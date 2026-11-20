<?php

namespace Tests\Feature;

use App\Models\DiningTable;
use App\Models\Floor;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\FloorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VipAndSecondFloorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(string $role = 'admin'): User
    {
        $u = new User(['name' => $role, 'email' => "$role@t.local", 'password' => 'pw-123456789']);
        $u->role = $role;
        $u->save();

        return $u;
    }

    public function test_seed_creates_a_second_floor_with_a_vip_lounge(): void
    {
        $this->seed(FloorSeeder::class);

        $this->assertSame(['Indoor', 'Patio', '2nd Floor'], Floor::orderBy('sort_order')->pluck('name')->all());

        $second = Floor::where('name', '2nd Floor')->first();
        $vip = $second->tables()->where('is_vip', true)->pluck('label')->sort()->values()->all();
        $this->assertSame(['V1', 'V2', 'V3'], $vip);
        $this->assertSame(0, DiningTable::whereIn('floor_id', Floor::whereIn('name', ['Indoor', 'Patio'])->pluck('id'))->where('is_vip', true)->count());
    }

    public function test_public_page_exposes_vip_flag_and_copy(): void
    {
        $this->seed(FloorSeeder::class);

        $this->get('/reservations')->assertOk()
            ->assertSee('is_vip', false)
            ->assertSee(config('kusina.vip.title'));
    }

    public function test_vip_tables_are_booked_like_any_other_and_flagged_for_staff(): void
    {
        $this->seed(FloorSeeder::class);
        $v = DiningTable::where('label', 'V3')->first();

        $this->postJson('/reservations', [
            'table_id' => $v->id, 'date' => '2026-11-25', 'time' => '19:00', 'party_size' => 8,
            'customer_name' => 'Maria', 'customer_phone' => '780 555 0100', 'notes' => '40th birthday',
        ])->assertCreated();

        $r = Reservation::first();
        $this->get("/reservations/{$r->code}")->assertOk()->assertSee('VIP');
        $this->actingAs($this->admin('staff'))->get('/admin/reservations?date=2026-11-25')->assertOk()->assertSee('★ VIP', false)->assertSee('40th birthday');

        // Same double-booking protection applies upstairs.
        $this->postJson('/reservations', [
            'table_id' => $v->id, 'date' => '2026-11-25', 'time' => '19:30', 'party_size' => 8,
            'customer_name' => 'Other', 'customer_phone' => '780 555 0101',
        ])->assertStatus(422)->assertJsonValidationErrors('table_id');
    }

    public function test_availability_covers_second_floor_tables(): void
    {
        $this->seed(FloorSeeder::class);
        $v = DiningTable::where('label', 'V1')->first();   // 6 seats

        $res = $this->getJson('/reservations/availability?date=2026-11-25&time=19:00&party=8')->assertOk();
        $this->assertSame('small', $res->json("tables.{$v->id}"));
        $this->assertSame('available', $this->getJson('/reservations/availability?date=2026-11-25&time=19:00&party=4')->json("tables.{$v->id}"));
    }

    public function test_admin_can_mark_tables_vip_in_the_floor_editor(): void
    {
        $floor = Floor::create(['name' => '2nd Floor']);
        $admin = $this->admin();

        $res = $this->actingAs($admin)->putJson("/admin/floor/{$floor->id}/tables", ['tables' => [
            ['label' => 'V9', 'seats' => 6, 'shape' => 'round', 'x' => 10, 'y' => 10, 'w' => 100, 'h' => 100, 'is_active' => true, 'is_vip' => true],
            ['label' => 'T1', 'seats' => 2, 'shape' => 'rect', 'x' => 200, 'y' => 10, 'w' => 60, 'h' => 60, 'is_active' => true],
        ]])->assertOk();

        $this->assertTrue(DiningTable::where('label', 'V9')->first()->is_vip);
        $this->assertFalse(DiningTable::where('label', 'T1')->first()->is_vip);   // defaults to a normal table
        $this->assertTrue((bool) collect($res->json('tables'))->firstWhere('label', 'V9')['is_vip']);

        $this->actingAs($this->admin('staff'))->putJson("/admin/floor/{$floor->id}/tables", ['tables' => []])->assertForbidden();
    }
}
