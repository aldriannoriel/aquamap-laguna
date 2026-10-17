<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Rules\AdministratorPasswordLength;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Throwable;

#[Signature('admin:create')]
#[Description('Create a new administrator interactively; never promotes existing users')]
class CreateAdministrator extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $db = DB::connection();
        $memoryTest = app()->environment('testing') && $db->getDriverName() === 'sqlite' && $db->getDatabaseName() === ':memory:';
        if (! $memoryTest && ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'aquamap_laguna')) {
            $this->error('Administrator setup requires the aquamap_laguna database.');

            return self::FAILURE;
        }
        if (! $memoryTest && $db->selectOne('SELECT DATABASE() AS name', [], false)->name !== 'aquamap_laguna') {
            $this->error('Connected database mismatch; setup stopped.');

            return self::FAILURE;
        }
        if (! $this->input->isInteractive()) {
            $this->error('Use an interactive local terminal; passwords cannot be supplied as arguments.');

            return self::FAILURE;
        }
        $name = trim((string) $this->ask('Administrator name'));
        $email = strtolower(trim((string) $this->ask('Administrator email')));
        $identity = Validator::make(compact('name', 'email'), ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:254']]);
        if ($identity->fails()) {
            foreach ($identity->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }
        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            $this->error('That email already belongs to an account. No account was changed or promoted.');

            return self::FAILURE;
        }
        // Hidden input must fail rather than fall back to visible password entry.
        $password = $this->secret('Password (12+ characters, mixed case, number and symbol)', false);
        $confirmation = $this->secret('Confirm password', false);
        $validation = Validator::make(['password' => $password, 'password_confirmation' => $confirmation], [
            'password' => ['required', 'string', 'max:1024', new AdministratorPasswordLength, Password::min(12)->mixedCase()->numbers()->symbols(), 'confirmed'],
        ]);
        if ($validation->fails()) {
            foreach ($validation->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }
        try {
            $administrator = new User;
            $administrator->name = $name;
            $administrator->email = $email;
            $administrator->password = $password;
            $administrator->is_admin = true;
            $administrator->save();
        } catch (Throwable) {
            $this->error('The new administrator could not be created. No existing account was promoted.');

            return self::FAILURE;
        }
        $this->info('Administrator created. Sign in at the administrator login page.');

        return self::SUCCESS;
    }
}
