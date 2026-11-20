<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffAndPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        if ($existing = User::where('email', "$role@test.local")->first()) {
            return $existing;
        }

        $u = new User(['name' => ucfirst($role), 'email' => "$role@test.local", 'password' => 'secret-pass-123']);
        $u->role = $role;
        $u->save();

        return $u;
    }

    private function order(array $over = []): Order
    {
        return Order::create($over + [
            'code' => Order::generateCode(), 'customer_name' => 'A', 'customer_phone' => '0917000000',
            'order_type' => 'pickup', 'status' => 'pending', 'payment_method' => 'counter',
            'subtotal' => 200, 'delivery_fee' => 0, 'total' => 200,
        ]);
    }

    // ---- access control ----
    public function test_admin_area_requires_staff_role(): void
    {
        $this->get('/admin/orders')->assertRedirect('/login');
        $this->actingAs($this->user('customer'))->get('/admin/orders')->assertForbidden();
        $this->actingAs($this->user('staff'))->get('/admin/orders')->assertOk();
    }

    public function test_menu_and_floor_management_is_admin_only(): void
    {
        $this->actingAs($this->user('staff'))->get('/admin/menu')->assertForbidden();
        $this->actingAs($this->user('staff'))->get('/admin/floor')->assertForbidden();
        $this->actingAs($this->user('admin'))->get('/admin/menu')->assertOk();
        $this->actingAs($this->user('admin'))->get('/admin/floor')->assertOk();
    }

    public function test_role_cannot_be_mass_assigned(): void
    {
        $u = new User(['name' => 'x', 'email' => 'x@test.local', 'password' => 'pw', 'role' => 'admin']);
        $u->save();
        $this->assertSame('customer', $u->fresh()->role);
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
    }

    // ---- status transitions ----
    public function test_pickup_flow_and_illegal_jumps(): void
    {
        $staff = $this->user('staff');
        $o = $this->order();

        $this->actingAs($staff)->postJson("/admin/orders/{$o->id}/advance")->assertOk();  // -> preparing
        $this->actingAs($staff)->postJson("/admin/orders/{$o->id}/advance")->assertOk();  // -> ready
        $this->actingAs($staff)->postJson("/admin/orders/{$o->id}/advance")->assertOk();  // -> completed (pickup skips delivery)
        $this->assertSame(OrderStatus::Completed, $o->fresh()->status);

        $this->actingAs($staff)->postJson("/admin/orders/{$o->id}/advance")->assertStatus(422);
        $this->actingAs($staff)->postJson("/admin/orders/{$o->id}/cancel")->assertStatus(422); // finished orders can't be cancelled
    }

    public function test_delivery_goes_through_out_for_delivery(): void
    {
        $o = $this->order(['order_type' => 'delivery', 'status' => 'ready']);
        $this->assertSame(OrderStatus::OutForDelivery, $o->status->next($o->order_type));
    }

    public function test_unpaid_online_order_is_hidden_and_cannot_advance(): void
    {
        $o = $this->order(['payment_method' => 'online']);
        $this->assertSame(0, Order::visibleToStaff()->count());
        $this->actingAs($this->user('staff'))->postJson("/admin/orders/{$o->id}/advance")->assertStatus(422);
    }

    // ---- Stripe webhook ----
    private function webhook(string $type, int $cents, string $secret = 'whsec_test', ?int $ts = null, string $status = 'paid')
    {
        $body = json_encode(['type' => $type, 'data' => ['object' => ['id' => 'cs_123', 'payment_status' => $status, 'amount_total' => $cents]]]);
        $ts ??= time();
        $sig = hash_hmac('sha256', "$ts.$body", $secret);

        return $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t=$ts,v1=$sig", 'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    private function onlineOrder(): Order
    {
        return $this->order(['payment_method' => 'online', 'stripe_session_id' => 'cs_123', 'amount_due' => 200]);
    }

    public function test_webhook_with_valid_signature_marks_order_paid(): void
    {
        config(['kusina.stripe.webhook_secret' => 'whsec_test']);
        $o = $this->onlineOrder();

        $this->webhook('checkout.session.completed', 20000)->assertOk();
        $this->assertSame('paid', $o->fresh()->payment_status);
        $this->assertSame(1, Order::visibleToStaff()->count());
    }

    public function test_webhook_rejects_bad_signature_stale_timestamp_and_missing_secret(): void
    {
        config(['kusina.stripe.webhook_secret' => 'whsec_test']);
        $o = $this->onlineOrder();

        $this->webhook('checkout.session.completed', 20000, 'wrong-secret')->assertForbidden();
        $this->webhook('checkout.session.completed', 20000, 'whsec_test', time() - 3600)->assertForbidden();
        $this->assertSame('unpaid', $o->fresh()->payment_status);

        config(['kusina.stripe.webhook_secret' => null]);
        $this->webhook('checkout.session.completed', 20000)->assertForbidden();
    }

    public function test_webhook_ignores_underpayment_and_unpaid_sessions(): void
    {
        config(['kusina.stripe.webhook_secret' => 'whsec_test']);
        $o = $this->onlineOrder();

        $this->webhook('checkout.session.completed', 100)->assertOk();
        $this->webhook('checkout.session.completed', 20000, status: 'unpaid')->assertOk();
        $this->assertSame('unpaid', $o->fresh()->payment_status);
    }

    public function test_expired_checkout_cancels_the_order(): void
    {
        config(['kusina.stripe.webhook_secret' => 'whsec_test']);
        $o = $this->onlineOrder();

        $this->webhook('checkout.session.expired', 0)->assertOk();
        $this->assertSame('cancelled', $o->fresh()->status->value);
        $this->assertSame('failed', $o->fresh()->payment_status);
    }
    // ---- uploads ----
    public function test_php_file_disguised_as_image_is_rejected(): void
    {
        Storage::fake('public');
        $evil = UploadedFile::fake()->createWithContent('shell.php.png', "<?php system(\$_GET['c']); ?>");

        $this->actingAs($this->user('admin'))->post('/admin/menu/items', [
            'category_id' => \App\Models\Category::create(['name' => 'C', 'slug' => 'c'])->id,
            'name' => 'X', 'base_price' => 10, 'image' => $evil,
        ])->assertSessionHasErrors('image');

        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_valid_image_is_reencoded_with_random_name(): void
    {
        Storage::fake('public');
        $img = UploadedFile::fake()->image('dish.png', 300, 200);

        $path = \App\Support\ImageSanitizer::store($img);

        $this->assertMatchesRegularExpression('#^menu/[A-Za-z0-9]{40}\.webp$#', $path);
        $this->assertStringStartsWith('RIFF', Storage::disk('public')->get($path)); // real WebP container
    }

    // ---- headers ----
    public function test_security_headers_present(): void
    {
        $this->get('/')->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy');
    }
}
