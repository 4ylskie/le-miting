<?php

use App\Services\DemoDatabaseGuard;
use App\Services\DemoEnvironmentGuard;
use App\Services\DemoGuardedConnectionFactory;
use Illuminate\Config\Repository;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Connectors\ConnectorInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Process\Process;
use Tests\GuardedConnectionFactory;
use Tests\MySqlTestDatabaseGuard;

/** Synthetic configuration only; never used to open a demo connection. */
function demoDatabaseConfig(): array
{
    return ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '3307',
        'database' => 'le_miting_demo', 'username' => 'le_miting_demo',
        'password' => 'non-connecting-test-sentinel', 'timezone' => '+00:00'];
}

function demoIdentity(): array
{
    return ['version' => '26.7.0', 'product' => 'MySQL Community Server - GPL', 'port' => 3307,
        'datadir' => 'C:/ProgramData/MySQL/MySQL Server 26.7/Data/', 'db' => 'le_miting_demo',
        'account' => 'le_miting_demo@127.0.0.1', 'timezone' => '+00:00',
        'roles' => 'NONE', 'mandatory_roles' => '', 'partial_revokes' => 0];
}

function demoGrants(): array
{
    return ['GRANT USAGE ON *.* TO `le_miting_demo`@`127.0.0.1`',
        'GRANT SELECT, INSERT, UPDATE, DELETE ON `le\\_miting\\_demo`.* TO `le_miting_demo`@`127.0.0.1`'];
}

function demoSettings(): array
{
    $config = app('config')->all();
    $explicit = [];
    foreach ([
        'APP_ENV' => ['app.env', 'demo'], 'APP_URL' => ['app.url', DemoEnvironmentGuard::ORIGIN],
        'APP_DEBUG' => ['app.debug', false], 'SESSION_DRIVER' => ['session.driver', 'database'],
        'SESSION_CONNECTION' => ['session.connection', 'mysql'], 'SESSION_COOKIE' => ['session.cookie', 'le-miting-demo-session'],
        'SESSION_DOMAIN' => ['session.domain', null], 'CACHE_STORE' => ['cache.default', 'database'],
        'CACHE_PREFIX' => ['cache.prefix', 'le-miting-demo-cache-'],
        'DB_CACHE_CONNECTION' => ['cache.stores.database.connection', 'mysql'],
        'DB_CACHE_LOCK_CONNECTION' => ['cache.stores.database.lock_connection', 'mysql'],
        'MAIL_MAILER' => ['mail.default', 'array'], 'QUEUE_CONNECTION' => ['queue.default', 'sync'],
        'PAYMENT_DRIVER' => ['payment.driver', 'mock'], 'AI_DRIVER' => ['ai.driver', 'mock'],
        'LOG_CHANNEL' => ['logging.default', 'single'], 'FILESYSTEM_DISK' => ['filesystems.default', 'local'],
    ] as $variable => [$key, $value]) {
        data_set($config, $key, $value);
        $explicit[$variable] = $value === false ? 'false' : ($value === null ? 'null' : $value);
    }
    data_set($config, 'database.default', 'mysql');
    data_set($config, 'database.connections', ['mysql' => demoDatabaseConfig()]);
    data_set($config, 'cache.stores.database.table', 'cache');
    data_set($config, 'cache.stores.database.lock_table', 'cache_locks');
    data_set($config, 'app.previous_keys', []);
    $key = random_bytes(32);
    data_set($config, 'app.key', 'base64:'.base64_encode($key));
    $explicit += ['APP_KEY' => data_get($config, 'app.key'), 'DB_CONNECTION' => 'mysql',
        'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307', 'DB_DATABASE' => 'le_miting_demo',
        'DB_USERNAME' => 'le_miting_demo', 'DB_PASSWORD' => 'non-connecting-test-sentinel'];
    $fingerprints = ['source_key_sha256' => hash('sha256', 'different-source-key'),
        'demo_key_sha256' => hash('sha256', $key)];

    return [$config, $explicit, $fingerprints];
}

test('demo policy accepts its exact synthetic configuration without connecting', function () {
    (new DemoDatabaseGuard)->assertConfiguration(demoDatabaseConfig());
    (new DemoDatabaseGuard)->assertIdentity(demoIdentity());
    (new DemoDatabaseGuard)->assertGrants(demoGrants(), false);
    (new DemoEnvironmentGuard)->assertSettings(...demoSettings());
    expect(true)->toBeTrue();
});

test('demo database configuration rejects an unsafe target before connecting', function (string $field, mixed $value) {
    $config = demoDatabaseConfig();
    $config[$field] = $value;
    expect(fn () => (new DemoDatabaseGuard)->assertConfiguration($config))->toThrow(RuntimeException::class);
})->with([
    ['driver', 'sqlite'], ['driver', 'mariadb'], ['database', 'le_miting_dev'], ['database', 'le_miting_test'],
    ['database', 'production'], ['username', 'root'], ['username', 'le_miting_test'], ['host', 'localhost'],
    ['host', ['127.0.0.1']], ['port', '3306'], ['timezone', 'SYSTEM'], ['password', ''],
    ['url', 'mysql://invalid'], ['read', ['host' => 'elsewhere']], ['write', ['database' => 'le_miting_dev']],
    ['direct', true], ['unix_socket', '/tmp/mysql.sock'], ['prefix', 'dev.'], ['options', [PDO::ATTR_PERSISTENT => true]],
]);

test('demo server identity rejects unapproved values', function (string $field, mixed $value) {
    $identity = demoIdentity();
    $identity[$field] = $value;
    expect(fn () => (new DemoDatabaseGuard)->assertIdentity($identity))->toThrow(RuntimeException::class);
})->with([
    ['version', '8.4.0'], ['product', 'MariaDB Server'], ['port', 3306], ['datadir', 'C:/xampp/mysql/data'],
    ['db', 'le_miting_dev'], ['db', 'le_miting_test'], ['account', 'root@localhost'],
    ['timezone', 'SYSTEM'], ['roles', 'administrator'], ['mandatory_roles', 'admin'], ['partial_revokes', null],
]);

test('demo privileges reject missing excessive or wildcard scope', function (array $grants) {
    expect(fn () => (new DemoDatabaseGuard)->assertGrants($grants, false))->toThrow(RuntimeException::class);
})->with([
    'missing' => [[]],
    'global' => [['GRANT ALL PRIVILEGES ON *.* TO `le_miting_demo`@`127.0.0.1`']],
    'unescaped schema' => [['GRANT USAGE ON *.* TO `le_miting_demo`@`127.0.0.1`',
        'GRANT SELECT, INSERT, UPDATE, DELETE ON `le_miting_demo`.* TO `le_miting_demo`@`127.0.0.1`']],
    'extra grant' => [[...demoGrants(), 'GRANT SELECT ON `le_miting_dev`.* TO `le_miting_demo`@`127.0.0.1`']],
    'migration privilege not authorized' => [[...demoGrants(), 'GRANT CREATE ON `le_miting_demo`.* TO `le_miting_demo`@`127.0.0.1`']],
]);

test('demo privileges accept exact literal scope when partial revokes is enabled', function () {
    $grants = str_replace('le\\_miting\\_demo', 'le_miting_demo', demoGrants());
    (new DemoDatabaseGuard)->assertGrants($grants, true);
    expect($grants)->toHaveCount(2);
});

/** PDO double verifies probe/control flow only, never live account isolation. */
function demoPdo(?string $accessible = null): PDO
{
    $pdo = Mockery::mock(PDO::class);
    $pdo->shouldReceive('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->andReturn('mysql');
    $pdo->shouldReceive('getAttribute')->with(PDO::ATTR_CONNECTION_STATUS)->andReturn('127.0.0.1 via TCP/IP');
    $identity = Mockery::mock(PDOStatement::class);
    $identity->shouldReceive('fetch')->with(PDO::FETCH_ASSOC)->andReturn(demoIdentity());
    $grants = Mockery::mock(PDOStatement::class);
    $grants->shouldReceive('fetchAll')->with(PDO::FETCH_COLUMN)->andReturn(demoGrants());
    $selected = Mockery::mock(PDOStatement::class);
    $selected->shouldReceive('fetchColumn')->andReturn('le_miting_demo');
    $pdo->shouldReceive('query')->with(Mockery::on(fn ($sql) => str_starts_with($sql, 'SELECT VERSION()')))->andReturn($identity);
    $pdo->shouldReceive('query')->with('SHOW GRANTS')->andReturn($grants);
    $pdo->shouldReceive('query')->with('SELECT DATABASE()')->andReturn($selected);
    foreach (['le_miting_dev', 'le_miting_test'] as $schema) {
        $pdo->shouldReceive('exec')->with('USE `'.$schema.'`')->andReturnUsing(function () use ($schema, $accessible) {
            if ($schema === $accessible) {
                return 0;
            }
            $exception = new PDOException('Denied');
            $exception->errorInfo = ['42000', 1044, 'Denied'];
            throw $exception;
        });
    }

    return $pdo;
}

test('demo authenticated probe requires both cross schema denials', function (?string $accessible) {
    if ($accessible !== null) {
        expect(fn () => (new DemoDatabaseGuard)->assertPdo(demoPdo($accessible)))->toThrow(RuntimeException::class);
    } else {
        (new DemoDatabaseGuard)->assertPdo(demoPdo());
        expect(true)->toBeTrue();
    }
})->with([null, 'le_miting_dev', 'le_miting_test']);

test('demo runtime rejects conflicting effective settings', function (string $field, mixed $value) {
    [$config, $explicit, $fingerprints] = demoSettings();
    data_set($config, $field, $value);
    expect(fn () => (new DemoEnvironmentGuard)->assertSettings($config, $explicit, $fingerprints))->toThrow(RuntimeException::class);
})->with([
    ['app.env', 'local'], ['app.env', 'production'], ['app.env', 'testing'], ['app.url', 'http://fyp.test'],
    ['app.debug', true], ['session.cookie', 'le-miting-session'], ['session.domain', '.test'],
    ['session.driver', 'file'], ['session.connection', 'dev'], ['session.table', 'other.sessions'],
    ['cache.prefix', 'le-miting-cache-'], ['cache.stores.database.connection', 'dev'],
    ['cache.stores.database.lock_connection', 'dev'], ['cache.stores.database.lock_table', 'other.cache_locks'],
    ['mail.default', 'smtp'], ['mail.mailers.array.transport', 'smtp'], ['payment.driver', 'fiuu'],
    ['ai.driver', 'openai'], ['queue.default', 'database'], ['logging.default', 'stack'],
    ['logging.channels.single.driver', 'slack'], ['app.timezone', 'Asia/Kuala_Lumpur'],
    ['app.previous_keys', ['old-key']], ['payment.fiuu.secret_key', 'forbidden-test-value'],
    ['openai.api_key', 'forbidden-test-value'], ['mail.mailers.smtp.password', 'forbidden-test-value'],
    ['services.ses.key', 'forbidden-test-value'], ['filesystems.disks.public.driver', 's3'],
    ['database.connections.other', ['driver' => 'mysql']], ['database.default', 'other'],
]);

test('demo runtime requires explicit configuration and a different recorded key', function (string $problem) {
    [$config, $explicit, $fingerprints] = demoSettings();
    if ($problem === 'missing') {
        $explicit = [];
    } elseif ($problem === 'key reused') {
        $fingerprints['source_key_sha256'] = $fingerprints['demo_key_sha256'];
    } elseif ($problem === 'key changed') {
        $fingerprints['demo_key_sha256'] = hash('sha256', 'wrong');
    } elseif ($problem === 'external credential') {
        $explicit['MAIL_PASSWORD'] = 'not-a-real-password';
    } else {
        unset($explicit['DB_PASSWORD']);
    }
    expect(fn () => (new DemoEnvironmentGuard)->assertSettings($config, $explicit, $fingerprints))->toThrow(RuntimeException::class);
})->with(['missing', 'key reused', 'key changed', 'external credential', 'missing database password']);

test('demo cannot bootstrap in the development runtime or fall back to its environment file', function () {
    expect(fn () => DemoEnvironmentGuard::assertBootFiles($this->app))->toThrow(RuntimeException::class);
    $this->artisan('demo:preflight')->expectsOutputToContain('No fallback or writes performed.')->assertFailed();
});

test('demo boot policy rejects missing configuration cached configuration and shared roots', function (array $changes) {
    $state = array_replace([DemoEnvironmentGuard::ROOT, DemoEnvironmentGuard::ROOT, true, false, false], $changes);
    expect(fn () => DemoEnvironmentGuard::assertBootState(...$state))->toThrow(RuntimeException::class);
})->with([
    'missing file' => [[2 => false]], 'fallback file' => [[3 => true]], 'cached' => [[4 => true]],
    'development root' => [[0 => 'C:/Users/USER/Herd/fyp']],
    'junction to development' => [[1 => 'C:/Users/USER/Herd/fyp']],
]);

test('demo boot policy accepts only the isolated explicit uncached runtime', function () {
    DemoEnvironmentGuard::assertBootState(DemoEnvironmentGuard::ROOT, DemoEnvironmentGuard::ROOT, true, false, false);
    expect(true)->toBeTrue();
});

test('actual bootstrap rejects demo selection before ordinary environment fallback', function (bool $argument) {
    $command = [PHP_BINARY, 'artisan', 'demo:preflight', '--no-interaction'];
    if ($argument) {
        $command[] = '--env=demo';
    }
    $process = new Process($command, base_path(), ['APP_ENV' => $argument ? 'testing' : 'demo']);
    $process->run();
    expect($process->getExitCode())->not->toBe(0);
    expect($process->getOutput().$process->getErrorOutput())->toContain('separate runtime');
    expect($this->app->make('db')->connection()->getDatabaseName())->toBe('le_miting_test');
})->with([true, false]);

test('demo path checks reject shared writable locations', function (string $path) {
    expect(fn () => (new DemoEnvironmentGuard)->assertResolvedPath(base_path($path), DemoEnvironmentGuard::ROOT.'/'.$path))
        ->toThrow(RuntimeException::class);
})->with(['storage', 'bootstrap/cache', 'storage/framework/views', 'storage/app/public', 'public/storage', 'storage/logs/laravel.log']);

test('demo accepts only direct loopback web requests', function (array $changes, bool $allowed) {
    $server = array_replace(['REMOTE_ADDR' => '127.0.0.1', 'SERVER_NAME' => '127.0.0.1',
        'SERVER_PORT' => '8011', 'HTTP_HOST' => '127.0.0.1:8011'], $changes);
    if (! $allowed) {
        expect(fn () => (new DemoEnvironmentGuard)->assertWebRequest($server))->toThrow(RuntimeException::class);
    } else {
        (new DemoEnvironmentGuard)->assertWebRequest($server);
        expect(true)->toBeTrue();
    }
})->with([
    [[], true], [['REMOTE_ADDR' => '192.168.1.10'], false], [['SERVER_NAME' => '0.0.0.0'], false],
    [['HTTP_HOST' => 'fyp.test'], false], [['SERVER_PORT' => '8000'], false],
    [['HTTP_X_FORWARDED_FOR' => '127.0.0.1'], false], [['HTTP_FORWARDED' => 'for=127.0.0.1'], false],
]);

test('demo guard rejects unapproved commands including local administrator provisioning', function (string $command) {
    $event = new CommandStarting($command, new ArgvInput(['artisan', $command]), new NullOutput);
    expect(fn () => (new DemoEnvironmentGuard)->protectCommand($event))->toThrow(RuntimeException::class);
})->with(['migrate', 'migrate:install', 'migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback',
    'db:wipe', 'db:seed', 'schema:dump', 'queue:work', 'queue:listen', 'schedule:run',
    'admin:provision-local', 'tinker', 'config:cache', 'serve']);

test('demo stage one permits only preflight without a force flag', function () {
    $guard = new DemoEnvironmentGuard;
    $guard->protectCommand(new CommandStarting('demo:preflight', new ArgvInput(['artisan', 'demo:preflight']), new NullOutput));
    expect(fn () => $guard->protectCommand(new CommandStarting('demo:preflight',
        new ArgvInput(['artisan', 'demo:preflight', '--force']), new NullOutput)))->toThrow(RuntimeException::class);
});

test('existing guarded test connection remains installed and isolated', function () {
    MySqlTestDatabaseGuard::assertEnvironment($this->app);
    $connection = $this->app->make('db')->connection();
    MySqlTestDatabaseGuard::assertConnection($connection);
    expect($connection->getDatabaseName())->toBe('le_miting_test');
    expect($this->app->make('db.factory'))->toBeInstanceOf(GuardedConnectionFactory::class);
    expect($this->app->bound('demo.requested'))->toBeFalse();
});

test('demo factory checks each connection and reconnect before exposing its PDO', function () {
    $application = new Application(base_path());
    $application->instance('config', new Repository(['database' => ['default' => 'mysql', 'connections' => ['mysql' => demoDatabaseConfig()]]]));
    $environment = Mockery::mock(DemoEnvironmentGuard::class);
    $environment->shouldReceive('verify')->atLeast()->once()->with($application);
    $guard = Mockery::mock(DemoDatabaseGuard::class)->makePartial();
    $first = Mockery::mock(PDO::class);
    $second = Mockery::mock(PDO::class);
    $guard->shouldReceive('assertPdo')->once()->with($first);
    $guard->shouldReceive('assertPdo')->once()->with($second)->andThrow(new RuntimeException('Wrong server'));
    $connector = Mockery::mock(ConnectorInterface::class);
    $connector->shouldReceive('connect')->twice()->andReturn($first, $second);
    $application->instance('db.connector.mysql', $connector);
    $factory = new DemoGuardedConnectionFactory($application, $environment, $guard);
    $manager = new DatabaseManager($application, $factory);
    expect($manager->connection()->getPdo())->toBe($first);
    expect(fn () => $manager->reconnect())->toThrow(RuntimeException::class, 'no fallback');
});

test('demo factory rejects explicitly named alternative connections without connecting', function () {
    $application = new Application(base_path());
    $environment = Mockery::mock(DemoEnvironmentGuard::class);
    $environment->shouldReceive('verify')->once();
    $factory = new DemoGuardedConnectionFactory($application, $environment, new DemoDatabaseGuard);
    expect(fn () => $factory->make(demoDatabaseConfig(), 'other'))->toThrow(RuntimeException::class, 'alternate connection');
});
