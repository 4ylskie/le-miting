<?php

namespace App\Console\Commands;

use App\Services\DemoDatabaseGuard;
use App\Services\DemoEnvironmentGuard;
use App\Services\DemoGuardedConnectionFactory;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Database\Connection;
use RuntimeException;

#[Signature('admin:provision-demo')]
#[Description('Interactively create the first administrator in the guarded isolated demo database')]
class ProvisionDemoAdmin extends ProvisionLocalAdmin
{
    protected function verifiedConnection(): Connection
    {
        if (PHP_SAPI !== 'cli' || ! $this->laravel->environment('demo')) {
            throw new RuntimeException('Demo provisioning requires the demo CLI environment.');
        }
        $this->laravel->make(DemoEnvironmentGuard::class)->verify($this->laravel);
        if (! $this->laravel->make('db.factory') instanceof DemoGuardedConnectionFactory) {
            throw new RuntimeException('Demo connection factory missing.');
        }
        $connection = $this->laravel->make('db')->connection('mysql');
        $guard = new DemoDatabaseGuard;
        $guard->assertConfiguration($connection->getConfig());
        $guard->assertPdo($connection->getPdo());

        return $connection;
    }

    protected function successMessage(): string
    {
        return 'One demo administrator created. No other accounts or restaurant records were changed.';
    }
}
