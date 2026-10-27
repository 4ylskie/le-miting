<?php

namespace App\Console\Commands;

use App\Services\DemoCatalogTransfer;
use App\Services\DemoDatabaseGuard;
use App\Services\DemoEnvironmentGuard;
use App\Services\DemoGuardedConnectionFactory;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

#[Signature('demo:catalog-import {--apply : Apply the separately approved restoration once}')]
#[Description('Inspect or restore the pinned catalog bundle in the isolated demo runtime only')]
class ImportDemoCatalog extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DemoEnvironmentGuard $environment, DemoDatabaseGuard $database, DemoCatalogTransfer $transfer): int
    {
        try {
            if (PHP_SAPI !== 'cli') {
                throw new RuntimeException('Catalog safety: import requires CLI.');
            }
            $environment->verify($this->laravel);
            if (! $this->laravel->make('db.factory') instanceof DemoGuardedConnectionFactory) {
                throw new RuntimeException('Catalog safety: demo database factory missing.');
            }
            $target = $this->laravel->make('db')->connection('mysql');
            $database->assertConfiguration($target->getConfig());
            $database->assertPdo($target->getPdo());
            $transfer->assertEmptyTarget($target);
            $file = storage_path('app/private/demo-catalog.json');
            $environment->assertResolvedPath($file, DemoEnvironmentGuard::ROOT.'/storage/app/private/demo-catalog.json');
            if (! is_file($file) || filesize($file) > 30000000 || hash_file('sha256', $file) !== DemoCatalogTransfer::APPROVED_SHA256) {
                throw new RuntimeException('Catalog safety: missing, oversized or unapproved bundle.');
            }
            $bundle = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            $transfer->assertApproved($bundle, config_path('reservation.php'));
            if ($this->option('apply')) {
                $environment->verify($this->laravel);
                $database->assertPdo($target->getPdo());
                $transfer->restore($target, $bundle, storage_path('app/public'), storage_path('app/private'));
                $this->info('Catalog restored and compared byte-for-byte by field; no other records imported.');
            } else {
                $this->info('Catalog verified; inspection only. No records or assets written.');
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error(str_starts_with($exception->getMessage(), 'Catalog safety:') ? $exception->getMessage() : 'Catalog safety: verification or restoration failed. Retain attempt artifacts for review; no automatic retry.');

            return self::FAILURE;
        }
    }
}
