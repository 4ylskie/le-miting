<?php

namespace Tests;

use Illuminate\Database\Connection;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use PDO;
use PDOException;
use RuntimeException;
use SensitiveParameter;
use Throwable;

final class MySqlTestDatabaseGuard
{
    public const string DATABASE = 'le_miting_test';

    public const string ACCOUNT = 'le_miting_test@127.0.0.1';

    /** @var list<string> */
    public const array PRIVILEGES = ['ALTER', 'CREATE', 'DELETE', 'DROP', 'INDEX', 'INSERT', 'REFERENCES', 'SELECT', 'UPDATE'];

    public static function prepare(Application $app): void
    {
        $app->afterBootstrapping(LoadConfiguration::class, fn (Application $app) => self::assertEnvironment($app));
        $app->afterBootstrapping(RegisterProviders::class, fn (Application $app) => self::enforce($app));
    }

    public static function enforce(Application $app): void
    {
        self::assertEnvironment($app);
        $name = $app['config']->get('database.default');
        self::assertConfiguration(is_string($name) ? $app['config']->get('database.connections.'.$name, []) : []);

        if ($app->resolved('db')) {
            throw new RuntimeException('Test database guard: database manager was resolved before the safety guard.');
        }

        $app->singleton('db.factory', fn (): GuardedConnectionFactory => new GuardedConnectionFactory($app));
        $app->make('db')->connection();
    }

    public static function assertEnvironment(Application $app): void
    {
        if (! $app->environment('testing') || $app['config']->get('app.env') !== 'testing') {
            throw new RuntimeException('Test database guard: application environment must be testing.');
        }

        if ($app->configurationIsCached() || $app->bound('config_loaded_from_cache') && $app->make('config_loaded_from_cache')) {
            throw new RuntimeException('Test database guard: cached configuration is not permitted.');
        }
    }

    /** @param array<string, mixed> $config */
    public static function assertConfiguration(#[SensitiveParameter] array $config): void
    {
        if (($config['driver'] ?? null) !== 'mysql'
            || ($config['host'] ?? null) !== '127.0.0.1'
            || (string) ($config['port'] ?? '') !== '3307'
            || ($config['database'] ?? null) !== self::DATABASE
            || ($config['username'] ?? null) !== 'le_miting_test'
            || ($config['timezone'] ?? null) !== '+00:00'
            || ! is_string($config['password'] ?? null)
            || $config['password'] === '') {
            throw new RuntimeException('Test database guard: explicit isolated MySQL test credentials and endpoint are required.');
        }

        foreach (['url', 'unix_socket', 'read', 'write', 'direct'] as $key) {
            if (! empty($config[$key])) {
                throw new RuntimeException('Test database guard: alternate database endpoints are not permitted.');
            }
        }

        if (! empty($config['options'][PDO::ATTR_PERSISTENT]) || isset($config['options'][PDO::MYSQL_ATTR_INIT_COMMAND])) {
            throw new RuntimeException('Test database guard: persistent connections and SQL initialization options are not permitted.');
        }
    }

    public static function assertConnection(#[SensitiveParameter] Connection $connection): PDO
    {
        self::assertConfiguration($connection->getConfig());

        try {
            $pdo = $connection->getPdo();
            if (! $pdo instanceof PDO || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql'
                || $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS) !== '127.0.0.1 via TCP/IP') {
                throw new RuntimeException('Unexpected PDO connection.');
            }

            $identity = $pdo->query('SELECT VERSION() AS version, @@version_comment AS product, @@port AS port,
                @@datadir AS datadir, DATABASE() AS db, CURRENT_USER() AS account, CURRENT_ROLE() AS roles,
                @@global.mandatory_roles AS mandatory_roles, @@partial_revokes AS partial_revokes,
                @@session.time_zone AS session_timezone')->fetch(PDO::FETCH_ASSOC);
            self::assertIdentity($identity);
            self::assertGrants($pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN), (bool) $identity['partial_revokes']);

            $denied = false;
            try {
                $pdo->exec('USE `le_miting_dev`');
            } catch (PDOException $exception) {
                $denied = ($exception->errorInfo[1] ?? null) === 1044;
            }

            if (! $denied || $pdo->query('SELECT DATABASE()')->fetchColumn() !== self::DATABASE) {
                throw new RuntimeException('Development schema isolation failed.');
            }

            return $pdo;
        } catch (Throwable) {
            throw new RuntimeException('Test database guard: authenticated server, account or privilege verification failed.');
        }
    }

    /** @param array<string, mixed> $identity */
    public static function assertIdentity(array $identity): void
    {
        $directory = strtolower(str_replace('\\', '/', (string) ($identity['datadir'] ?? '')));

        if (($identity['version'] ?? null) !== '26.7.0'
            || ($identity['product'] ?? null) !== 'MySQL Community Server - GPL'
            || (int) ($identity['port'] ?? 0) !== 3307
            || rtrim($directory, '/') !== 'c:/programdata/mysql/mysql server 26.7/data'
            || ($identity['db'] ?? null) !== self::DATABASE
            || ($identity['account'] ?? null) !== self::ACCOUNT
            || ($identity['roles'] ?? null) !== 'NONE'
            || ($identity['mandatory_roles'] ?? null) !== ''
            || ($identity['session_timezone'] ?? null) !== '+00:00'
            || ! in_array($identity['partial_revokes'] ?? null, [0, 1, '0', '1'], true)) {
            throw new RuntimeException('Test database guard: server identity or selected account/database is not approved.');
        }
    }

    /** @param list<string> $grants */
    public static function assertGrants(array $grants, bool $partialRevokes): void
    {
        $account = ' TO `le_miting_test`@`127.0.0.1`';
        $scope = $partialRevokes ? '`le_miting_test`.*' : '`le\\_miting\\_test`.*';
        $usageSeen = false;
        $schemaSeen = false;

        foreach ($grants as $grant) {
            if ($grant === 'GRANT USAGE ON *.*'.$account && ! $usageSeen) {
                $usageSeen = true;

                continue;
            }

            if (! str_starts_with($grant, 'GRANT ') || ! str_ends_with($grant, ' ON '.$scope.$account) || $schemaSeen) {
                throw new RuntimeException('Test database guard: unexpected privileges or roles are not permitted.');
            }

            $privileges = explode(', ', substr($grant, 6, -strlen(' ON '.$scope.$account)));
            sort($privileges);
            if ($privileges !== self::PRIVILEGES) {
                throw new RuntimeException('Test database guard: test-schema privileges are missing or excessive.');
            }

            $schemaSeen = true;
        }

        if (! $usageSeen || ! $schemaSeen) {
            throw new RuntimeException('Test database guard: required account grants are missing.');
        }
    }
}
