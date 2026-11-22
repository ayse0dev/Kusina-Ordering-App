<?php

namespace App\Services;

use App\Events\ReservationsChanged;
use App\Models\DiningTable;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    private array $cfg;

    public function __construct()
    {
        $this->cfg = config('kusina.reservations');
    }

    public function slots(CarbonImmutable $date): array
    {
        $open = $date->setTimeFromTimeString($this->cfg['open']);
        $close = $date->setTimeFromTimeString($this->cfg['close']);
        $now = CarbonImmutable::now();
        $slots = [];

        for ($t = $open; $t->addMinutes($this->cfg['slot_minutes'])->lte($close); $t = $t->addMinutes($this->cfg['step_minutes'])) {
            $slots[] = ['time' => $t->format('H:i'), 'past' => $t->lte($now)];
        }

        return $slots;
    }

    public function tableStates(CarbonImmutable $start, int $party): array
    {
        $end = $start->addMinutes($this->cfg['slot_minutes']);
        $booked = Reservation::blocking()->overlapping($start, $end)->pluck('dining_table_id')->flip();

        return DiningTable::where('is_active', true)->get()->mapWithKeys(fn ($t) => [
            $t->id => $booked->has($t->id) ? 'booked' : ($t->seats < $party ? 'small' : 'available'),
        ])->all();
    }

    public function book(array $data): Reservation
    {
        $start = CarbonImmutable::parse($data['date'].' '.$data['time']);
        $end = $start->addMinutes($this->cfg['slot_minutes']);

        $this->assertBookable($start, $end);

        $reservation = DB::transaction(function () use ($data, $start, $end) {
            $table = DiningTable::where('id', $data['table_id'])->where('is_active', true)->lockForUpdate()->first();

            if (! $table) {
                throw ValidationException::withMessages(['table_id' => 'That table is not available.']);
            }
            if ($table->seats < (int) $data['party_size']) {
                throw ValidationException::withMessages(['table_id' => 'That table is too small for your party.']);
            }
            if (Reservation::where('dining_table_id', $table->id)->blocking()->overlapping($start, $end)->exists()) {
                throw ValidationException::withMessages(['table_id' => 'Sorry, that table was just booked. Please pick another.']);
            }

            return Reservation::create([
                'code' => Reservation::generateCode(),
                'dining_table_id' => $table->id,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'customer_email' => $data['customer_email'] ?? null,
                'party_size' => $data['party_size'],
                'starts_at' => $start,
                'ends_at' => $end,
                'status' => 'confirmed',
                'notes' => $data['notes'] ?? null,
            ] + SimulatedPayment::reservationPrice((int) $data['party_size'])
              + SimulatedPayment::settle($data['payment_method'] ?? 'cash', $data));
        });

        ReservationsChanged::dispatch();
        ReceiptMailer::reservation($reservation);

        return $reservation;
    }

    public function setStatus(Reservation $reservation, string $status): Reservation
    {
        $reservation->update(['status' => $status]);
        ReservationsChanged::dispatch();

        return $reservation;
    }

    private function assertBookable(CarbonImmutable $start, CarbonImmutable $end): void
    {
        $open = $start->setTimeFromTimeString($this->cfg['open']);
        $close = $start->setTimeFromTimeString($this->cfg['close']);
        $aligned = ($start->diffInMinutes($open, true)) % $this->cfg['step_minutes'] === 0;

        $error = match (true) {
            $start->lte(CarbonImmutable::now()) => 'Please choose a future time.',
            $start->gt(CarbonImmutable::now()->addDays($this->cfg['advance_days'])) => "Bookings open up to {$this->cfg['advance_days']} days ahead.",
            $start->lt($open) || $end->gt($close) || ! $aligned => "We take reservations between {$this->cfg['open']} and {$this->cfg['close']}.",
            default => null,
        };

        if ($error) {
            throw ValidationException::withMessages(['time' => $error]);
        }
    }
}
