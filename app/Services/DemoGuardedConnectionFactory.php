<?php

namespace App\Services;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Foundation\Application;
use PDO;
use RuntimeException;
use SensitiveParameter;
use Throwable;

class DemoGuardedConnectionFactory extends ConnectionFactory
{
    public function __construct(private Application $application, private DemoEnvironmentGuard $environment, private DemoDatabaseGuard $guard, private ?DemoMigrationGuard $migration = null)
    {
        parent::__construct($application);
    }

    /** @param array<string, mixed> $config */
    public function make(#[SensitiveParameter] array $config, mixed $name = null): Connection
    {
        $this->environment->verify($this->application);
        if ($name !== 'mysql') {
            throw new RuntimeException('Demo safety: alternate connection names are forbidden.');
        }
        $this->guard->assertConfiguration($config);
        $connection = parent::make($config, $name);
        $connection->getPdo();
        $connection->beforeExecuting(function (string $query, array $bindings, Connection $connection): void {
            $this->environment->verify($this->application);
            $this->guard->assertConfiguration($connection->getConfig());
            if ($this->migration !== null) {
                $this->migration->assertQuery($query);
            } elseif (! preg_match('/^\\s*(SELECT|INSERT|UPDATE|DELETE)\\b/i', $query)) {
                throw new RuntimeException('Demo safety: schema and session SQL is not authorized in Stage 1.');
            }
            $connection->reconnectIfMissingConnection();
            $this->guard->assertPdo($connection->getPdo());
        });

        return $connection;
    }

    /** @param array<string, mixed> $config */
    protected function createPdoResolver(array $config): Closure
    {
        $resolve = parent::createPdoResolver($config);

        return function () use ($config, $resolve): PDO {
            $this->environment->verify($this->application);
            $this->guard->assertConfiguration($config);
            try {
                $pdo = $resolve();
                $this->guard->assertPdo($pdo);
                $this->migration?->assertEmptySchema($pdo);

                return $pdo;
            } catch (Throwable) {
                throw new RuntimeException('Demo safety: database connection unavailable or rejected; no fallback is permitted.');
            }
        };
    }
}
