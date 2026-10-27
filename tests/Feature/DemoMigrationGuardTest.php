<?php

use App\Services\DemoDatabaseGuard;
use App\Services\DemoEnvironmentGuard;
use App\Services\DemoMigrationGuard;
use Illuminate\Console\Events\CommandStarting;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\NullOutput;

function demoMigrationEvent(string $command): CommandStarting
{
    return new CommandStarting($command, new ArgvInput(['artisan', $command]), new NullOutput);
}

function emptyDemoSchema(): PDO
{
    $statement = Mockery::mock(PDOStatement::class);
    $statement->shouldReceive('fetchColumn')->times(4)->andReturn('0');
    $pdo = Mockery::mock(PDO::class);
    $pdo->shouldReceive('query')->times(4)->with(Mockery::pattern('/^SELECT COUNT\(\*\) FROM information_schema\.(TABLES|ROUTINES|TRIGGERS|EVENTS) WHERE [A-Z_]+ = DATABASE\(\)$/'))->andReturn($statement);

    return $pdo;
}

beforeEach(function () {
    $this->migrationMarker = sys_get_temp_dir().'/le-miting-migration-'.bin2hex(random_bytes(12));
});

afterEach(function () {
    if (is_file($this->migrationMarker)) {
        unlink($this->migrationMarker);
    }
});

test('only the exact approved isolated forward command is accepted', function () {
    DemoMigrationGuard::assertInvocation('cli', 'demo', DemoEnvironmentGuard::ROOT, ['migrate', '--env=demo', '--no-interaction']);
    expect(true)->toBeTrue();
});

test('new development migrations are not authorized by the historical demo approval', function () {
    expect(fn () => (new DemoMigrationGuard)->assertMigrationFiles(database_path('migrations')))
        ->toThrow(RuntimeException::class, 'unexpected migration chain');
});

test('forward authorization rejects different environments roots SAPIs and arguments', function (array $changes) {
    $arguments = array_replace(['cli', 'demo', DemoEnvironmentGuard::ROOT, ['migrate', '--env=demo', '--no-interaction']], $changes);
    expect(fn () => DemoMigrationGuard::assertInvocation(...$arguments))->toThrow(RuntimeException::class);
})->with([
    [[0 => 'cli-server']], [[0 => 'fpm-fcgi']], [[1 => 'local']], [[1 => 'testing']], [[1 => 'production']],
    [[2 => 'C:/Users/USER/Herd/fyp']], [[3 => ['migrate']]],
    ...array_map(fn (string $option): array => [[3 => ['migrate', '--env=demo', '--no-interaction', $option]]],
        ['--force', '--seed', '--database=mysql', '--path=elsewhere', '--pretend', '--graceful']),
    ...array_map(fn (string $command): array => [[3 => [$command, '--env=demo', '--no-interaction']]],
        ['migrate:install', 'migrate:fresh', 'migrate:refresh', 'migrate:rollback', 'migrate:reset', 'db:wipe', 'db:seed', 'serve']),
]);

test('migration mode requires exactly the temporary schema scoped privilege profile', function () {
    $usage = 'GRANT USAGE ON *.* TO `le_miting_demo`@`127.0.0.1`';
    $grant = 'GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, ALTER ON `le\\_miting\\_demo`.* TO `le_miting_demo`@`127.0.0.1`';
    (new DemoDatabaseGuard(true))->assertGrants([$usage, $grant], false);
    expect(fn () => (new DemoDatabaseGuard)->assertGrants([$usage, $grant], false))->toThrow(RuntimeException::class);
    expect(fn () => app(DemoDatabaseGuard::class)->assertGrants([$usage, $grant], false))->toThrow(RuntimeException::class);
    foreach ([str_replace(', ALTER', '', $grant), str_replace(', ALTER', ', INDEX, ALTER', $grant),
        str_replace('`le\\_miting\\_demo`.*', '*.*', $grant), $grant.' WITH GRANT OPTION',
        str_replace('`le\\_miting\\_demo`.*', '`le_miting_demo`.*', $grant)] as $unsafe) {
        expect(fn () => (new DemoDatabaseGuard(true))->assertGrants([$usage, $unsafe], false))->toThrow(RuntimeException::class);
    }
    expect(fn () => (new DemoDatabaseGuard(true))->assertGrants([], false))->toThrow(RuntimeException::class);
});

test('nonempty or ambiguous schema checks fail closed', function (mixed $count) {
    $statement = Mockery::mock(PDOStatement::class);
    $statement->shouldReceive('fetchColumn')->once()->andReturn($count);
    $pdo = Mockery::mock(PDO::class);
    $pdo->shouldReceive('query')->once()->andReturn($statement);
    expect(fn () => (new DemoMigrationGuard)->assertEmptySchema($pdo))->toThrow(RuntimeException::class);
})->with([1, '1', false, null, '', 'unknown']);

test('forward migration requires verified emptiness and permits one internal install only', function () {
    $guard = new DemoMigrationGuard;
    expect(fn () => $guard->protectCommand(demoMigrationEvent('migrate'), $this->migrationMarker))->toThrow(RuntimeException::class);
    expect(fn () => $guard->protectCommand(demoMigrationEvent('migrate:install'), $this->migrationMarker))->toThrow(RuntimeException::class);
    $guard->assertEmptySchema(emptyDemoSchema());
    $guard->protectCommand(demoMigrationEvent('migrate'), $this->migrationMarker);
    $guard->protectCommand(demoMigrationEvent('migrate:install'), $this->migrationMarker);
    expect(is_file($this->migrationMarker))->toBeTrue();
    expect(fn () => $guard->protectCommand(demoMigrationEvent('migrate:install'), $this->migrationMarker))->toThrow(RuntimeException::class);
    expect(fn () => $guard->protectCommand(demoMigrationEvent('migrate'), $this->migrationMarker))->toThrow(RuntimeException::class);
    $second = new DemoMigrationGuard;
    $second->assertEmptySchema(emptyDemoSchema());
    expect(fn () => $second->protectCommand(demoMigrationEvent('migrate'), $this->migrationMarker))->toThrow(RuntimeException::class, 'already claimed');
});

test('migration mode blocks unrelated and destructive internal commands', function (string $command) {
    $guard = new DemoMigrationGuard;
    $guard->assertEmptySchema(emptyDemoSchema());
    $guard->protectCommand(demoMigrationEvent('migrate'), $this->migrationMarker);
    expect(fn () => $guard->protectCommand(demoMigrationEvent($command), $this->migrationMarker))->toThrow(RuntimeException::class);
})->with(['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback', 'db:wipe', 'db:seed', 'tinker',
    'demo:preflight', 'queue:work', 'serve', 'admin:provision-local']);

test('approved SQL requires the claimed forward command', function (string $sql) {
    $guard = new DemoMigrationGuard;
    expect(fn () => $guard->assertQuery($sql))->toThrow(RuntimeException::class);
    $guard->assertEmptySchema(emptyDemoSchema());
    $guard->protectCommand(demoMigrationEvent('migrate'), $this->migrationMarker);
    $guard->assertQuery($sql);
    expect(true)->toBeTrue();
})->with([
    'create table `migrations` (`id` int unsigned not null auto_increment primary key)',
    'alter table `reservations` add `checked_in_at` timestamp null',
    'ALTER TABLE payments ADD CONSTRAINT payments_exactly_one_parent CHECK ((order_id IS NULL) <> (reservation_id IS NULL)) ENFORCED',
    'drop table if exists `feedback`',
    'insert into `migrations` (`migration`, `batch`) values (?, ?)',
    'update `orders` set `order_type` = ? where `order_type` = ?',
]);

test('migration SQL gate rejects writes outside the approved chain', function (string $sql) {
    $guard = new DemoMigrationGuard;
    $guard->assertEmptySchema(emptyDemoSchema());
    $guard->protectCommand(demoMigrationEvent('migrate'), $this->migrationMarker);
    expect(fn () => $guard->assertQuery($sql))->toThrow(RuntimeException::class);
})->with(['CREATE DATABASE le_miting_demo', 'CREATE TABLE unknown (id int)', 'DROP TABLE users',
    'TRUNCATE TABLE migrations', 'DELETE FROM migrations', 'INSERT INTO users (name) VALUES (?)',
    'UPDATE orders SET status = ?', 'SET FOREIGN_KEY_CHECKS=0', 'GRANT ALL ON *.* TO someone',
    'ALTER TABLE le_miting_dev.users ADD anything int']);

test('migration SQL gate permits read only introspection before the command starts', function () {
    (new DemoMigrationGuard)->assertQuery('SELECT VERSION()');
    expect(true)->toBeTrue();
});

test('migration manifest rejects missing files', function () {
    expect(fn () => (new DemoMigrationGuard)->assertMigrationFiles(sys_get_temp_dir().'/missing-'.bin2hex(random_bytes(8))))
        ->toThrow(RuntimeException::class);
});

test('migration manifest rejects altered files extra migrations and schema dumps', function (string $change) {
    $directory = sys_get_temp_dir().'/le-miting-manifest-'.bin2hex(random_bytes(12));
    mkdir($directory.'/migrations', 0700, true);
    mkdir($directory.'/schema', 0700);
    try {
        foreach (glob(database_path('migrations/*.php')) as $file) {
            if (basename($file) <= '2026_10_09_155139_repair_payment_parent_integrity.php') {
                copy($file, $directory.'/migrations/'.basename($file));
            }
        }
        $guard = new DemoMigrationGuard;
        $guard->assertMigrationFiles($directory.'/migrations');
        $target = match ($change) {
            'altered' => $directory.'/migrations/0001_01_01_000000_create_users_table.php',
            'extra' => $directory.'/migrations/unapproved.php',
            'dump' => $directory.'/schema/mysql-schema.sql',
        };
        file_put_contents($target, 'unapproved fixture');
        expect(fn () => $guard->assertMigrationFiles($directory.'/migrations'))->toThrow(RuntimeException::class);
    } finally {
        foreach (['migrations', 'schema'] as $folder) {
            foreach (glob($directory.'/'.$folder.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory.'/'.$folder);
        }
        rmdir($directory);
    }
})->with(['altered', 'extra', 'dump']);

test('registered command listener uses the migration gate only when explicitly bound', function () {
    $guard = Mockery::mock(DemoMigrationGuard::class);
    $guard->shouldReceive('protectCommand')->once()->withArgs(fn (CommandStarting $event, string $marker): bool => $event->command === 'migrate' && $marker === storage_path('app/private/demo-forward-migration.started'));
    app()->instance('demo.requested', true);
    app()->instance(DemoMigrationGuard::class, $guard);
    app('events')->dispatch(demoMigrationEvent('migrate'));
    expect(app('db')->connection()->getDatabaseName())->toBe('le_miting_test');
});
