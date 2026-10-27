<?php

use App\Services\DevelopmentDatabaseGuard;
use Illuminate\Config\Repository;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\NullOutput;

function productionGuard(string $configuredDatabase, ?string $connectedDatabase = null): DevelopmentDatabaseGuard
{
    $config = new Repository([
        'database' => [
            'default' => 'mysql',
            'connections' => ['mysql' => ['driver' => 'mysql', 'database' => $configuredDatabase]],
        ],
    ]);
    $application = Mockery::mock(Application::class);
    $application->shouldReceive('environment')->with('testing')->andReturn(false);
    $application->shouldReceive('environment')->with('production')->andReturn(true);
    $application->shouldReceive('offsetGet')->with('config')->andReturn($config);

    if ($connectedDatabase !== null) {
        $statement = Mockery::mock(PDOStatement::class);
        $statement->shouldReceive('fetchColumn')->once()->andReturn($connectedDatabase);
        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('query')->with('SELECT DATABASE()')->once()->andReturn($statement);
        $pdo->shouldReceive('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->andReturn('mysql');
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getPdo')->andReturn($pdo);
        $manager = Mockery::mock(DatabaseManager::class);
        $manager->shouldReceive('connection')->with('mysql')->andReturn($connection);
        $application->shouldReceive('make')->with('db')->andReturn($manager);
    } else {
        $application->shouldNotReceive('make');
    }

    return new DevelopmentDatabaseGuard($application);
}

function schemaCommand(string $command): CommandStarting
{
    return new CommandStarting($command, new ArgvInput(['artisan', $command]), new NullOutput);
}

test('production migration and seeding are allowed only for an authenticated unprotected database', function (string $command) {
    productionGuard('le_miting_production', 'le_miting_production')->protectCommand(schemaCommand($command));

    expect(true)->toBeTrue();
})->with(['migrate', 'migrate:install', 'db:seed']);

test('production refuses protected configured database names before connecting', function (string $database) {
    expect(fn () => productionGuard($database)->protectCommand(schemaCommand('migrate')))
        ->toThrow(RuntimeException::class, 'unprotected MySQL database');
})->with(['le_miting_dev', 'le_miting_demo', 'le_miting_test']);

test('production refuses a protected database reached through an alternate connection URL', function (string $database) {
    expect(fn () => productionGuard('le_miting_production', $database)->protectCommand(schemaCommand('db:seed')))
        ->toThrow(RuntimeException::class, 'unprotected MySQL database');
})->with(['le_miting_dev', 'le_miting_demo', 'le_miting_test']);

test('production refuses destructive schema commands before connecting', function (string $command) {
    expect(fn () => productionGuard('le_miting_production')->protectCommand(schemaCommand($command)))
        ->toThrow(RuntimeException::class, 'destructive production schema commands');
})->with(['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback', 'db:wipe']);
