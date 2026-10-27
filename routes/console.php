<?php

use App\Services\DevelopmentDatabaseGuard;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('db:preflight', function (DevelopmentDatabaseGuard $guard): void {
    $guard->verify();
    $this->info('Verified: Oracle MySQL 26.7.0, 127.0.0.1:3307, le_miting_dev, dedicated account, UTC session. No schema changes performed.');
})->purpose('Verify the isolated development database without changing it');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
