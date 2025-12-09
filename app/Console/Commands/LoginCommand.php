<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;

class LoginCommand extends Command
{
    protected $signature = 'login {--email=dev@test.com : Email for the test user}';

    protected $description = 'Ensure a user exists and login via browser';

    public function handle(): int
    {
        $email = $this->option('email');

        if (! $this->shouldLogin()) {
            $this->fixLoginRequirements($email);
        }

        $url = config('app.url');

        $this->info("Logging in as: {$email}");

        $result = Process::path(base_path())
            ->run(['npx', 'playwright', 'test', '--', 'node', 'scripts/login.cjs', $url, $email, 'password']);

        if (! $result->successful()) {
            // Try direct node execution
            $result = Process::path(base_path())
                ->run(['node', 'scripts/login.cjs', $url, $email, 'password']);
        }

        $this->line($result->output());

        if ($result->failed()) {
            $this->error($result->errorOutput());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function shouldLogin(): bool
    {
        $email = $this->option('email');

        if (! User::where('email', $email)->exists()) {
            return false;
        }

        return true;
    }

    protected function fixLoginRequirements(string $email): void
    {
        if (! User::where('email', $email)->exists()) {
            $this->info("Creating user: {$email}");

            User::create([
                'name' => 'Dev User',
                'email' => $email,
                'password' => Hash::make('password'),
            ]);
        }
    }
}
