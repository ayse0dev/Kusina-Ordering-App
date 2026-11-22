<?php

namespace Tests\Feature;

use App\Models\DiningTable;
use App\Models\Floor;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    private DiningTable $table;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 10:00:00');

        $floor = Floor::create(['name' => 'Indoor']);
        $this->table = $floor->tables()->create(['label' => 'A1', 'seats' => 4, 'x' => 0, 'y' => 0]);
        $this->date = '2026-11-21';
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function book(array $over = [])
    {
        return $this->postJson('/reservations', $over + [
            'table_id' => $this->table->id, 'date' => $this->date, 'time' => '18:00', 'party_size' => 4,
            'customer_name' => 'Maria', 'customer_phone' => '09171234567', 'customer_email' => 'maria@example.com', 'payment_method' => 'cash',
        ]);
    }

    public function test_can_book_a_table(): void
    {
        $this->book()->assertCreated();
        $r = Reservation::first();
        $this->assertSame('2026-11-21 18:00:00', $r->starts_at->toDateTimeString());
        $this->assertSame('19:30', $r->ends_at->format('H:i'));
    }

    public function test_overlapping_booking_is_rejected(): void
    {
        $this->book()->assertCreated();
        $this->book(['time' => '18:30', 'customer_name' => 'Other'])->assertStatus(422)->assertJsonValidationErrors('table_id');
        $this->book(['time' => '17:00'])->assertStatus(422);
        $this->assertSame(1, Reservation::count());
    }

    public function test_back_to_back_booking_is_allowed(): void
    {
        $this->book()->assertCreated();
        $this->book(['time' => '19:30'])->assertCreated();
    }

    public function test_cancelled_booking_frees_the_table(): void
    {
        $this->book()->assertCreated();
        Reservation::first()->update(['status' => 'cancelled']);
        $this->book()->assertCreated();
    }

    public function test_table_too_small_is_rejected(): void
    {
        $this->book(['party_size' => 6])->assertStatus(422);
    }

    public function test_opening_hours_past_and_window_are_enforced(): void
    {
        $this->book(['time' => '08:30'])->assertStatus(422);
        $this->book(['time' => '21:00'])->assertStatus(422);
        $this->book(['time' => '20:30'])->assertCreated();
        $this->book(['date' => '2026-11-20', 'time' => '09:00'])->assertStatus(422);
        $this->book(['date' => '2027-01-30', 'time' => '12:00'])->assertStatus(422);
        $this->book(['time' => '18:15'])->assertStatus(422);
    }

    public function test_availability_marks_booked_and_small_tables(): void
    {
        $this->book()->assertCreated();
        $big = $this->table->floor->tables()->create(['label' => 'B1', 'seats' => 8, 'x' => 100, 'y' => 0]);
        $small = $this->table->floor->tables()->create(['label' => 'S1', 'seats' => 2, 'x' => 200, 'y' => 0]);

        $res = $this->getJson("/reservations/availability?date={$this->date}&time=18:30&party=5")->assertOk();
        $this->assertSame('booked', $res->json("tables.{$this->table->id}"));
        $this->assertSame('available', $res->json("tables.{$big->id}"));
        $this->assertSame('small', $res->json("tables.{$small->id}"));
    }

    public function test_customer_can_cancel_with_code(): void
    {
        $this->book();
        $r = Reservation::first();
        $this->post("/reservations/{$r->code}/cancel")->assertRedirect();
        $this->assertSame('cancelled', $r->fresh()->status);
    }
}
