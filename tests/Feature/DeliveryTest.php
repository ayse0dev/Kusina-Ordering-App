<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Models\Category;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use App\Services\Courier\CourierManager;
use App\Support\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use RefreshDatabase;

    private const ADDRESS = '12 Main St, Edson, AB T7E 1A1';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Cache::flush();

        $item = Category::create(['name' => 'M', 'slug' => 'm'])->items()->create(['name' => 'Dish', 'base_price' => 100, 'is_available' => true]);
        $this->postJson('/cart', ['item_id' => $item->id, 'option_ids' => [], 'qty' => 1])->assertOk();
    }

    private function useUber(): void
    {
        config([
            'kusina.delivery.provider' => 'uber',
            'kusina.delivery.uber' => ['customer_id' => 'cust_1', 'client_id' => 'id', 'client_secret' => 'sec', 'signing_key' => 'sign_key'],
            'kusina.delivery.pickup.address' => '1 Restaurant Rd, Edson, AB',
            'kusina.delivery.pickup.phone' => '780-555-0100',
        ]);
    }

    private function fakeUber(array $quote = null, array $delivery = null): void
    {
        Http::fake([
            'auth.uber.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            'api.uber.com/v1/customers/cust_1/delivery_quotes' => Http::response($quote ?? ['id' => 'dqt_1', 'fee' => 750, 'currency' => 'cad', 'duration' => 35, 'expires' => now()->addMinutes(15)->toIso8601String()]),
            'api.uber.com/v1/customers/cust_1/deliveries' => Http::response($delivery ?? ['id' => 'del_1', 'status' => 'pending', 'tracking_url' => 'https://track.uber.test/abc', 'courier' => null]),
            'api.uber.com/v1/customers/cust_1/deliveries/*/cancel' => Http::response(['id' => 'del_1', 'status' => 'canceled']),
        ]);
    }

    private function checkout(array $over = [])
    {
        return $this->post('/checkout', $over + [
            'customer_name' => 'Juan', 'customer_phone' => '780 555 0123', 'order_type' => 'delivery',
            'delivery_address' => self::ADDRESS, 'payment_method' => 'counter',
        ]);
    }

    private function staff(): User
    {
        if ($existing = User::where('email', 's@t.local')->first()) {
            return $existing;
        }

        $u = new User(['name' => 's', 'email' => 's@t.local', 'password' => 'pw-123456789']);
        $u->role = 'staff';
        $u->save();

        return $u;
    }

    private function advance(Order $o, int $times = 1): void
    {
        $staff = $this->staff();
        for ($i = 0; $i < $times; $i++) {
            $this->actingAs($staff)->postJson("/admin/orders/{$o->id}/advance")->assertOk();
        }
    }

    private function signedWebhook(string $provider, array $body, string $secret = 'sign_key', array $headers = [])
    {
        $raw = json_encode($body);
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_UBER_SIGNATURE' => hash_hmac('sha256', $raw, $secret)] + $headers;

        return $this->call('POST', "/webhooks/courier/{$provider}", [], [], [], $server, $raw);
    }

    public function test_manual_delivery_uses_flat_fee_and_follows_staff_status(): void
    {
        $this->checkout()->assertRedirect();

        $order = Order::first();
        $d = $order->delivery;
        $this->assertSame('manual', $d->provider);
        $this->assertSame('flat', $d->fee_source);
        $this->assertSame(DeliveryStatus::Pending, $d->status);
        $this->assertEquals(5, $order->delivery_fee);
        $this->assertEquals(110.25, $order->total);

        $this->advance($order, 2);
        $this->assertSame(DeliveryStatus::Requested, $d->fresh()->status);
        Http::assertNothingSent();

        $this->advance($order);
        $this->assertSame(DeliveryStatus::InTransit, $d->fresh()->status);
        $this->advance($order);
        $this->assertSame(DeliveryStatus::Delivered, $d->fresh()->status);
    }

    public function test_staff_can_assign_a_driver(): void
    {
        $this->checkout();
        $order = Order::first();
        $staff = $this->staff();

        $this->actingAs($staff)->postJson("/admin/orders/{$order->id}/courier/driver", [])->assertStatus(422);
        $this->actingAs($staff)->postJson("/admin/orders/{$order->id}/courier/driver", ['name' => 'Pedro', 'phone' => '780 555 0199'])->assertOk();

        $d = $order->delivery->fresh();
        $this->assertSame('Pedro', $d->courier_name);
        $this->assertSame(DeliveryStatus::PickingUp, $d->status);

        $this->getJson("/track/{$order->code}/status")->assertJsonPath('delivery.courier', 'Pedro');
    }

    public function test_live_courier_quote_sets_the_delivery_fee_server_side(): void
    {
        $this->useUber();
        $this->fakeUber();

        $this->postJson('/checkout/quote', ['order_type' => 'delivery', 'delivery_address' => self::ADDRESS])
            ->assertOk()
            ->assertJsonPath('delivery_fee', 7.5)
            ->assertJsonPath('delivery.eta', 35)
            ->assertJsonPath('delivery.source', 'courier');

        $this->checkout(['delivery_fee' => 0, 'delivery' => ['fee' => 0]])->assertRedirect();
        $order = Order::first();
        $this->assertEquals(7.5, $order->delivery_fee);
        $this->assertEquals(112.88, $order->total);
        $this->assertSame('courier', $order->delivery->fee_source);
        $this->assertSame('dqt_1', $order->delivery->quote_id);
    }

    public function test_quote_is_cached_per_address_so_the_courier_is_not_hammered(): void
    {
        $this->useUber();
        $this->fakeUber();

        foreach (range(1, 3) as $_) {
            $this->postJson('/checkout/quote', ['order_type' => 'delivery', 'delivery_address' => self::ADDRESS])->assertOk();
        }
        Http::assertSentCount(2);
    }

    public function test_courier_failure_falls_back_to_the_flat_fee(): void
    {
        $this->useUber();
        Http::fake([
            'auth.uber.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            'api.uber.com/*' => Http::response(['message' => 'address not serviceable'], 422),
        ]);

        $this->postJson('/checkout/quote', ['order_type' => 'delivery', 'delivery_address' => self::ADDRESS])
            ->assertOk()
            ->assertJsonPath('delivery_fee', 5)
            ->assertJsonPath('delivery.source', 'flat')
            ->assertJsonStructure(['delivery' => ['message']]);

        $this->checkout()->assertRedirect();
        $this->assertEquals(5, Order::first()->delivery_fee);
    }

    public function test_quote_in_the_wrong_currency_is_never_charged(): void
    {
        $this->useUber();
        $this->fakeUber(['id' => 'dqt_x', 'fee' => 99999, 'currency' => 'usd', 'duration' => 30]);

        $this->postJson('/checkout/quote', ['order_type' => 'delivery', 'delivery_address' => self::ADDRESS])
            ->assertJsonPath('delivery_fee', 5)->assertJsonPath('delivery.source', 'flat');
    }

    public function test_addresses_outside_the_delivery_zone_are_refused(): void
    {
        config([
            'kusina.delivery.geocoder.driver' => 'nominatim', 'kusina.delivery.max_km' => 10,
            'kusina.delivery.pickup.lat' => 53.58, 'kusina.delivery.pickup.lng' => -116.43,
        ]);
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([['lat' => '53.5461', 'lon' => '-113.4938']])]);

        $this->postJson('/checkout/quote', ['order_type' => 'delivery', 'delivery_address' => self::ADDRESS])
            ->assertOk()->assertJsonPath('delivery.error', fn ($m) => str_contains($m, 'outside our delivery area'));

        $this->checkout()->assertSessionHasErrors('delivery_address');
        $this->assertSame(0, Order::count());
    }

    public function test_addresses_inside_the_zone_are_accepted(): void
    {
        config([
            'kusina.delivery.geocoder.driver' => 'nominatim', 'kusina.delivery.max_km' => 10,
            'kusina.delivery.pickup.lat' => 53.58, 'kusina.delivery.pickup.lng' => -116.43,
        ]);
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([['lat' => '53.59', 'lon' => '-116.44']])]);

        $this->checkout()->assertRedirect();
        $this->assertEqualsWithDelta(53.59, (float) Order::first()->delivery->dropoff_lat, 0.001);
    }

    public function test_courier_is_requested_when_the_kitchen_starts_and_cancelled_with_the_order(): void
    {
        $this->useUber();
        $this->fakeUber();
        $this->checkout();
        $order = Order::first();

        $this->assertSame(DeliveryStatus::Pending, $order->delivery->status);
        $this->advance($order);

        $d = $order->delivery->fresh();
        $this->assertSame(DeliveryStatus::Requested, $d->status);
        $this->assertSame('del_1', $d->provider_delivery_id);
        $this->assertSame('https://track.uber.test/abc', $d->tracking_url);

        Http::assertSent(function (HttpRequest $r) use ($order) {
            return $r->url() === 'https://api.uber.com/v1/customers/cust_1/deliveries'
                && $r['external_id'] === $order->code
                && $r['dropoff_phone_number'] === '+17805550123'
                && $r['dropoff_address'] === self::ADDRESS
                && $r['manifest_items'][0]['name'] === 'Dish';
        });

        $this->actingAs($this->staff())->postJson("/admin/orders/{$order->id}/cancel")->assertOk();
        $this->assertSame(DeliveryStatus::Cancelled, $d->fresh()->status);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/deliveries/del_1/cancel'));
    }

    public function test_failed_dispatch_is_recorded_and_can_be_retried(): void
    {
        $this->useUber();
        Http::fake([
            'auth.uber.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            'api.uber.com/v1/customers/cust_1/delivery_quotes' => Http::response(['id' => 'dqt_1', 'fee' => 750, 'currency' => 'cad', 'duration' => 35]),
            'api.uber.com/v1/customers/cust_1/deliveries' => Http::sequence()
                ->push(['message' => 'no couriers available'], 503)
                ->push(['id' => 'del_2', 'status' => 'pending', 'tracking_url' => 'https://track.uber.test/xyz']),
        ]);

        $this->checkout();
        $order = Order::first();
        $this->advance($order);

        $d = $order->delivery->fresh();
        $this->assertSame(DeliveryStatus::Failed, $d->status);
        $this->assertStringContainsString('no couriers', $d->failure_reason);

        $this->actingAs($this->staff())->postJson("/admin/orders/{$order->id}/courier/dispatch")->assertOk();
        $this->assertSame(DeliveryStatus::Requested, $d->fresh()->status);
        $this->assertSame('del_2', $d->fresh()->provider_delivery_id);
        $this->assertNull($d->fresh()->failure_reason);
    }

    public function test_signed_webhooks_move_the_delivery_and_order_forward(): void
    {
        $this->useUber();
        $this->fakeUber();
        $this->checkout();
        $order = Order::first();
        $this->advance($order);

        $this->signedWebhook('uber', ['kind' => 'event.delivery_status', 'delivery_id' => 'del_1', 'status' => 'pickup',
            'data' => ['courier' => ['name' => 'Alex', 'phone_number' => '+17805550111'], 'tracking_url' => 'https://track.uber.test/abc']])->assertOk();
        $d = $order->delivery->fresh();
        $this->assertSame([DeliveryStatus::PickingUp, 'Alex'], [$d->status, $d->courier_name]);

        $this->signedWebhook('uber', ['kind' => 'event.delivery_status', 'delivery_id' => 'del_1', 'status' => 'pickup_complete'])->assertOk();
        $this->assertSame('out_for_delivery', $order->fresh()->status->value);

        $this->signedWebhook('uber', ['kind' => 'event.delivery_status', 'delivery_id' => 'del_1', 'status' => 'pickup'])->assertOk();
        $this->assertSame(DeliveryStatus::InTransit, $d->fresh()->status);

        $this->signedWebhook('uber', ['kind' => 'event.delivery_status', 'delivery_id' => 'del_1', 'status' => 'delivered'])->assertOk();
        $this->assertSame(DeliveryStatus::Delivered, $d->fresh()->status);
        $this->assertSame('completed', $order->fresh()->status->value);
        $this->assertNotNull($d->fresh()->delivered_at);

        $this->signedWebhook('uber', ['kind' => 'event.delivery_status', 'delivery_id' => 'del_1', 'status' => 'canceled'])->assertOk();
        $this->assertSame(DeliveryStatus::Delivered, $d->fresh()->status);
    }

    public function test_webhooks_with_bad_signatures_or_unknown_providers_are_rejected(): void
    {
        $this->useUber();
        $this->fakeUber();
        $this->checkout();
        $order = Order::first();
        $this->advance($order);
        $body = ['kind' => 'event.delivery_status', 'delivery_id' => 'del_1', 'status' => 'delivered'];

        $this->signedWebhook('uber', $body, 'wrong_key')->assertForbidden();
        $this->postJson('/webhooks/courier/uber', $body)->assertForbidden();
        $this->postJson('/webhooks/courier/manual', $body)->assertNotFound();
        $this->postJson('/webhooks/courier/doordash', $body)->assertNotFound();
        $this->assertSame(DeliveryStatus::Requested, $order->delivery->fresh()->status);

        $this->signedWebhook('uber', ['delivery_id' => 'unknown', 'status' => 'delivered'])->assertOk();
    }

    public function test_javascript_tracking_links_are_never_stored(): void
    {
        $this->useUber();
        $this->fakeUber(delivery: ['id' => 'del_1', 'status' => 'pending', 'tracking_url' => 'javascript:alert(1)']);
        $this->checkout();
        $order = Order::first();
        $this->advance($order);

        $this->assertNull($order->delivery->fresh()->tracking_url);

        $this->signedWebhook('uber', ['delivery_id' => 'del_1', 'status' => 'pickup', 'data' => ['tracking_url' => 'https://ok.example/t/1']])->assertOk();
        $this->assertSame('https://ok.example/t/1', $order->delivery->fresh()->tracking_url);
    }

    public function test_unconfigured_courier_falls_back_to_manual(): void
    {
        config(['kusina.delivery.provider' => 'uber']);
        $this->assertSame('uber', app(CourierManager::class)->configuredKey());
        $this->assertSame('manual', app(CourierManager::class)->active()->key());

        $this->checkout()->assertRedirect();
        $this->assertSame('manual', Order::first()->delivery->provider);
    }

    public function test_unknown_courier_setting_is_treated_as_manual(): void
    {
        config(['kusina.delivery.provider' => 'carrier-pigeon']);
        $this->assertSame('manual', app(CourierManager::class)->configuredKey());
    }

    private function useGrab(): void
    {
        config([
            'kusina.delivery.provider' => 'grab',
            'kusina.delivery.grab' => ['client_id' => 'g_id', 'client_secret' => 'g_sec', 'env' => 'staging', 'webhook_token' => 'hook_secret'],
            'kusina.delivery.pickup.lat' => 53.58, 'kusina.delivery.pickup.lng' => -116.43,
            'kusina.delivery.geocoder.driver' => 'nominatim', 'kusina.delivery.max_km' => null,
        ]);
    }

    public function test_grab_refuses_quotes_in_a_foreign_currency(): void
    {
        $this->useGrab();
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([['lat' => '53.59', 'lon' => '-116.44']]),
            'partner-api.grab.com/grabid/*' => Http::response(['access_token' => 'g_tok']),
            'partner-api.grab.com/grab-express-sandbox/v1/deliveries/quotes' => Http::response(['quotes' => [
                ['amount' => 12000, 'currency' => ['code' => 'PHP', 'exponent' => 2], 'estimatedTimeline' => []],
            ]]),
        ]);

        $this->postJson('/checkout/quote', ['order_type' => 'delivery', 'delivery_address' => self::ADDRESS])
            ->assertJsonPath('delivery_fee', 5)->assertJsonPath('delivery.source', 'flat');
    }

    public function test_grab_quote_booking_and_token_secured_webhook(): void
    {
        $this->useGrab();
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([['lat' => '53.59', 'lon' => '-116.44']]),
            'partner-api.grab.com/grabid/*' => Http::response(['access_token' => 'g_tok']),
            'partner-api.grab.com/grab-express-sandbox/v1/deliveries/quotes' => Http::response(['quotes' => [
                ['amount' => 850, 'currency' => ['code' => 'CAD', 'exponent' => 2], 'estimatedTimeline' => []],
            ]]),
            'partner-api.grab.com/grab-express-sandbox/v1/deliveries' => Http::response(['deliveryID' => 'IN-1', 'status' => 'ALLOCATING', 'trackURL' => 'https://grab.test/t/1']),
        ]);

        $this->postJson('/checkout/quote', ['order_type' => 'delivery', 'delivery_address' => self::ADDRESS])->assertJsonPath('delivery_fee', 8.5);
        $this->checkout()->assertRedirect();
        $order = Order::first();
        $this->advance($order);

        $d = $order->delivery->fresh();
        $this->assertSame(['grab', 'IN-1', DeliveryStatus::Requested], [$d->provider, $d->provider_delivery_id, $d->status]);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/deliveries') && $r['merchantOrderID'] === $order->code
            && $r['destination']['coordinates']['latitude'] === 53.59);

        $body = ['deliveryID' => 'IN-1', 'status' => 'IN_DELIVERY', 'driver' => ['name' => 'Rey', 'phone' => '+639170000000']];
        $this->postJson('/webhooks/courier/grab', $body)->assertForbidden();
        $this->postJson('/webhooks/courier/grab?token=nope', $body)->assertForbidden();
        $this->postJson('/webhooks/courier/grab?token=hook_secret', $body)->assertOk();
        $this->assertSame([DeliveryStatus::InTransit, 'Rey'], [$d->fresh()->status, $d->fresh()->courier_name]);
        $this->assertSame('out_for_delivery', $order->fresh()->status->value);
    }

    public function test_phone_numbers_are_normalised_for_couriers(): void
    {
        $this->assertSame('+17805550123', Phone::e164('(780) 555-0123'));
        $this->assertSame('+17805550123', Phone::e164('1-780-555-0123'));
        $this->assertSame('+639171234567', Phone::e164('+63 917 123 4567'));
    }

    public function test_pickup_and_counter_orders_have_no_delivery_record(): void
    {
        $this->post('/checkout', ['customer_name' => 'J', 'customer_phone' => '780 555 0123', 'order_type' => 'pickup', 'payment_method' => 'counter'])->assertRedirect();
        $this->assertSame(0, Delivery::count());
    }

    public function test_only_staff_can_use_courier_controls(): void
    {
        $this->checkout();
        $order = Order::first();

        $this->postJson("/admin/orders/{$order->id}/courier/dispatch")->assertUnauthorized();
        $this->get('/admin/deliveries')->assertRedirect('/login');
        $this->actingAs($this->staff())->get('/admin/deliveries')->assertOk()->assertSee('Deliveries');
    }

    public function test_tracking_page_hides_courier_details_after_delivery(): void
    {
        $this->useUber();
        $this->fakeUber();
        $this->checkout();
        $order = Order::first();
        $this->advance($order);
        $this->signedWebhook('uber', ['delivery_id' => 'del_1', 'status' => 'pickup', 'data' => ['courier' => ['name' => 'Alex', 'phone_number' => '+17805550111']]]);

        $live = $this->getJson("/track/{$order->code}/status")->json('delivery');
        $this->assertSame(['Alex', '+17805550111'], [$live['courier'], $live['phone']]);
        $this->assertNotNull($live['tracking']);

        $this->signedWebhook('uber', ['delivery_id' => 'del_1', 'status' => 'delivered']);
        $done = $this->getJson("/track/{$order->code}/status")->json('delivery');
        $this->assertNull($done['phone']);
        $this->assertNull($done['tracking']);
    }
}
