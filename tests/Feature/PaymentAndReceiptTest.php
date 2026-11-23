<?php

namespace Tests\Feature;

use App\Mail\OrderReceipt;
use App\Mail\ReservationReceipt;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Floor;
use App\Models\Order;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymentAndReceiptTest extends TestCase
{
    use RefreshDatabase;

    private DiningTable $table;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->table = Floor::create(['name' => 'Indoor'])->tables()->create(['label' => 'A1', 'seats' => 4, 'x' => 0, 'y' => 0]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function book(array $over = [])
    {
        return $this->postJson('/reservations', $over + [
            'table_id' => $this->table->id, 'date' => '2026-11-21', 'time' => '18:00', 'party_size' => 4,
            'customer_name' => 'Maria', 'customer_phone' => '09171234567', 'customer_email' => 'maria@example.com',
            'payment_method' => 'cash',
        ]);
    }

    private function wallet(array $over = []): array
    {
        return $over + ['payment_method' => 'ewallet', 'payment_detail' => 'Maya', 'account_number' => '0917 123 4567'];
    }

    private function card(array $over = []): array
    {
        return $over + ['payment_method' => 'card', 'payment_detail' => 'BDO', 'card_holder' => 'Maria Santos', 'card_number' => '4242 4242 4242 4242', 'card_expiry' => '12/30'];
    }

    public function test_ewallet_needs_an_account_number_and_card_needs_bank_and_details(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->book($this->wallet(['account_number' => '']))->assertStatus(422)->assertJsonValidationErrors('account_number');
        $this->book($this->wallet(['account_number' => 'abc']))->assertStatus(422)->assertJsonValidationErrors('account_number');
        $this->book($this->wallet(['payment_detail' => 'Venmo']))->assertStatus(422)->assertJsonValidationErrors('payment_detail');

        $this->book($this->card(['payment_detail' => '']))->assertStatus(422)->assertJsonValidationErrors('payment_detail');
        $this->book($this->card(['payment_detail' => 'Fake Bank']))->assertStatus(422)->assertJsonValidationErrors('payment_detail');
        $this->book($this->card(['card_holder' => '']))->assertStatus(422)->assertJsonValidationErrors('card_holder');
        $this->book($this->card(['card_number' => '1234']))->assertStatus(422)->assertJsonValidationErrors('card_number');
        $this->book($this->card(['card_expiry' => '13/30']))->assertStatus(422)->assertJsonValidationErrors('card_expiry');
        $this->book($this->card(['card_expiry' => '01/20']))->assertStatus(422)->assertJsonValidationErrors('card_expiry');
        $this->assertSame(0, Reservation::count());
    }

    public function test_only_the_last_four_digits_are_stored(): void
    {
        $this->book($this->card())->assertCreated();
        $r = Reservation::first();
        $this->assertSame('•••• 4242', $r->payment_account);
        $this->assertSame('BDO', $r->payment_detail);
        $this->assertStringNotContainsString('4242 4242', json_encode($r->getAttributes()));
        $this->get("/reservations/{$r->code}/receipt")->assertOk()->assertSee('•••• 4242')->assertDontSee('4242 4242');
    }

    public function test_reservation_requires_email_and_a_payment_method(): void
    {
        $this->book(['customer_email' => ''])->assertStatus(422)->assertJsonValidationErrors('customer_email');
        $this->book(['customer_email' => 'not-an-email'])->assertStatus(422)->assertJsonValidationErrors('customer_email');
        $this->book(['payment_method' => ''])->assertStatus(422)->assertJsonValidationErrors('payment_method');
        $this->book(['payment_method' => 'bitcoin'])->assertStatus(422)->assertJsonValidationErrors('payment_method');
        $this->assertSame(0, Reservation::count());
    }

    public function test_cash_reservation_is_unpaid_until_arrival_and_priced_with_gst(): void
    {
        $this->book()->assertCreated();
        $r = Reservation::first();

        $this->assertSame('cash', $r->payment_method);
        $this->assertSame('unpaid', $r->payment_status);
        $this->assertNull($r->payment_ref);
        $this->assertEquals(40.00, $r->subtotal);
        $this->assertEquals(2.00, $r->tax);
        $this->assertEquals(42.00, $r->total);
    }

    public function test_ewallet_and_card_are_approved_instantly_with_a_reference(): void
    {
        $this->book($this->wallet())->assertCreated();
        $r = Reservation::first();
        $this->assertSame('paid', $r->payment_status);
        $this->assertSame('Maya', $r->payment_detail);
        $this->assertStringStartsWith('SIM-', $r->payment_ref);
        $this->assertSame('E-Wallet (Maya)', $r->paymentLabel());

        $this->book($this->card(['time' => '20:00']))->assertCreated();
        $card = Reservation::where('payment_method', 'card')->first();
        $this->assertSame('paid', $card->payment_status);
        $this->assertSame('Bank (Credit/Debit Card Tap) (BDO)', $card->paymentLabel());
    }

    public function test_reservation_page_shows_the_table_and_payment(): void
    {
        $this->book($this->wallet(['payment_detail' => 'GCash']))->assertCreated();
        $r = Reservation::first();

        $this->get("/reservations/{$r->code}")->assertOk()
            ->assertSee('A1')->assertSee('Indoor')->assertSee('E-Wallet')->assertSee('Paid $42.00')
            ->assertSee('maria@example.com')->assertSee('print receipt', false);
    }

    public function test_printable_receipt_lists_table_and_totals(): void
    {
        $this->book()->assertCreated();
        $r = Reservation::first();

        $this->get("/reservations/{$r->code}/receipt")->assertOk()
            ->assertSee($r->code)->assertSee('A1')->assertSee('$40.00')->assertSee('$42.00')
            ->assertSee('DUE ON ARRIVAL')->assertSee('Print receipt')->assertSee("{$r->code}/receipt.pdf", false);
        $this->get('/reservations/RSV-NOPE123/receipt')->assertNotFound();
    }

    public function test_booked_table_cannot_be_reserved_and_is_free_again_after_cancelling(): void
    {
        $this->book()->assertCreated();
        $code = Reservation::first()->code;

        $this->assertSame('booked', $this->getJson('/reservations/availability?date=2026-11-21&time=18:30&party=2')->json("tables.{$this->table->id}"));
        $this->book(['time' => '18:30', 'customer_name' => 'Other'])->assertStatus(422)->assertJsonValidationErrors('table_id');
        $this->assertSame(1, Reservation::count());

        $this->post("/reservations/{$code}/cancel");
        $this->assertSame('available', $this->getJson('/reservations/availability?date=2026-11-21&time=18:30&party=2')->json("tables.{$this->table->id}"));
        $this->book(['time' => '18:30', 'customer_name' => 'Other'])->assertCreated();
    }

    public function test_reservation_receipt_is_emailed(): void
    {
        Mail::fake();
        $this->book()->assertCreated();

        Mail::assertSent(ReservationReceipt::class, fn ($m) => $m->hasTo('maria@example.com') && $m->reservation->table->label === 'A1');

        $html = (new ReservationReceipt(Reservation::first()))->render();
        $this->assertStringContainsString('A1', $html);
        $this->assertStringContainsString('$42.00', $html);
    }

    public function test_a_mail_failure_does_not_break_the_booking(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);

        $this->book()->assertCreated();
        $this->assertSame(1, Reservation::count());
    }

    private function cartWithItem(): void
    {
        $item = Category::firstOrCreate(['slug' => 'mains'], ['name' => 'Mains'])->items()->create(['name' => 'Adobo', 'base_price' => 20, 'is_available' => true]);
        $this->postJson('/cart', ['item_id' => $item->id, 'option_ids' => [], 'qty' => 1])->assertOk();
    }

    private function liveReservation(array $over = []): Reservation
    {
        return Reservation::create($over + [
            'code' => Reservation::generateCode(), 'dining_table_id' => $this->table->id,
            'customer_name' => 'Maria', 'customer_phone' => '09171234567', 'party_size' => 2,
            'starts_at' => now()->subMinutes(10), 'ends_at' => now()->addMinutes(80), 'status' => 'confirmed',
        ]);
    }

    private function dineIn(array $over = [])
    {
        return $this->post('/checkout', $over + [
            'customer_name' => 'Juan', 'customer_phone' => '780 555 0123', 'customer_email' => 'juan@example.com',
            'order_type' => 'dine_in', 'reservation_code' => $this->liveReservation()->code,
        ] + $this->card());
    }

    public function test_dine_in_order_needs_email_and_one_of_the_three_methods(): void
    {
        $this->cartWithItem();

        $this->dineIn(['customer_email' => ''])->assertSessionHasErrors('customer_email');
        $this->dineIn(['payment_method' => 'counter'])->assertSessionHasErrors('payment_method');
        $this->dineIn(['payment_method' => 'online'])->assertSessionHasErrors('payment_method');
        $this->assertSame(0, Order::count());
    }

    public function test_dine_in_card_order_is_paid_emailed_and_has_a_receipt(): void
    {
        Mail::fake();
        $this->cartWithItem();

        $this->dineIn()->assertRedirect();
        $o = Order::first();

        $this->assertSame('card', $o->payment_method);
        $this->assertSame('paid', $o->payment_status);
        $this->assertStringStartsWith('SIM-', $o->payment_ref);
        Mail::assertSent(OrderReceipt::class, fn ($m) => $m->hasTo('juan@example.com'));

        $this->get("/track/{$o->code}")->assertOk()->assertSee('Paid with Bank')->assertSee('print receipt', false);
        $this->get("/track/{$o->code}/receipt")->assertOk()->assertSee('Adobo')->assertSee('Table A1')->assertSee('PAID');
    }

    public function test_dine_in_cash_order_stays_unpaid_but_visible_to_staff(): void
    {
        $this->cartWithItem();

        $this->dineIn(['payment_method' => 'cash'])->assertRedirect();
        $o = Order::first();

        $this->assertSame('unpaid', $o->payment_status);
        $this->assertNull($o->payment_ref);
        $this->assertSame(1, Order::visibleToStaff()->count());
    }

    public function test_pickup_orders_keep_the_counter_flow(): void
    {
        $this->cartWithItem();

        $this->post('/checkout', [
            'customer_name' => 'J', 'customer_phone' => '780 555 0123', 'order_type' => 'pickup', 'payment_method' => 'counter',
        ])->assertRedirect();
        $this->assertSame('counter', Order::first()->payment_method);

        $this->cartWithItem();
        $this->post('/checkout', [
            'customer_name' => 'J', 'customer_phone' => '780 555 0123', 'order_type' => 'pickup', 'payment_method' => 'card',
        ])->assertSessionHasErrors('payment_method');
    }

    public function test_reservation_receipt_downloads_as_a_pdf(): void
    {
        $this->book($this->card())->assertCreated();
        $r = Reservation::first();

        $res = $this->get("/reservations/{$r->code}/receipt.pdf")->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringContainsString("attachment; filename=\"Receipt-{$r->code}.pdf\"", $res->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->get('/reservations/RSV-NOPE123/receipt.pdf')->assertNotFound();

        $this->get("/reservations/{$r->code}/receipt")->assertOk()->assertSee('Print receipt')->assertSee("{$r->code}/receipt.pdf", false)->assertDontSee('Download PDF');
    }

    public function test_order_receipt_downloads_as_a_pdf(): void
    {
        $this->cartWithItem();
        $this->dineIn()->assertRedirect();
        $o = Order::first();

        $res = $this->get("/track/{$o->code}/receipt.pdf")->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->get('/track/KUS-NOPE1234/receipt.pdf')->assertNotFound();
    }

    public function test_receipt_emails_carry_the_pdf(): void
    {
        Mail::fake();
        $this->book()->assertCreated();

        Mail::assertSent(ReservationReceipt::class, fn ($m) => count($m->attachments()) === 1);
    }
}
