<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Floor;
use App\Models\Order;
use App\Models\Reservation;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingAndTableFirstTest extends TestCase
{
    use RefreshDatabase;

    private DiningTable $table;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 18:00:00');
        $floor = Floor::create(['name' => 'Indoor', 'zones' => [['type' => 'kitchen', 'label' => 'Kitchen', 'x' => 22, 'y' => 17, 'w' => 400, 'h' => 150]]]);
        $this->table = $floor->tables()->create(['label' => 'A1', 'seats' => 4, 'x' => 0, 'y' => 0]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function reservation(array $over = []): Reservation
    {
        return Reservation::create($over + [
            'code' => Reservation::generateCode(), 'dining_table_id' => $this->table->id,
            'customer_name' => 'Maria', 'customer_phone' => '09171234567', 'party_size' => 2,
            'starts_at' => now()->subMinutes(5), 'ends_at' => now()->addMinutes(85), 'status' => 'confirmed',
        ]);
    }

    private function fillCart(int $qty = 1): void
    {
        $item = Category::firstOrCreate(['slug' => 'mains'], ['name' => 'Mains'])->items()->create(['name' => 'Adobo', 'base_price' => 20, 'is_available' => true]);
        $this->postJson('/cart', ['item_id' => $item->id, 'option_ids' => [], 'qty' => $qty])->assertOk();
    }

    private function dineIn(array $over = [])
    {
        return $this->post('/checkout', $over + [
            'customer_name' => 'Juan', 'customer_phone' => '780 555 0123', 'customer_email' => 'juan@example.com',
            'order_type' => 'dine_in', 'payment_method' => 'cash',
        ]);
    }

    public function test_dine_in_order_needs_a_reservation_code(): void
    {
        $this->fillCart();

        $this->dineIn()->assertSessionHasErrors('reservation_code');
        $this->dineIn(['reservation_code' => 'RSV-NOPE123'])->assertSessionHasErrors('reservation_code');
        $this->assertSame(0, Order::count());
    }

    public function test_reservation_must_be_active_and_inside_its_time_slot(): void
    {
        $this->fillCart();

        $cancelled = $this->reservation(['status' => 'cancelled']);
        $this->dineIn(['reservation_code' => $cancelled->code])->assertSessionHasErrors('reservation_code');

        $later = $this->reservation(['starts_at' => now()->addHours(3), 'ends_at' => now()->addHours(4)]);
        $this->dineIn(['reservation_code' => $later->code])->assertSessionHasErrors('reservation_code');

        $over = $this->reservation(['starts_at' => now()->subHours(3), 'ends_at' => now()->subHours(1)]);
        $this->dineIn(['reservation_code' => $over->code])->assertSessionHasErrors('reservation_code');

        $soon = $this->reservation(['starts_at' => now()->addMinutes(10), 'ends_at' => now()->addMinutes(100)]);
        $this->dineIn(['reservation_code' => strtolower($soon->code)])->assertRedirect();

        $this->assertSame(1, Order::count());
    }

    public function test_valid_reservation_sets_the_table_and_ignores_a_typed_one(): void
    {
        $this->fillCart();
        $r = $this->reservation();

        $this->dineIn(['reservation_code' => $r->code, 'table_number' => 'ZZ'])->assertRedirect();

        $o = Order::first();
        $this->assertSame('A1', $o->table_number);
        $this->assertSame($r->id, $o->reservation_id);
    }

    public function test_pickup_does_not_need_a_reservation(): void
    {
        $this->fillCart();

        $this->post('/checkout', [
            'customer_name' => 'J', 'customer_phone' => '780 555 0123', 'order_type' => 'pickup', 'payment_method' => 'counter',
        ])->assertRedirect();

        $this->assertNull(Order::first()->reservation_id);
    }

    public function test_status_reports_cooking_time_and_milestones(): void
    {
        $this->fillCart(3);
        $this->dineIn(['reservation_code' => $this->reservation()->code])->assertRedirect();
        $order = Order::first();

        $pending = $this->getJson("/track/{$order->code}/status")->assertOk();
        $this->assertSame(840, $pending->json('progress.total'));
        $this->assertSame(0, $pending->json('progress.elapsed'));
        $this->assertSame(['current', 'todo', 'todo', 'todo'], collect($pending->json('milestones'))->pluck('state')->all());

        app(OrderService::class)->transition($order, OrderStatus::Preparing);
        Carbon::setTestNow(now()->addMinutes(5));

        $cooking = $this->getJson("/track/{$order->code}/status")->assertOk();
        $this->assertSame(300, $cooking->json('progress.elapsed'));
        $this->assertSame(540, $cooking->json('progress.remaining'));
        $this->assertSame(['done', 'current', 'todo', 'todo'], collect($cooking->json('milestones'))->pluck('state')->all());
        $this->assertNotNull(collect($cooking->json('milestones'))->firstWhere('key', 'preparing')['at']);

        app(OrderService::class)->transition($order->fresh(), OrderStatus::Ready);
        $ready = $this->getJson("/track/{$order->code}/status")->assertOk();
        $this->assertSame(0, $ready->json('progress.remaining'));
        $this->assertSame(['done', 'done', 'current', 'todo'], collect($ready->json('milestones'))->pluck('state')->all());
    }

    public function test_track_page_shows_the_3d_kitchen_and_progress(): void
    {
        $this->fillCart();
        $this->dineIn(['reservation_code' => $this->reservation()->code])->assertRedirect();
        $order = Order::first();

        $this->get("/track/{$order->code}")->assertOk()
            ->assertSee('Live kitchen view')->assertSee('Cooking time')->assertSee('Order progress')
            ->assertSee('Table A1')->assertSee('kitchen', false);
    }

    public function test_reservation_page_links_to_ordering_while_active(): void
    {
        $r = $this->reservation();
        $this->get("/reservations/{$r->code}")->assertOk()->assertSee('Order food for this table');

        $done = $this->reservation(['starts_at' => now()->subHours(3), 'ends_at' => now()->subHours(1)]);
        $this->get("/reservations/{$done->code}")->assertOk()->assertDontSee('Order food for this table');
    }

    public function test_track_accepts_a_reservation_code_and_explains_unknown_codes(): void
    {
        $r = $this->reservation();

        $this->get('/track/'.strtolower($r->code))->assertRedirect(route('reservations.show', $r->code));

        $this->get('/track/KUS-NOPE1234')->assertNotFound()->assertSee('We couldn');
    }

    public function test_kitchen_feed_lists_only_fresh_ready_dine_in_orders_without_private_details(): void
    {
        $this->fillCart();
        $this->dineIn(['reservation_code' => $this->reservation()->code])->assertRedirect();
        $order = Order::first();

        $this->getJson('/kitchen/feed')->assertOk()->assertExactJson(['ready' => []]);

        app(OrderService::class)->transition($order, OrderStatus::Preparing);
        $this->getJson('/kitchen/feed')->assertExactJson(['ready' => []]);

        app(OrderService::class)->transition($order->fresh(), OrderStatus::Ready);
        $feed = $this->getJson('/kitchen/feed')->assertOk();
        $this->assertSame([['key' => 'o'.$order->id, 'table_id' => $this->table->id, 'age' => 0]], $feed->json('ready'));
        $this->assertStringNotContainsString($order->code, $feed->getContent());

        Carbon::setTestNow(now()->addMinutes(11));
        $this->getJson('/kitchen/feed')->assertExactJson(['ready' => []]);
    }

    public function test_pickup_orders_never_send_the_waiter(): void
    {
        $this->fillCart();
        $this->post('/checkout', ['customer_name' => 'J', 'customer_phone' => '780 555 0123', 'order_type' => 'pickup', 'payment_method' => 'counter'])->assertRedirect();
        $order = Order::first();
        app(OrderService::class)->transition($order, OrderStatus::Preparing);
        app(OrderService::class)->transition($order->fresh(), OrderStatus::Ready);

        $this->getJson('/kitchen/feed')->assertExactJson(['ready' => []]);
    }

    private function readyDineInOrder(): Order
    {
        $this->fillCart();
        $this->dineIn(['reservation_code' => $this->reservation()->code])->assertRedirect();
        $order = Order::first();
        app(OrderService::class)->transition($order, OrderStatus::Preparing);
        app(OrderService::class)->transition($order->fresh(), OrderStatus::Ready);

        return $order->fresh();
    }

    public function test_a_ready_dine_in_order_completes_itself_after_the_serving_window(): void
    {
        $order = $this->readyDineInOrder();

        Carbon::setTestNow(now()->addSeconds(60));
        $this->getJson("/track/{$order->code}/status")->assertOk()->assertJsonPath('status', 'ready');

        Carbon::setTestNow(now()->addSeconds(40));
        $this->getJson("/track/{$order->code}/status")->assertOk()->assertJsonPath('status', 'completed');
        $this->assertSame('completed', $order->fresh()->status->value);
        $this->getJson('/kitchen/feed')->assertExactJson(['ready' => []]);
    }

    public function test_the_tracking_page_marks_the_order_served_once_the_waiter_arrives(): void
    {
        $order = $this->readyDineInOrder();

        $this->postJson("/track/{$order->code}/served")->assertStatus(422);

        Carbon::setTestNow(now()->addSeconds(10));
        $this->postJson("/track/{$order->code}/served")->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('milestones.3.state', 'done');
    }

    public function test_served_only_applies_to_ready_dine_in_orders(): void
    {
        $this->fillCart();
        $this->post('/checkout', ['customer_name' => 'J', 'customer_phone' => '780 555 0123', 'order_type' => 'pickup', 'payment_method' => 'counter'])->assertRedirect();
        $pickup = Order::first();
        app(OrderService::class)->transition($pickup, OrderStatus::Preparing);
        app(OrderService::class)->transition($pickup->fresh(), OrderStatus::Ready);
        Carbon::setTestNow(now()->addMinutes(5));

        $this->postJson("/track/{$pickup->code}/served")->assertStatus(422);
        $this->getJson("/track/{$pickup->code}/status")->assertJsonPath('status', 'ready');
    }
}
