<?php

namespace Tests\Feature;

use App\Models\GalleryPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GalleryTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        if ($existing = User::where('email', "$role@t.local")->first()) {
            return $existing;
        }

        $u = new User(['name' => $role, 'email' => "$role@t.local", 'password' => 'pw-123456789']);
        $u->role = $role;
        $u->save();

        return $u;
    }

    public function test_home_shows_photos_not_menu_cards(): void
    {
        Storage::fake('public');
        $cat = \App\Models\Category::create(['name' => 'Mains', 'slug' => 'mains']);
        $cat->items()->create(['name' => 'Chicken Adobo', 'base_price' => 16.5, 'is_available' => true]);
        GalleryPhoto::create(['path' => 'gallery/abc.webp', 'caption' => 'Crispy pata']);

        $this->get('/')->assertOk()
            ->assertSee('alt="Crispy pata"', false)
            ->assertDontSee('Chicken Adobo')
            ->assertDontSee('$16.50');
    }

    public function test_home_renders_with_no_photos(): void
    {
        $this->get('/')->assertOk()->assertSee('From our kitchen');
    }

    public function test_admin_uploads_are_reencoded_and_deletable(): void
    {
        Storage::fake('public');
        $admin = $this->user('admin');

        $this->actingAs($admin)->post('/admin/gallery', [
            'photos' => [UploadedFile::fake()->image('a.jpg', 2400, 1600), UploadedFile::fake()->image('b.png', 300, 300)],
            'caption' => 'Sinigang',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, GalleryPhoto::count());
        $photo = GalleryPhoto::first();
        $this->assertMatchesRegularExpression('#^gallery/[A-Za-z0-9]{40}\.webp$#', $photo->path);
        [$w] = getimagesizefromstring(Storage::disk('public')->get($photo->path));
        $this->assertLessThanOrEqual(1600, $w);

        $this->actingAs($admin)->delete("/admin/gallery/{$photo->id}")->assertRedirect();
        Storage::disk('public')->assertMissing($photo->path);
        $this->assertSame(1, GalleryPhoto::count());
    }

    public function test_non_images_are_rejected(): void
    {
        Storage::fake('public');
        $evil = UploadedFile::fake()->createWithContent('shell.php.jpg', "<?php system(\$_GET['c']); ?>");

        $this->actingAs($this->user('admin'))->post('/admin/gallery', ['photos' => [$evil]])->assertSessionHasErrors('photos');
        $this->assertSame(0, GalleryPhoto::count());
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_only_admins_can_manage_the_gallery(): void
    {
        $this->get('/admin/gallery')->assertRedirect('/login');
        $this->actingAs($this->user('staff'))->get('/admin/gallery')->assertForbidden();
        $this->actingAs($this->user('staff'))->post('/admin/gallery', [])->assertForbidden();
        $this->actingAs($this->user('admin'))->get('/admin/gallery')->assertOk();
    }
}
