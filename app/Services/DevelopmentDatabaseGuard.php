<?php

namespace App\Services;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Application;
use PDO;
use RuntimeException;
use Tests\GuardedConnectionFactory;
use Tests\MySqlTestDatabaseGuard;
use Throwable;

final class DevelopmentDatabaseGuard
{
    public function __construct(private Application $app) {}

    public function protectCommand(CommandStarting $event): void
    {
        if (! in_array($event->command, ['migrate', 'migrate:install', 'migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback', 'db:wipe', 'db:seed', 'schema:dump'], true)) {
            return;
        }

        $name = $event->input->hasOption('database') ? $event->input->getOption('database') : null;
        if ($this->app->environment('testing')) {
            if (! $this->app->make('db.factory') instanceof GuardedConnectionFactory) {
                throw new RuntimeException('Database safety: testing schema commands require the guarded test bootstrap.');
            }
            MySqlTestDatabaseGuard::assertEnvironment($this->app);
            MySqlTestDatabaseGuard::assertConnection($this->app->make('db')->connection($name));

            return;
        }

        if ($this->app->environment('production')) {
            if (! in_array($event->command, ['migrate', 'migrate:install', 'db:seed'], true)) {
                throw new RuntimeException('Database safety: destructive production schema commands are disabled.');
            }

            $this->verifyProductionDatabase($name);

            return;
        }

        $this->verify($name);
        if ($event->input->hasOption('seed') && $event->input->getOption('seed')
            || $event->input->hasOption('seeder') && $event->input->getOption('seeder')) {
            throw new RuntimeException('Database safety: development seeding requires separate approval and is disabled.');
        }
        if (! in_array($event->command, ['migrate', 'migrate:install'], true)) {
            throw new RuntimeException('Database safety: development resets, rollbacks, seeders and schema dumps require separate approval and are disabled.');
        }
    }

    private function verifyProductionDatabase(?string $name): void
    {
        $name ??= $this->app['config']->get('database.default');
        $config = $this->app['config']->get('database.connections.'.$name, []);
        $protected = ['le_miting_dev', 'le_miting_demo', 'le_miting_test'];

        if (($config['driver'] ?? null) !== 'mysql'
            || in_array($config['database'] ?? null, $protected, true)) {
            throw new RuntimeException('Database safety: production commands require an unprotected MySQL database.');
        }

        try {
            $pdo = $this->app->make('db')->connection($name)->getPdo();
            $database = $pdo->query('SELECT DATABASE()')->fetchColumn();
        } catch (Throwable) {
            throw new RuntimeException('Database safety: production database identity could not be verified.');
        }

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql'
            || ! is_string($database) || $database === '' || in_array($database, $protected, true)) {
            throw new RuntimeException('Database safety: production commands require an unprotected MySQL database.');
        }
    }

    /** Read-only identity and privilege preflight; never runs migrations. */
    public function verify(?string $name = null): Connection
    {
        if (! $this->app->environment('local') || $this->app['config']->get('app.env') !== 'local'
            || $this->app->configurationIsCached()
            || $this->app->bound('config_loaded_from_cache') && $this->app->make('config_loaded_from_cache')) {
            throw new RuntimeException('Database safety: development commands require an uncached local environment.');
        }

        $name ??= $this->app['config']->get('database.default');
        $config = $this->app['config']->get('database.connections.'.$name, []);
        if (($config['driver'] ?? null) !== 'mysql' || ($config['host'] ?? null) !== '127.0.0.1'
            || (string) ($config['port'] ?? '') !== '3307' || ($config['database'] ?? null) !== 'le_miting_dev'
            || ($config['username'] ?? null) !== 'le_miting_dev' || empty($config['password'])
            || ($config['timezone'] ?? null) !== '+00:00') {
            throw new RuntimeException('Database safety: explicit approved development MySQL configuration is required.');
        }
        foreach (['url', 'unix_socket', 'read', 'write', 'direct', 'prefix'] as $key) {
            if (! empty($config[$key])) {
                throw new RuntimeException('Database safety: alternate endpoints and table prefixes are not approved.');
            }
        }
        if (! empty($config['options'][PDO::ATTR_PERSISTENT]) || isset($config['options'][PDO::MYSQL_ATTR_INIT_COMMAND])) {
            throw new RuntimeException('Database safety: persistent connections and SQL initialization options are not approved.');
        }

        try {
            $connection = $this->app->make('db')->connection($name);
            $pdo = $connection->getPdo();
            $identity = $pdo->query('SELECT VERSION() AS version, @@version_comment AS product, @@port AS port,
                @@datadir AS datadir, DATABASE() AS db, CURRENT_USER() AS account, CURRENT_ROLE() AS roles,
                @@global.mandatory_roles AS mandatory_roles, @@partial_revokes AS partial_revokes,
                @@session.time_zone AS timezone')->fetch(PDO::FETCH_ASSOC);
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql'
                || $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS) !== '127.0.0.1 via TCP/IP'
                || $identity['version'] !== '26.7.0' || $identity['product'] !== 'MySQL Community Server - GPL'
                || (int) $identity['port'] !== 3307 || $identity['db'] !== 'le_miting_dev'
                || $identity['account'] !== 'le_miting_dev@127.0.0.1' || $identity['timezone'] !== '+00:00'
                || $identity['roles'] !== 'NONE' || $identity['mandatory_roles'] !== ''
                || strtolower(rtrim(str_replace('\\', '/', $identity['datadir']), '/')) !== 'c:/programdata/mysql/mysql server 26.7/data') {
                throw new RuntimeException('Unapproved database identity.');
            }
            $scope = $identity['partial_revokes'] ? '`le_miting_dev`.*' : '`le\\_miting\\_dev`.*';
            $account = ' TO `le_miting_dev`@`127.0.0.1`';
            $expected = ['GRANT USAGE ON *.*'.$account,
                'GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, INDEX, ALTER ON '.$scope.$account];
            $actual = $pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
            sort($expected);
            sort($actual);
            if ($actual !== $expected) {
                throw new RuntimeException('Unapproved database privileges.');
            }

            return $connection;
        } catch (Throwable) {
            throw new RuntimeException('Database safety: authenticated development server, schema or privilege verification failed.');
        }
    }
}
