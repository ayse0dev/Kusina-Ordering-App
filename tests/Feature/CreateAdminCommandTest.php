<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_an_admin_with_a_hashed_password(): void
    {
        $this->artisan('kusina:admin', ['email' => 'boss@example.com', '--password' => 'a-long-secret-pass'])->assertSuccessful();

        $u = User::where('email', 'boss@example.com')->first();
        $this->assertSame('admin', $u->role);
        $this->assertTrue(Hash::check('a-long-secret-pass', $u->password));
    }

    public function test_generates_a_password_and_is_idempotent(): void
    {
        $this->artisan('kusina:admin', ['email' => 'boss@example.com'])->expectsOutputToContain('Generated password')->assertSuccessful();
        $this->artisan('kusina:admin', ['email' => 'boss@example.com', '--role' => 'staff', '--password' => 'another-long-pass'])->assertSuccessful();

        $this->assertSame(1, User::count());
        $this->assertSame('staff', User::first()->role);
    }

    public function test_rejects_weak_passwords_and_bad_input(): void
    {
        $this->artisan('kusina:admin', ['email' => 'boss@example.com', '--password' => 'short'])->assertFailed();
        $this->artisan('kusina:admin', ['email' => 'not-an-email'])->assertFailed();
        $this->artisan('kusina:admin', ['email' => 'a@b.co', '--role' => 'god', '--password' => 'long-enough-pass'])->assertFailed();
        $this->assertSame(0, User::count());
    }
}
