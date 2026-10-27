<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DevelopmentDatabaseGuard;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Throwable;

#[Signature('admin:provision-local')]
#[Description('Interactively create the first administrator in the guarded local development database')]
class ProvisionLocalAdmin extends Command
{
    public function handle(): int
    {
        if (! $this->input->isInteractive() || ! $this->hasInteractiveTerminal()) {
            $this->error('Provisioning requires an interactive local terminal; redirected input is not accepted.');

            return self::FAILURE;
        }

        try {
            $connection = $this->verifiedConnection();
            if (User::on($connection->getName())->exists()) {
                $this->error('Provisioning refused: a user already exists. No account was changed.');

                return self::FAILURE;
            }

            $name = $this->ask('Administrator name');
            $email = $this->ask('Administrator email');
            $this->line('Use 12 or more characters, including uppercase, lowercase, numbers and symbols (maximum 72 bytes).');
            $password = $this->secret('Password', false);
            $confirmation = $this->secret('Confirm password', false);

            $validator = Validator::make([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $confirmation,
            ], [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255'],
                'password' => [
                    'required', 'string', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols(),
                    function (string $attribute, mixed $value, \Closure $fail): void {
                        if (is_string($value) && strlen($value) > 72) {
                            $fail('The password must not exceed 72 bytes.');
                        }
                    },
                ],
            ]);
            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $this->error($message);
                }

                return self::FAILURE;
            }
            unset($validator, $confirmation);

            $pdo = $connection->getPdo();
            $lockName = $connection->getDatabaseName().'.admin.provision-local';
            $acquire = $pdo->prepare('SELECT GET_LOCK(?, 0)');
            $acquire->execute([$lockName]);
            if ((int) $acquire->fetchColumn() !== 1) {
                $this->error('Provisioning refused: another provisioning attempt holds the lock.');

                return self::FAILURE;
            }

            try {
                $connection->transaction(function () use ($connection, $pdo, $lockName, $name, $email, $password): void {
                    $verified = $this->verifiedConnection();
                    if ($verified !== $connection || $connection->getPdo() !== $pdo) {
                        throw new RuntimeException('Provisioning connection changed.');
                    }
                    $ownership = $pdo->prepare('SELECT IS_USED_LOCK(?) = CONNECTION_ID()');
                    $ownership->execute([$lockName]);
                    if ((int) $ownership->fetchColumn() !== 1) {
                        throw new RuntimeException('Provisioning lock was lost.');
                    }
                    if (User::on($connection->getName())->exists()) {
                        throw new RuntimeException('A user already exists.');
                    }

                    User::on($connection->getName())->create([
                        'name' => $name,
                        'email' => $email,
                        'password' => $password,
                        'role' => 'admin',
                    ]);
                }, 1);
            } finally {
                $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                $release->execute([$lockName]);
            }
        } catch (Throwable) {
            $this->error('Provisioning stopped safely. Check the environment preflight and whether a user exists before retrying. No credential details are reported.');

            return self::FAILURE;
        } finally {
            unset($password, $confirmation, $validator);
        }

        $this->info($this->successMessage());

        return self::SUCCESS;
    }

    protected function verifiedConnection(): Connection
    {
        return $this->laravel->make(DevelopmentDatabaseGuard::class)->verify();
    }

    protected function hasInteractiveTerminal(): bool
    {
        $stream = $this->input instanceof StreamableInputInterface ? $this->input->getStream() : null;

        return defined('STDIN') && stream_isatty($stream ?? STDIN);
    }

    protected function successMessage(): string
    {
        return 'One local administrator created. No other accounts or restaurant records were changed.';
    }
}
