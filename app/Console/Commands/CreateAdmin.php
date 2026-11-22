<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateAdmin extends Command
{
    protected $signature = 'kusina:admin {email : Login email} {--name=Kusina Admin : Display name} {--password= : Leave empty to generate one} {--role=admin : admin or staff}';

    protected $description = 'Create (or reset) an admin/staff account without seeding demo data';

    public function handle(): int
    {
        $v = Validator::make(
            ['email' => $this->argument('email'), 'role' => $this->option('role')],
            ['email' => ['required', 'email', 'max:150'], 'role' => ['in:admin,staff']],
        );
        if ($v->fails()) {
            $this->error($v->errors()->first());

            return self::FAILURE;
        }

        $password = $this->option('password') ?: Str::password(18, symbols: false);
        if (strlen($password) < 12) {
            $this->error('Use a password of at least 12 characters.');

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $this->argument('email')]);
        $user->name = $user->exists ? $user->name : $this->option('name');
        $user->password = $password;
        $user->role = $this->option('role');
        $user->email_verified_at ??= now();
        $user->save();

        $this->info(($user->wasRecentlyCreated ? 'Created' : 'Updated').": {$user->email} ({$user->role})");
        if (! $this->option('password')) {
            $this->warn("Generated password: {$password}   (store it safely; change it after first login)");
        }

        return self::SUCCESS;
    }
}
