<?php

namespace App\Services;

use PDO;
use PDOException;
use RuntimeException;
use SensitiveParameter;
use Throwable;

class DemoDatabaseGuard
{
    public const DATABASE = 'le_miting_demo';

    public function __construct(private bool $forwardMigration = false) {}

    /** @param array<string, mixed> $config */
    public function assertConfiguration(#[SensitiveParameter] array $config): void
    {
        if (($config['driver'] ?? null) !== 'mysql' || ($config['host'] ?? null) !== '127.0.0.1'
            || (string) ($config['port'] ?? '') !== '3307' || ($config['database'] ?? null) !== self::DATABASE
            || ($config['username'] ?? null) !== self::DATABASE || empty($config['password'])
            || ($config['timezone'] ?? null) !== '+00:00') {
            throw new RuntimeException('Demo safety: explicit isolated MySQL configuration is required.');
        }
        foreach (['url', 'unix_socket', 'read', 'write', 'direct', 'prefix', 'options'] as $key) {
            if (! empty($config[$key])) {
                throw new RuntimeException('Demo safety: alternate endpoints and PDO options are not permitted.');
            }
        }
    }

    /** @param array<string, mixed> $identity */
    public function assertIdentity(array $identity): void
    {
        if (($identity['version'] ?? null) !== '26.7.0'
            || ($identity['product'] ?? null) !== 'MySQL Community Server - GPL'
            || (int) ($identity['port'] ?? 0) !== 3307
            || strtolower(rtrim(str_replace('\\', '/', $identity['datadir'] ?? ''), '/')) !== 'c:/programdata/mysql/mysql server 26.7/data'
            || ($identity['db'] ?? null) !== self::DATABASE
            || ($identity['account'] ?? null) !== 'le_miting_demo@127.0.0.1'
            || ($identity['timezone'] ?? null) !== '+00:00'
            || ($identity['roles'] ?? null) !== 'NONE' || ($identity['mandatory_roles'] ?? null) !== ''
            || ! in_array($identity['partial_revokes'] ?? null, [0, 1, '0', '1'], true)) {
            throw new RuntimeException('Demo safety: unapproved database identity.');
        }
    }

    /** @param list<string> $grants */
    public function assertGrants(array $grants, bool $partialRevokes): void
    {
        $scope = $partialRevokes ? '`le_miting_demo`.*' : '`le\\_miting\\_demo`.*';
        $account = ' TO `le_miting_demo`@`127.0.0.1`';
        $privileges = ! $this->forwardMigration ? 'SELECT, INSERT, UPDATE, DELETE'
            : 'SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, ALTER';
        $expected = ['GRANT USAGE ON *.*'.$account,
            'GRANT '.$privileges.' ON '.$scope.$account];
        sort($expected);
        sort($grants);
        if ($grants !== $expected) {
            throw new RuntimeException('Demo safety: missing or excessive database privileges.');
        }
    }

    /** Verify the PDO before returning it to Laravel, including every reconnect. */
    public function assertPdo(PDO $pdo): void
    {
        try {
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql'
                || $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS) !== '127.0.0.1 via TCP/IP') {
                throw new RuntimeException('Unexpected connection.');
            }
            $identity = $pdo->query('SELECT VERSION() AS version, @@version_comment AS product, @@port AS port,
                @@datadir AS datadir, DATABASE() AS db, CURRENT_USER() AS account, CURRENT_ROLE() AS roles,
                @@global.mandatory_roles AS mandatory_roles, @@partial_revokes AS partial_revokes,
                @@session.time_zone AS timezone')->fetch(PDO::FETCH_ASSOC);
            $this->assertIdentity($identity);
            $this->assertGrants($pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN), (bool) $identity['partial_revokes']);
            foreach (['le_miting_dev', 'le_miting_test'] as $schema) {
                $denied = false;
                try {
                    $pdo->exec('USE `'.$schema.'`');
                } catch (PDOException $exception) {
                    $denied = ($exception->errorInfo[1] ?? null) === 1044;
                }
                if (! $denied) {
                    throw new RuntimeException('Cross-schema access was not denied.');
                }
            }
            if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== self::DATABASE) {
                throw new RuntimeException('Selected schema changed.');
            }
        } catch (Throwable) {
            throw new RuntimeException('Demo safety: authenticated database verification failed; no fallback is permitted.');
        }
    }
}
