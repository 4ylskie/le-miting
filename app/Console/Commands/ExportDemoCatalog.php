<?php

namespace App\Console\Commands;

use App\Services\DemoCatalogTransfer;
use App\Services\DevelopmentDatabaseGuard;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

#[Signature('demo:catalog-export {--write : Save the verified catalog-only bundle to private storage}')]
#[Description('Inspect the approved development catalog using a read-only MySQL transaction')]
class ExportDemoCatalog extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DevelopmentDatabaseGuard $guard, DemoCatalogTransfer $transfer): int
    {
        try {
            if (PHP_SAPI !== 'cli' || strtolower(str_replace('\\', '/', realpath(base_path()))) !== 'c:/users/user/herd/fyp') {
                throw new RuntimeException('Catalog safety: export requires the approved development CLI runtime.');
            }
            $source = $guard->verify();
            $bundle = $transfer->export($source->getPdo(), storage_path('app/public'), config_path('reservation.php'));
            $transfer->assertApproved($bundle, config_path('reservation.php'));
            if ($this->option('write')) {
                $file = storage_path('app/private/demo-catalog.json');
                $handle = @fopen($file, 'x');
                if ($handle === false) {
                    throw new RuntimeException('Catalog safety: export already exists or cannot be created; no overwrite.');
                }
                try {
                    $json = json_encode($bundle, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                    if (fwrite($handle, $json) !== strlen($json) || ! fflush($handle)) {
                        throw new RuntimeException('Catalog safety: incomplete export; retain for review, do not transfer.');
                    }
                } finally {
                    fclose($handle);
                }
                if (hash_file('sha256', $file) !== DemoCatalogTransfer::APPROVED_SHA256) {
                    throw new RuntimeException('Catalog safety: export file checksum mismatch.');
                }
            }
            $this->line(json_encode(['source' => 'le_miting_dev@127.0.0.1 / le_miting_dev',
                'counts' => array_map('count', $bundle['rows']), 'sha256' => $transfer->fingerprint($bundle),
                'assets' => array_map(fn ($asset) => ['sha256' => $asset['sha256'], 'size' => strlen(base64_decode($asset['bytes'], true))], $bundle['assets']),
                'written' => (bool) $this->option('write')], JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error(str_starts_with($exception->getMessage(), 'Catalog safety:') ? $exception->getMessage() : 'Catalog safety: source verification failed; no success inferred.');

            return self::FAILURE;
        }
    }
}
