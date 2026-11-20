<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->createAdmin();
        $this->call([MenuSeeder::class, FloorSeeder::class]);
    }

    private function createAdmin(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@kusina.test');

        if (User::where('email', $email)->exists()) {
            return;
        }

        $password = env('ADMIN_PASSWORD') ?: Str::password(16, symbols: false);

        $user = new User(['name' => 'Kusina Admin', 'email' => $email, 'password' => $password]);
        $user->role = 'admin';
        $user->email_verified_at = now();
        $user->save();

        $this->command?->warn("Admin created: {$email} / {$password}  (change it after first login)");
    }
}
