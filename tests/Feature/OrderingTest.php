<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderingTest extends TestCase
{
    use RefreshDatabase;

    private MenuItem $item;
    private array $sizes;
    private $egg;
    private $bacon;

    protected function setUp(): void
    {
        parent::setUp();

        $cat = Category::create(['name' => 'Mains', 'slug' => 'mains']);
        $this->item = $cat->items()->create(['name' => 'Pancit', 'base_price' => 100, 'is_available' => true]);
        $size = $this->item->optionGroups()->create(['name' => 'Size', 'type' => 'single', 'is_required' => true, 'max_select' => 1]);
        $this->sizes = [
            'solo' => $size->options()->create(['name' => 'Solo', 'price_delta' => 0]),
            'big' => $size->options()->create(['name' => 'Big', 'price_delta' => 50]),
        ];
        $addon = $this->item->optionGroups()->create(['name' => 'Add-ons', 'type' => 'multiple', 'is_required' => false, 'max_select' => 1]);
        $this->egg = $addon->options()->create(['name' => 'Egg', 'price_delta' => 20]);
        $this->bacon = $addon->options()->create(['name' => 'Bacon', 'price_delta' => 30]);
    }

    private function add(array $over = [])
    {
        return $this->postJson('/cart', $over + [
            'item_id' => $this->item->id, 'option_ids' => [$this->sizes['big']->id], 'qty' => 2,
        ]);
    }

    private function checkoutData(array $over = []): array
    {
        return $over + [
            'customer_name' => 'Juan', 'customer_phone' => '0917 123 4567',
            'order_type' => 'pickup', 'payment_method' => 'counter',
        ];
    }

    public function test_server_computes_price_from_options(): void
    {
        $this->add(['option_ids' => [$this->sizes['big']->id, $this->egg->id], 'price' => 1])
            ->assertOk()
            ->assertJsonPath('lines.0.unit_price', 170)   // 100 + 50 + 20; client "price" ignored
            ->assertJsonPath('subtotal', 340);
    }

    public function test_required_option_group_is_enforced(): void
    {
        $this->add(['option_ids' => []])->assertStatus(422)->assertJsonValidationErrors('options');
    }

    public function test_options_from_other_items_and_over_limits_are_rejected(): void
    {
        $other = Category::first()->items()->create(['name' => 'X', 'base_price' => 1]);
        $foreign = $other->optionGroups()->create(['name' => 'G', 'type' => 'single'])->options()->create(['name' => 'Foreign', 'price_delta' => -999]);

        $this->add(['option_ids' => [$this->sizes['solo']->id, $foreign->id]])->assertStatus(422);
        $this->add(['option_ids' => [$this->sizes['solo']->id, $this->sizes['big']->id]])->assertStatus(422); // single group
        $this->add(['option_ids' => [$this->sizes['solo']->id, $this->egg->id, $this->bacon->id]])->assertStatus(422); // max 1
    }

    public function test_unavailable_item_cannot_be_added(): void
    {
        $this->item->update(['is_available' => false]);
        $this->add()->assertStatus(422);
    }

    public function test_cart_update_and_remove(): void
    {
        $key = $this->add()->json('lines.0.key');
        $this->patchJson("/cart/{$key}", ['qty' => 5])->assertJsonPath('count', 5);
        $this->deleteJson("/cart/{$key}")->assertJsonPath('count', 0);
    }

    public function test_counter_order_snapshots_prices_and_clears_cart(): void
    {
        $this->add();
        $this->post('/checkout', $this->checkoutData())->assertRedirect();

        $order = Order::with('items.options')->first();
        $this->assertSame('pending', $order->status->value);
        $this->assertEquals(300, $order->subtotal);
        $this->assertEquals(15, $order->tax);          // 5% GST
        $this->assertEquals(315, $order->total);
        $this->assertSame('Big', $order->items[0]->options[0]->name);

        // Later menu changes must not alter the placed order.
        $this->item->update(['base_price' => 999]);
        $this->assertEquals(150, $order->fresh()->items[0]->unit_price);

        $this->getJson('/cart')->assertJsonPath('count', 0);
    }

    public function test_delivery_adds_fee_and_requires_address(): void
    {
        $this->add();
        $this->post('/checkout', $this->checkoutData(['order_type' => 'delivery']))->assertSessionHasErrors('delivery_address');

        $this->post('/checkout', $this->checkoutData(['order_type' => 'delivery', 'delivery_address' => '1 Rizal St']));
        $this->assertEquals(320.25, Order::first()->total);   // (300 + $5 delivery) + 5% GST
    }

    public function test_online_payment_without_provider_fails_cleanly(): void
    {
        config(['kusina.stripe.secret' => null]);
        $this->add();

        $this->post('/checkout', $this->checkoutData(['payment_method' => 'online']))->assertSessionHasErrors('payment_method');
        $this->assertSame('cancelled', Order::first()->status->value);
    }

    public function test_empty_cart_cannot_check_out(): void
    {
        $this->post('/checkout', $this->checkoutData())->assertSessionHasErrors('cart');
    }
}
