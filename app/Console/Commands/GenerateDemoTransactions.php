<?php

namespace App\Console\Commands;

use App\Services\DemoTransactionGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

#[Signature('demo:transactions {--execute : Apply the reviewed manifest once; requires separate execution approval}')]
#[Description('Inspect the fixed demo02-v1 transaction manifest; read-only unless --execute is supplied')]
class GenerateDemoTransactions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DemoTransactionGenerator $generator): int
    {
        try {
            $manifest = $generator->run((bool) $this->option('execute'));
            $this->line(json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            $this->info($this->option('execute') ? 'Committed and verified. Do not run again.' : 'Inspection only; no records or attempt markers written.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception instanceof \RuntimeException && ! $exception instanceof \PDOException
                && ! $exception instanceof QueryException
                ? $exception->getMessage() : 'Generation verification failed. Stop and inspect safely; do not retry.');

            return self::FAILURE;
        }
    }
}
