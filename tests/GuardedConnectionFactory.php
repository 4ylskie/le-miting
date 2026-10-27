<?php

namespace Tests;

use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Foundation\Application;
use RuntimeException;
use SensitiveParameter;

final class GuardedConnectionFactory extends ConnectionFactory
{
    public function __construct(private Application $application)
    {
        parent::__construct($application);
    }

    /** @param array<string, mixed> $config */
    public function make(#[SensitiveParameter] array $config, mixed $name = null): Connection
    {
        MySqlTestDatabaseGuard::assertEnvironment($this->application);
        MySqlTestDatabaseGuard::assertConfiguration($config);
        $connection = parent::make($config, $name);
        $verifiedPdo = MySqlTestDatabaseGuard::assertConnection($connection);

        $connection->beforeExecuting(function (string $query, array $bindings, Connection $connection) use (&$verifiedPdo): void {
            MySqlTestDatabaseGuard::assertEnvironment($this->application);

            if (preg_match('/^\s*use\b/i', $query)) {
                throw new RuntimeException('Test database guard: changing the selected schema is not permitted.');
            }

            $connection->reconnectIfMissingConnection();

            if ($connection->getRawPdo() !== $verifiedPdo || preg_match('/^\s*(?:create|alter|drop|truncate|rename)\b/i', $query)) {
                $verifiedPdo = MySqlTestDatabaseGuard::assertConnection($connection);
            }
        });

        return $connection;
    }
}
