<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReservationAdminController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']])['date'] ?? now()->toDateString();

        $reservations = Reservation::with('table.floor')
            ->whereDate('starts_at', $date)
            ->orderBy('starts_at')
            ->get();

        return view('admin.reservations.index', compact('reservations', 'date'));
    }

    public function status(Request $request, Reservation $reservation, ReservationService $service)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['confirmed', 'seated', 'completed', 'cancelled', 'no_show'])],
        ]);

        $service->setStatus($reservation, $data['status']);

        return back();
    }
}
