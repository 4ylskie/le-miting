<?php

use App\Console\Commands\ProvisionDemoAdmin;
use App\Models\MenuCategory;
use App\Models\Table;
use App\Models\User;
use App\Services\DemoEnvironmentGuard;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\MySqlTestDatabaseGuard;
use Tests\NonTransactionalDatabase;

uses(NonTransactionalDatabase::class);

/** Substitute only the terminal and demo identity boundary; writes remain test-guarded. */
class DemoAdminCommandUnderTest extends ProvisionDemoAdmin
{
    protected $signature = 'admin:provision-demo';

    public ?Closure $connectionVerifier = null;

    protected function hasInteractiveTerminal(): bool
    {
        return true;
    }

    protected function verifiedConnection(): Connection
    {
        return $this->connectionVerifier === null ? parent::verifiedConnection() : ($this->connectionVerifier)();
    }
}

function demoAdminTester(bool $realGuard = false, ?Closure $verify = null): CommandTester
{
    $command = new DemoAdminCommandUnderTest;
    $command->setLaravel(app());
    if (! $realGuard) {
        $command->connectionVerifier = $verify ?? function (): Connection {
            $connection = DB::connection();
            MySqlTestDatabaseGuard::assertConnection($connection);

            return $connection;
        };
    }

    return new CommandTester($command);
}

/** @return list<string> */
function demoAdminInputs(): array
{
    $password = Str::password(24).'aA1!';

    return ['Demo administrator', 'demo-admin@example.test', $password, $password];
}

test('demo command requires an interactive terminal and accepts no credential or force options', function () {
    $this->artisan('admin:provision-demo', ['--no-interaction' => true])->assertFailed();
    $command = new ProvisionDemoAdmin;
    $command->setLaravel(app());
    $tester = new CommandTester($command);
    $tester->setInputs(['']);
    expect($tester->execute([], ['interactive' => true]))->toBe(1);
    expect($tester->getDisplay())->toContain('requires an interactive local terminal');
    expect($command->getDefinition()->getArguments())->toBeEmpty();
    expect($command->getDefinition()->hasOption('password'))->toBeFalse();
    expect($command->getDefinition()->hasOption('force'))->toBeFalse();
    $this->assertDatabaseCount('users', 0);
});

test('demo provisioning rejects non demo environments before collecting credentials', function (string $environment) {
    app()->instance('env', $environment);
    try {
        $tester = demoAdminTester(realGuard: true);
        expect($tester->execute([], ['interactive' => true]))->toBe(1);
        expect($tester->getDisplay())->toContain('Provisioning stopped safely')->not->toContain('Administrator name');
    } finally {
        app()->instance('env', 'testing');
    }
    $this->assertDatabaseCount('users', 0);
})->with(['local', 'production', 'testing']);

test('claiming demo environment cannot bypass runtime and database isolation', function () {
    app()->instance('env', 'demo');
    try {
        $tester = demoAdminTester(realGuard: true);
        expect($tester->execute([], ['interactive' => true]))->toBe(1);
        expect($tester->getDisplay())->not->toContain('Administrator name');
    } finally {
        app()->instance('env', 'testing');
    }
    $this->assertDatabaseCount('users', 0);
});

test('demo command allowlist requires interactive execution without force', function () {
    $guard = new DemoEnvironmentGuard;
    $input = new ArgvInput(['artisan', 'admin:provision-demo']);
    $guard->protectCommand(new CommandStarting('admin:provision-demo', $input, new NullOutput));
    $input->setInteractive(false);
    expect(fn () => $guard->protectCommand(new CommandStarting('admin:provision-demo', $input, new NullOutput)))
        ->toThrow(RuntimeException::class);
    expect(fn () => $guard->protectCommand(new CommandStarting('admin:provision-demo',
        new ArgvInput(['artisan', 'admin:provision-demo', '--force']), new NullOutput)))->toThrow(RuntimeException::class);
});

test('demo provisioning hashes one authorized administrator and preserves restaurant records without logging credentials', function () {
    $category = MenuCategory::factory()->create();
    $table = Table::factory()->create();
    $before = [$category->fresh()->getAttributes(), $table->fresh()->getAttributes()];
    Mail::fake();
    Log::spy();
    $checks = 0;
    $tester = demoAdminTester(verify: function () use (&$checks): Connection {
        $connection = DB::connection();
        MySqlTestDatabaseGuard::assertConnection($connection);
        expect($connection->transactionLevel())->toBe($checks++ === 0 ? 0 : 1);

        return $connection;
    });
    $inputs = demoAdminInputs();
    $tester->setInputs($inputs);
    expect($tester->execute([], ['interactive' => true]))->toBe(0);
    $admin = User::sole();
    expect($admin->name)->toBe($inputs[0]);
    expect($admin->email)->toBe($inputs[1]);
    expect($admin->role)->toBe('admin');
    expect($admin->isAdmin())->toBeTrue();
    expect(Hash::check($inputs[2], $admin->password))->toBeTrue();
    expect($admin->password)->not->toBe($inputs[2]);
    expect($checks)->toBe(2);
    expect($tester->getDisplay())->toContain('One demo administrator created')->not->toContain($inputs[2])->not->toContain($admin->password);
    expect([$category->fresh()->getAttributes(), $table->fresh()->getAttributes()])->toBe($before);
    foreach (['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency', 'log'] as $method) {
        Log::shouldNotHaveReceived($method);
    }
    Mail::assertNothingSent();
    $duplicate = demoAdminTester();
    expect($duplicate->execute([], ['interactive' => true]))->toBe(1);
    expect($duplicate->getDisplay())->toContain('a user already exists');
    $this->assertDatabaseCount('users', 1);
});

test('demo provisioning refuses any existing user without modifying it', function (string $role) {
    $user = User::factory()->create(['role' => $role, 'password' => Str::password(24)]);
    $before = $user->fresh()->getAttributes();
    $tester = demoAdminTester();
    expect($tester->execute([], ['interactive' => true]))->toBe(1);
    expect($tester->getDisplay())->not->toContain('Administrator name');
    expect($user->fresh()->getAttributes())->toBe($before);
    $this->assertDatabaseCount('users', 1);
})->with(['admin', 'customer']);

test('demo provisioning rejects invalid credentials without exposing input', function (string $case) {
    $inputs = demoAdminInputs();
    match ($case) {
        'email' => $inputs[1] = 'invalid',
        'weak' => $inputs[2] = $inputs[3] = Str::lower(Str::random(5)),
        'mismatch' => $inputs[3] = Str::password(24),
    };
    $tester = demoAdminTester();
    $tester->setInputs($inputs);
    expect($tester->execute([], ['interactive' => true]))->toBe(1);
    expect($tester->getDisplay())->not->toContain($inputs[2])->not->toContain($inputs[3]);
    $this->assertDatabaseCount('users', 0);
})->with(['email', 'weak', 'mismatch']);

test('demo provisioning rolls back an interrupted insertion without logging sensitive exception content', function () {
    $inputs = demoAdminInputs();
    $dispatcher = User::getEventDispatcher();
    User::setEventDispatcher(clone $dispatcher);
    User::created(fn () => throw new RuntimeException($inputs[2]));
    Log::spy();
    try {
        $tester = demoAdminTester();
        $tester->setInputs($inputs);
        expect($tester->execute([], ['interactive' => true]))->toBe(1);
        expect($tester->getDisplay())->not->toContain($inputs[2]);
        $this->assertDatabaseCount('users', 0);
        expect(DB::transactionLevel())->toBe(0);
        expect(DB::selectOne('SELECT IS_FREE_LOCK(?) AS free', ['le_miting_test.admin.provision-local'])->free)->toBe(1);
        Log::shouldNotHaveReceived('error');
    } finally {
        User::setEventDispatcher($dispatcher);
    }
});
