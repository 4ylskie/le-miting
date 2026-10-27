<?php

namespace App\Services;

use Illuminate\Console\Events\CommandStarting;
use PDO;
use RuntimeException;

class DemoMigrationGuard
{
    private bool $started = false;

    private bool $installed = false;

    private bool $emptyVerified = false;

    /** Fingerprint of the reviewed 18-file historical chain, not a generic migration permission. */
    private const MANIFEST = '3bd20731562581e69d1cb0536e8e1bfd5a6337e8d7f0b8caaf51058dea68b9b4';

    /** @param list<string> $arguments */
    public static function assertInvocation(string $sapi, string $environment, string $root, array $arguments): void
    {
        if ($sapi !== 'cli' || $environment !== 'demo'
            || strtolower(str_replace('\\', '/', $root)) !== strtolower(DemoEnvironmentGuard::ROOT)
            || $arguments !== ['migrate', '--env=demo', '--no-interaction']) {
            throw new RuntimeException('Demo migration safety: only the approved forward CLI invocation is permitted.');
        }
    }

    public function assertMigrationFiles(string $directory): void
    {
        $files = glob($directory.'/*.php');
        if ($files === false || count($files) !== 18 || glob(dirname($directory).'/schema/*')) {
            throw new RuntimeException('Demo migration safety: unexpected migration chain or schema dump.');
        }
        sort($files);
        $entries = array_map(fn (string $file): string => basename($file).':'.hash_file('sha256', $file), $files);
        if (hash('sha256', implode("\n", $entries)) !== self::MANIFEST) {
            throw new RuntimeException('Demo migration safety: migration fingerprint differs from the approved chain.');
        }
    }

    public function assertEmptySchema(PDO $pdo): void
    {
        if ($this->started) {
            return;
        }
        foreach (['TABLES' => 'TABLE_SCHEMA', 'ROUTINES' => 'ROUTINE_SCHEMA',
            'TRIGGERS' => 'TRIGGER_SCHEMA', 'EVENTS' => 'EVENT_SCHEMA'] as $catalog => $column) {
            $count = $pdo->query("SELECT COUNT(*) FROM information_schema.$catalog WHERE $column = DATABASE()")->fetchColumn();
            if ($count !== 0 && $count !== '0') {
                throw new RuntimeException('Demo migration safety: schema is not verified empty.');
            }
        }
        $this->emptyVerified = true;
    }

    /** The exclusive marker is deliberately retained on failure; no automatic retry is authorized. */
    public function protectCommand(CommandStarting $event, string $marker): void
    {
        if ($event->command === 'migrate' && ! $this->started && $this->emptyVerified) {
            $handle = @fopen($marker, 'x');
            if ($handle === false) {
                throw new RuntimeException('Demo migration safety: migration attempt already claimed or marker unavailable.');
            }
            fclose($handle);
            $this->started = true;

            return;
        }
        if ($event->command === 'migrate:install' && $this->started && ! $this->installed
            && ! $event->input->getParameterOption('--database', false)) {
            $this->installed = true;

            return;
        }
        throw new RuntimeException('Demo migration safety: unrelated, repeated or destructive commands are forbidden.');
    }

    public function assertQuery(string $query): void
    {
        if (preg_match('/^\s*SELECT\b/i', $query)) {
            return;
        }
        $tables = 'users|password_reset_tokens|sessions|cache|cache_locks|jobs|job_batches|failed_jobs|menu_categories|menu_items|tables|orders|order_items|payments|reservations|reservation_foods|feedback|migrations';
        if ($this->started && (
            preg_match('/^\s*(?:CREATE|ALTER) TABLE `?(?:'.$tables.')`?\s/i', $query)
            || preg_match('/^\s*DROP TABLE IF EXISTS `?feedback`?\s*;?$/i', $query)
            || preg_match('/^\s*INSERT INTO `?migrations`?\s*\(/i', $query)
            || preg_match('/^\s*UPDATE `?orders`? SET `?order_type`? = \? WHERE `?order_type`? = \?$/i', $query)
        )) {
            return;
        }
        throw new RuntimeException('Demo migration safety: SQL is outside the approved forward migration operations.');
    }
}
