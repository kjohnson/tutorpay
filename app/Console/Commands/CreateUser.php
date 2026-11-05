<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('user:create {email : The email address used to log in} {--name= : The display name (defaults to the part of the email before the @)}')]
#[Description('Create a login, or set a new password for an existing one')]
class CreateUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->error('Please provide a valid email address.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user !== null && ! $this->confirm("A login for {$email} already exists. Set a new password for it?")) {
            return self::SUCCESS;
        }

        $password = $this->secret('Password (at least 8 characters)');
        $passwordErrors = Validator::make(['password' => $password], ['password' => ['required', Password::min(8)]])->errors();

        if ($passwordErrors->isNotEmpty()) {
            $this->error($passwordErrors->first('password'));

            return self::FAILURE;
        }

        if ($password !== $this->secret('Confirm password')) {
            $this->error('The passwords do not match.');

            return self::FAILURE;
        }

        if ($user !== null) {
            $user->update(['password' => $password]);
            $this->info("Password updated for {$email}.");

            return self::SUCCESS;
        }

        User::create([
            'name' => $this->option('name') ?: strstr($email, '@', true),
            'email' => $email,
            'password' => $password,
        ]);

        $this->info("Login created for {$email}.");

        return self::SUCCESS;
    }
}
