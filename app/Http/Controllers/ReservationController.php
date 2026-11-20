<?php

namespace App\Http\Controllers;

use App\Models\Floor;
use App\Models\Reservation;
use App\Services\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function __construct(private ReservationService $service) {}

    public function index()
    {
        $floors = Floor::orderBy('sort_order')->with(['tables' => fn ($q) => $q->where('is_active', true)])->get()
            ->map(fn ($f) => [
                'id' => $f->id, 'name' => $f->name, 'width' => $f->width, 'height' => $f->height,
                'tables' => $f->tables->map(fn ($t) => $t->only(['id', 'label', 'seats', 'shape', 'x', 'y', 'w', 'h', 'is_vip']))->values(),
            ]);

        $cfg = config('kusina.reservations');

        return view('reservations.index', [
            'floors' => $floors,
            'config' => [
                'min' => now()->toDateString(),
                'max' => now()->addDays($cfg['advance_days'])->toDateString(),
                'maxParty' => $cfg['max_party'],
                'minutes' => $cfg['slot_minutes'],
                'open' => $cfg['open'],
                'close' => $cfg['close'],
                'vip' => config('kusina.vip'),
            ],
        ]);
    }

    public function availability(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['nullable', 'date_format:H:i'],
            'party' => ['nullable', 'integer', 'min:1', 'max:'.config('kusina.reservations.max_party')],
        ]);

        $date = CarbonImmutable::parse($data['date']);
        $out = ['slots' => $this->service->slots($date), 'tables' => (object) []];

        if (! empty($data['time'])) {
            $out['tables'] = $this->service->tableStates(
                CarbonImmutable::parse($data['date'].' '.$data['time']),
                (int) ($data['party'] ?? 1),
            );
        }

        return response()->json($out);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'table_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'party_size' => ['required', 'integer', 'min:1', 'max:'.config('kusina.reservations.max_party')],
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => ['required', 'string', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);

        $reservation = $this->service->book($data);

        return response()->json(['redirect' => route('reservations.show', $reservation->code)], 201);
    }

    public function show(string $code)
    {
        $reservation = Reservation::with('table.floor')->where('code', $code)->firstOrFail();

        return view('reservations.show', compact('reservation'));
    }

    public function cancel(string $code)
    {
        $reservation = Reservation::where('code', $code)->firstOrFail();

        if ($reservation->status === 'confirmed' && $reservation->starts_at->isFuture()) {
            $this->service->setStatus($reservation, 'cancelled');
        }

        return redirect()->route('reservations.show', $code);
    }
}
