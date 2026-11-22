<?php

namespace Tests\Feature;

use App\Enums\OrderType;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\User;
use App\Models\Voucher;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VouchersAndPagesTest extends TestCase
{
    use RefreshDatabase;

    private function discount(array $over = []): Voucher
    {
        return Voucher::create($over + ['code' => 'SAVE10', 'kind' => 'discount', 'status' => 'active', 'type' => 'percent', 'value' => 10]);
    }

    private function gift(float $balance, string $code = 'GIFT-TEST-0001'): Voucher
    {
        return Voucher::create(['code' => $code, 'kind' => 'gift', 'status' => 'active', 'value' => $balance, 'balance' => $balance]);
    }

    private function quote(float $sub, array $codes = [], OrderType $t = OrderType::Pickup): array
    {
        return app(PricingService::class)->quote($sub, $t, $codes);
    }

    public function test_gst_is_added_and_delivery_fee_is_taxed(): void
    {
        $q = $this->quote(100);
        $this->assertSame([5.0, 105.0], [$q['tax'], $q['total']]);

        $d = $this->quote(100, [], OrderType::Delivery);
        $this->assertSame([5.25, 110.25], [$d['tax'], $d['total']]);
    }

    public function test_percent_and_fixed_discounts(): void
    {
        $this->discount();
        $q = $this->quote(100, ['save10']);
        $this->assertSame([10.0, 4.5, 94.5], [$q['discount'], $q['tax'], $q['total']]);

        $this->discount(['code' => 'FIVEOFF', 'type' => 'fixed', 'value' => 500]);
        $this->assertSame(100.0, $this->quote(100, ['FIVEOFF'])['discount']);
    }

    public function test_discount_code_rules(): void
    {
        $this->discount(['code' => 'MIN50', 'min_spend' => 50]);
        $this->discount(['code' => 'OLD', 'expires_at' => now()->subDay()]);
        $this->discount(['code' => 'USED', 'max_uses' => 1, 'uses' => 1]);
        $this->discount(['code' => 'OFF', 'status' => 'void']);
        $this->discount(['code' => 'A']);
        $this->discount(['code' => 'B']);

        $this->assertArrayHasKey('MIN50', $this->quote(20, ['MIN50'])['errors']);
        $this->assertSame(5.0, $this->quote(50, ['MIN50'])['discount']);
        foreach (['OLD', 'USED', 'OFF', 'NOPE'] as $bad) {
            $this->assertArrayHasKey($bad, $this->quote(100, [$bad])['errors'], $bad);
        }
        $this->assertArrayHasKey('B', $this->quote(100, ['A', 'B'])['errors']);
    }

    public function test_gift_voucher_partial_and_full_cover(): void
    {
        $this->gift(20);
        $q = $this->quote(100, ['GIFT-TEST-0001']);
        $this->assertSame([105.0, 20.0, 85.0], [$q['total'], $q['gift_applied'], $q['amount_due']]);

        $this->gift(500, 'GIFT-BIG-0002');
        $q = $this->quote(100, ['GIFT-BIG-0002']);
        $this->assertSame([105.0, 0.0], [$q['gift_applied'], $q['amount_due']]);
    }

    private function cartWithItem(float $price = 100): void
    {
        $item = Category::create(['name' => 'M', 'slug' => 'm'])->items()->create(['name' => 'Dish', 'base_price' => $price, 'is_available' => true]);
        $this->postJson('/cart', ['item_id' => $item->id, 'option_ids' => [], 'qty' => 1])->assertOk();
    }

    private function checkout(array $codes, array $over = [])
    {
        return $this->post('/checkout', $over + [
            'customer_name' => 'Juan', 'customer_phone' => '780 555 0123', 'order_type' => 'pickup',
            'payment_method' => 'counter', 'codes' => $codes,
        ]);
    }

    public function test_order_consumes_gift_balance_and_cancel_restores_it(): void
    {
        $g = $this->gift(40);
        $this->discount();
        $this->cartWithItem();
        $this->checkout(['SAVE10', 'GIFT-TEST-0001'])->assertRedirect();

        $o = Order::first();
        $this->assertEquals([10, 4.5, 94.5, 40, 54.5], [$o->discount, $o->tax, $o->total, $o->gift_applied, $o->amount_due]);
        $this->assertEquals(0, $g->fresh()->balance);
        $this->assertSame(1, Voucher::where('code', 'SAVE10')->first()->uses);
        $this->assertSame('unpaid', $o->payment_status);

        $staff = (function () { $u = new User(['name' => 's', 'email' => 's@t.local', 'password' => 'pw-123456789']); $u->role = 'staff'; $u->save(); return $u; })();
        $this->actingAs($staff)->postJson("/admin/orders/{$o->id}/cancel")->assertOk();

        $this->assertEquals(40, $g->fresh()->balance);
        $this->assertSame(0, Voucher::where('code', 'SAVE10')->first()->uses);
    }

    public function test_fully_covered_order_is_marked_paid(): void
    {
        $this->gift(500);
        $this->cartWithItem();
        $this->checkout(['GIFT-TEST-0001'])->assertRedirect();

        $o = Order::first();
        $this->assertSame('paid', $o->payment_status);
        $this->assertEquals(0, $o->amount_due);
        $this->assertEquals(395, Voucher::first()->balance);
    }

    public function test_invalid_code_blocks_checkout(): void
    {
        $this->cartWithItem();
        $this->checkout(['BOGUS'])->assertSessionHasErrors('codes');
        $this->assertSame(0, Order::count());
    }

    public function test_quote_endpoint(): void
    {
        $this->discount();
        $this->cartWithItem();
        $this->postJson('/checkout/quote', ['order_type' => 'pickup', 'codes' => ['SAVE10']])
            ->assertOk()->assertJsonPath('discount', 10)->assertJsonPath('total', 94.5);
    }

    private function purchaseData(): array
    {
        return ['amount' => 50, 'buyer_name' => 'Ana', 'buyer_email' => 'ana@example.com', 'recipient_name' => 'Ben'];
    }

    public function test_gift_purchase_activates_only_after_signed_webhook(): void
    {
        config(['kusina.stripe.secret' => 'sk_test_x', 'kusina.stripe.webhook_secret' => 'whsec_test']);
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_gift', 'url' => 'https://checkout.stripe.com/c/pay/cs_gift'])]);

        $this->post('/vouchers/purchase', $this->purchaseData())->assertRedirect('https://checkout.stripe.com/c/pay/cs_gift');

        $v = Voucher::first();
        $this->assertSame('pending', $v->status);
        $this->assertFalse($v->isUsable());
        $this->get("/vouchers/purchase/{$v->public_token}")->assertOk()->assertDontSee($v->code);

        $body = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_gift', 'payment_status' => 'paid', 'amount_total' => 5000]]]);
        $ts = time();
        $sig = hash_hmac('sha256', "$ts.$body", 'whsec_test');
        $this->call('POST', '/webhooks/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t=$ts,v1=$sig", 'CONTENT_TYPE' => 'application/json'], $body)->assertOk();

        $this->assertSame('active', $v->fresh()->status);
        $this->get("/vouchers/purchase/{$v->public_token}")->assertSee($v->code);
        $this->postJson('/vouchers/check', ['code' => $v->code])->assertJsonPath('valid', true);
    }

    public function test_gift_purchase_validates_amount_and_requires_stripe(): void
    {
        config(['kusina.stripe.secret' => null]);
        $this->post('/vouchers/purchase', ['amount' => 5] + $this->purchaseData())->assertSessionHasErrors('amount');
        $this->post('/vouchers/purchase', $this->purchaseData())->assertSessionHasErrors('amount');
        $this->assertSame(0, Voucher::where('status', '!=', 'void')->count());
    }

    public function test_pending_vouchers_cannot_be_checked_or_used(): void
    {
        $v = Voucher::create(['code' => 'GIFT-PEND-0001', 'kind' => 'gift', 'status' => 'pending', 'value' => 50, 'balance' => 50]);
        $this->postJson('/vouchers/check', ['code' => $v->code])->assertJsonPath('valid', false);
        $this->assertArrayHasKey('GIFT-PEND-0001', $this->quote(100, ['GIFT-PEND-0001'])['errors']);
    }

    public function test_only_admins_manage_vouchers(): void
    {
        $mk = function ($role) { $u = new User(['name' => $role, 'email' => "$role@t.local", 'password' => 'pw-123456789']); $u->role = $role; $u->save(); return $u; };

        $this->actingAs($mk('staff'))->get('/admin/vouchers')->assertForbidden();

        $admin = $mk('admin');
        $this->actingAs($admin)->post('/admin/vouchers/discount', ['code' => 'kain10', 'type' => 'percent', 'value' => 10])->assertSessionHasNoErrors();
        $this->assertSame('KAIN10', Voucher::first()->code);
        $this->actingAs($admin)->post('/admin/vouchers/discount', ['code' => 'KAIN10', 'type' => 'percent', 'value' => 10])->assertSessionHasErrors('code');
        $this->actingAs($admin)->post('/admin/vouchers/discount', ['code' => 'BIG', 'type' => 'percent', 'value' => 150])->assertSessionHasErrors('value');
        $this->actingAs($admin)->get('/admin/vouchers')->assertOk()->assertSee('KAIN10');
    }

    public function test_public_pages_render(): void
    {
        foreach (['/', '/menu', '/about', '/contact', '/vouchers', '/reservations'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/about')->assertSee('Edson');
    }

    public function test_contact_form_stores_message_and_ignores_bots(): void
    {
        $this->post('/contact', ['name' => 'Ana', 'email' => 'ana@example.com', 'message' => 'Do you cater events?'])->assertRedirect('/contact');
        $this->assertSame(1, ContactMessage::count());

        $this->post('/contact', ['name' => 'Bot', 'email' => 'b@example.com', 'message' => 'buy pills now', 'website' => 'http://spam'])->assertRedirect('/contact');
        $this->assertSame(1, ContactMessage::count());

        $this->post('/contact', ['name' => '', 'email' => 'nope', 'message' => 'x'])->assertSessionHasErrors(['name', 'email', 'message']);
    }

    public function test_staff_can_read_messages_but_guests_cannot(): void
    {
        $this->get('/admin/messages')->assertRedirect('/login');
        $u = new User(['name' => 's', 'email' => 's@t.local', 'password' => 'pw-123456789']);
        $u->role = 'staff';
        $u->save();
        ContactMessage::create(['name' => 'Ana', 'email' => 'a@e.com', 'message' => 'Hello there']);
        $this->actingAs($u)->get('/admin/messages')->assertOk()->assertSee('Hello there');
    }
}
