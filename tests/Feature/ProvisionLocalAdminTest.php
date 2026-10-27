<?php

use App\Console\Commands\ProvisionLocalAdmin;
use App\Models\MenuCategory;
use App\Models\Table;
use App\Models\User;
use App\Services\DevelopmentDatabaseGuard;
use Illuminate\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\MySqlTestDatabaseGuard;
use Tests\NonTransactionalDatabase;

uses(NonTransactionalDatabase::class);

/** Only tests replace the terminal and development-identity boundary; real queries remain test-guarded. */
class LocalAdminCommandUnderTest extends ProvisionLocalAdmin
{
    protected $signature = 'admin:provision-local';

    public ?Closure $connectionVerifier = null;

    protected function hasInteractiveTerminal(): bool
    {
        return true;
    }

    protected function verifiedConnection(): Connection
    {
        return $this->connectionVerifier === null
            ? parent::verifiedConnection()
            : ($this->connectionVerifier)();
    }
}

function localAdminTester(?Closure $verify = null, bool $realGuard = false): CommandTester
{
    $command = new LocalAdminCommandUnderTest;
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
function localAdminInputs(): array
{
    $password = Str::password(24).'aA1!';

    return ['Local administrator', 'local-admin@example.test', $password, $password];
}

test('the registered command refuses noninteractive execution without prompting or writing', function () {
    $this->artisan('admin:provision-local', ['--no-interaction' => true])
        ->expectsOutputToContain('requires an interactive local terminal')
        ->assertFailed();

    $this->assertDatabaseCount('users', 0);
});

test('the real command rejects redirected input even when interactive mode is requested', function () {
    $command = new ProvisionLocalAdmin;
    $command->setLaravel(app());
    $tester = new CommandTester($command);
    $tester->setInputs(['']);

    expect($tester->execute([], ['interactive' => true]))->toBe(1);
    expect($tester->getDisplay())->toContain('requires an interactive local terminal');
    $this->assertDatabaseCount('users', 0);
});

test('the real development guard rejects the testing application before credentials are requested', function () {
    $tester = localAdminTester(realGuard: true);

    expect($tester->execute([], ['interactive' => true]))->toBe(1);
    expect($tester->getDisplay())->toContain('Provisioning stopped safely')->not->toContain('Administrator name');
    $this->assertDatabaseCount('users', 0);
});

test('the development guard refuses unsafe environment or database configuration before connecting', function (string $environment, string $database, bool $cached) {
    $config = new Repository([
        'app' => ['env' => $environment],
        'database' => ['default' => 'mysql', 'connections' => ['mysql' => [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3307,
            'database' => $database, 'username' => 'le_miting_dev',
            'password' => Str::random(32), 'timezone' => '+00:00',
        ]]],
    ]);
    $application = Mockery::mock(Application::class);
    $application->shouldReceive('environment')->with('local')->andReturn($environment === 'local');
    $application->shouldReceive('offsetGet')->with('config')->andReturn($config);
    $application->shouldReceive('configurationIsCached')->andReturn($cached);
    $application->shouldReceive('bound')->with('config_loaded_from_cache')->andReturn(false);
    $application->shouldNotReceive('make');
    app()->instance(DevelopmentDatabaseGuard::class, new DevelopmentDatabaseGuard($application));
    $tester = localAdminTester(realGuard: true);

    expect($tester->execute([], ['interactive' => true]))->toBe(1);
    expect($tester->getDisplay())->toContain('Provisioning stopped safely')->not->toContain('Administrator name');
    $this->assertDatabaseCount('users', 0);
})->with([
    'production' => ['production', 'le_miting_dev', false],
    'test schema in local environment' => ['local', 'le_miting_test', false],
    'unapproved schema' => ['local', 'another_database', false],
    'cached configuration' => ['local', 'le_miting_dev', true],
]);

test('no force or password option can bypass provisioning safeguards', function () {
    $definition = (new ProvisionLocalAdmin)->getDefinition();

    expect($definition->hasOption('force'))->toBeFalse();
    expect($definition->hasOption('password'))->toBeFalse();
    expect($definition->getArguments())->toBeEmpty();
});

test('the existing guard accepts local mode only with the approved authenticated development identity', function () {
    $config = new Repository([
        'app' => ['env' => 'local'],
        'database' => ['default' => 'mysql', 'connections' => ['mysql' => [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3307,
            'database' => 'le_miting_dev', 'username' => 'le_miting_dev',
            'password' => Str::random(32), 'timezone' => '+00:00',
        ]]],
    ]);
    $identity = Mockery::mock(PDOStatement::class);
    $identity->shouldReceive('fetch')->with(PDO::FETCH_ASSOC)->once()->andReturn([
        'version' => '26.7.0', 'product' => 'MySQL Community Server - GPL', 'port' => 3307,
        'datadir' => 'C:/ProgramData/MySQL/MySQL Server 26.7/Data/', 'db' => 'le_miting_dev',
        'account' => 'le_miting_dev@127.0.0.1', 'roles' => 'NONE', 'mandatory_roles' => '',
        'partial_revokes' => 1, 'timezone' => '+00:00',
    ]);
    $grants = Mockery::mock(PDOStatement::class);
    $grants->shouldReceive('fetchAll')->with(PDO::FETCH_COLUMN)->once()->andReturn([
        'GRANT USAGE ON *.* TO `le_miting_dev`@`127.0.0.1`',
        'GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, INDEX, ALTER ON `le_miting_dev`.* TO `le_miting_dev`@`127.0.0.1`',
    ]);
    $pdo = Mockery::mock(PDO::class);
    $pdo->shouldReceive('query')->twice()->andReturn($identity, $grants);
    $pdo->shouldReceive('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->andReturn('mysql');
    $pdo->shouldReceive('getAttribute')->with(PDO::ATTR_CONNECTION_STATUS)->andReturn('127.0.0.1 via TCP/IP');
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getPdo')->andReturn($pdo);
    $manager = Mockery::mock(DatabaseManager::class);
    $manager->shouldReceive('connection')->with('mysql')->andReturn($connection);
    $application = Mockery::mock(Application::class);
    $application->shouldReceive('environment')->with('local')->andReturn(true);
    $application->shouldReceive('offsetGet')->with('config')->andReturn($config);
    $application->shouldReceive('configurationIsCached')->andReturn(false);
    $application->shouldReceive('bound')->with('config_loaded_from_cache')->andReturn(false);
    $application->shouldReceive('make')->with('db')->andReturn($manager);

    expect((new DevelopmentDatabaseGuard($application))->verify())->toBe($connection);
});

test('provisioning creates one hashed administrator without modifying restaurant data or exposing credentials', function () {
    $category = MenuCategory::factory()->create();
    $table = Table::factory()->create();
    $categoryBefore = $category->fresh()->getAttributes();
    $tableBefore = $table->fresh()->getAttributes();
    Mail::fake();
    Log::spy();
    $checks = 0;
    $tester = localAdminTester(function () use (&$checks): Connection {
        $connection = DB::connection();
        MySqlTestDatabaseGuard::assertConnection($connection);
        expect($connection->transactionLevel())->toBe($checks === 0 ? 0 : 1);
        $checks++;

        return $connection;
    });
    $inputs = localAdminInputs();
    $tester->setInputs($inputs);

    expect($tester->execute([], ['interactive' => true]))->toBe(0);

    $this->assertDatabaseCount('users', 1);
    $admin = User::sole();
    expect($admin->name)->toBe($inputs[0]);
    expect($admin->email)->toBe($inputs[1]);
    expect($admin->role)->toBe('admin');
    expect($admin->isAdmin())->toBeTrue();
    expect(Hash::check($inputs[2], $admin->password))->toBeTrue();
    expect($admin->password === $inputs[2])->toBeFalse();
    expect($admin->email_verified_at)->toBeNull();
    expect($checks)->toBe(2);
    expect($tester->getDisplay())->toContain('One local administrator created')
        ->not->toContain($inputs[2])->not->toContain($admin->password);
    expect($category->fresh()->getAttributes())->toBe($categoryBefore);
    expect($table->fresh()->getAttributes())->toBe($tableBefore);
    Mail::assertNothingSent();
    foreach (['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency', 'log'] as $method) {
        Log::shouldNotHaveReceived($method);
    }
    expect(DB::selectOne('SELECT IS_FREE_LOCK(?) AS free', ['le_miting_test.admin.provision-local'])->free)->toBe(1);

    $this->post(route('admin.login.submit'), ['email' => $inputs[1], 'password' => $inputs[2]])
        ->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($admin);
});

test('any existing user prevents provisioning without altering the account', function (string $role) {
    $user = User::factory()->create(['role' => $role, 'password' => Str::password(24)]);
    $before = hash('sha256', json_encode($user->fresh()->getAttributes()));
    $tester = localAdminTester();

    expect($tester->execute([], ['interactive' => true]))->toBe(1);

    expect($tester->getDisplay())->toContain('a user already exists')->not->toContain('Administrator name');
    $this->assertDatabaseCount('users', 1);
    expect(hash('sha256', json_encode($user->fresh()->getAttributes())))->toBe($before);
})->with(['admin', 'customer']);

test('invalid credential input creates no account and is never echoed', function (string $case, string $message) {
    $inputs = localAdminInputs();
    match ($case) {
        'email' => $inputs[1] = 'not-an-email',
        'name' => $inputs[0] = '',
        'weak' => $inputs[2] = $inputs[3] = Str::lower(Str::random(5)),
        'long' => $inputs[2] = $inputs[3] = Str::password(73),
        'mismatch' => $inputs[3] = Str::password(24),
    };
    Log::spy();
    $tester = localAdminTester();
    $tester->setInputs($inputs);

    expect($tester->execute([], ['interactive' => true]))->toBe(1);

    expect($tester->getDisplay())->toContain($message)->not->toContain($inputs[2])->not->toContain($inputs[3]);
    $this->assertDatabaseCount('users', 0);
    Log::shouldNotHaveReceived('error');
})->with([
    'invalid email' => ['email', 'valid email address'],
    'missing name' => ['name', 'name field is required'],
    'weak password' => ['weak', 'at least 12 characters'],
    'bcrypt byte limit' => ['long', 'must not exceed 72 bytes'],
    'password mismatch' => ['mismatch', 'confirmation does not match'],
]);

test('a second independent MySQL session cannot provision while the first holds the lock', function () {
    $other = app('db.factory')->make(DB::connection()->getConfig(), 'provisioning-contender');
    MySqlTestDatabaseGuard::assertConnection($other);
    expect($other->selectOne('SELECT CONNECTION_ID() AS id')->id)
        ->not->toBe(DB::selectOne('SELECT CONNECTION_ID() AS id')->id);
    expect($other->selectOne('SELECT GET_LOCK(?, 0) AS acquired', ['le_miting_test.admin.provision-local'])->acquired)->toBe(1);

    try {
        $tester = localAdminTester();
        $tester->setInputs(localAdminInputs());
        expect($tester->execute([], ['interactive' => true]))->toBe(1);
        expect($tester->getDisplay())->toContain('another provisioning attempt holds the lock');
        $this->assertDatabaseCount('users', 0);
    } finally {
        $other->selectOne('SELECT RELEASE_LOCK(?)', ['le_miting_test.admin.provision-local']);
        $other->disconnect();
    }

    $retry = localAdminTester();
    $retry->setInputs(localAdminInputs());
    expect($retry->execute([], ['interactive' => true]))->toBe(0);
    $this->assertDatabaseCount('users', 1);
    $later = localAdminTester();
    expect($later->execute([], ['interactive' => true]))->toBe(1);
    $this->assertDatabaseCount('users', 1);
});

test('a user inserted after the initial check is caught by the transactional recheck', function () {
    $checks = 0;
    $tester = localAdminTester(function () use (&$checks): Connection {
        $connection = DB::connection();
        MySqlTestDatabaseGuard::assertConnection($connection);
        if (++$checks === 2) {
            $other = app('db.factory')->make($connection->getConfig(), 'provisioning-race');
            $attributes = User::factory()->make(['password' => Str::password(24)])->getAttributes();
            $other->table('users')->insert($attributes);
            $other->disconnect();
        }

        return $connection;
    });
    $inputs = localAdminInputs();
    $tester->setInputs($inputs);

    expect($tester->execute([], ['interactive' => true]))->toBe(1);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseMissing('users', ['email' => $inputs[1]]);
    expect(DB::selectOne('SELECT IS_FREE_LOCK(?) AS free', ['le_miting_test.admin.provision-local'])->free)->toBe(1);
});

test('a failed transactional identity recheck rolls back and releases the provisioning lock', function () {
    $checks = 0;
    $tester = localAdminTester(function () use (&$checks): Connection {
        if (++$checks === 2) {
            throw new RuntimeException('Simulated identity failure');
        }

        return DB::connection();
    });
    $tester->setInputs(localAdminInputs());

    expect($tester->execute([], ['interactive' => true]))->toBe(1);
    $this->assertDatabaseCount('users', 0);
    expect(DB::transactionLevel())->toBe(0);
    expect(DB::selectOne('SELECT IS_FREE_LOCK(?) AS free', ['le_miting_test.admin.provision-local'])->free)->toBe(1);
});

test('an exception after insertion rolls back without exposing sensitive exception contents', function () {
    $inputs = localAdminInputs();
    $originalDispatcher = User::getEventDispatcher();
    User::setEventDispatcher(clone $originalDispatcher);
    User::created(function () use ($inputs): void {
        throw new RuntimeException($inputs[2]);
    });
    Log::spy();

    try {
        $tester = localAdminTester();
        $tester->setInputs($inputs);

        expect($tester->execute([], ['interactive' => true]))->toBe(1);
        expect($tester->getDisplay())->toContain('Provisioning stopped safely')->not->toContain($inputs[2]);
        $this->assertDatabaseCount('users', 0);
        expect(DB::transactionLevel())->toBe(0);
        expect(DB::selectOne('SELECT IS_FREE_LOCK(?) AS free', ['le_miting_test.admin.provision-local'])->free)->toBe(1);
        Log::shouldNotHaveReceived('error');
    } finally {
        User::setEventDispatcher($originalDispatcher);
    }
});
