<?php

namespace App\Console\Commands;

use App\Services\DemoDatabaseGuard;
use App\Services\DemoEnvironmentGuard;
use App\Services\DemoGuardedConnectionFactory;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

#[Signature('demo:preflight')]
#[Description('Read-only verification of the isolated demo runtime and database')]
class DemoPreflight extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DemoEnvironmentGuard $environment, DemoDatabaseGuard $database): int
    {
        try {
            $environment->verify($this->laravel);
            if (! $this->laravel->make('db.factory') instanceof DemoGuardedConnectionFactory) {
                throw new RuntimeException('Demo connection factory missing.');
            }
            $database->assertPdo($this->laravel->make('db')->connection('mysql')->getPdo());
        } catch (Throwable) {
            $this->error('Demo preflight failed: runtime or database is not provisioned or isolated. No fallback or writes performed.');

            return self::FAILURE;
        }
        $this->info('Verified demo runtime: Oracle MySQL 26.7.0, 127.0.0.1:3307, le_miting_demo, dedicated account, UTC and isolated effects. No writes performed.');

        return self::SUCCESS;
    }
}
