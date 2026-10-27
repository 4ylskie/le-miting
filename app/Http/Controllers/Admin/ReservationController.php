<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignReservationTableRequest;
use App\Http\Requests\Admin\CheckInReservationRequest;
use App\Http\Requests\Admin\UpdateReservationStatusRequest;
use App\Models\Reservation;
use App\Models\Table;
use App\Services\ReservationService;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function __construct(private ReservationService $reservations) {}

    /**
     * Allowable forward transitions for a reservation.
     *
     * @return array<string, array<int, string>>
     */
    private function transitions(): array
    {
        return $this->reservations->transitions();
    }

    public function index(Request $request)
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $reservations = Reservation::with('table')
            ->when($search, fn ($query) => $query->where('reservation_number', 'like', '%'.$search.'%'))
            ->when($status && in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'], true), fn ($query) => $query->where('status', $status))
            ->orderBy('reservation_date', 'desc')
            ->orderBy('reservation_time', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('admin.reservations.index', [
            'reservations' => $reservations,
            'search' => $search,
            'currentStatus' => $status,
        ]);
    }

    public function show(Reservation $reservation)
    {
        $reservation->load('table', 'reservationFoods.menuItem', 'payment');

        $candidates = Table::where('status', 'available')
            ->where('id', '!=', $reservation->table_id)
            ->where('capacity', '>=', $reservation->guest_count)
            ->get()
            ->filter(fn (Table $table) => $this->reservations->isTableSuitable($table, $reservation->guest_count));
        $availableTables = $this->reservations->availableTablesForInterval($candidates, $reservation->reservation_date_time, $reservation->id);

        return view('admin.reservations.show', [
            'reservation' => $reservation,
            'nextStatuses' => array_values(array_diff(
                $this->transitions()[$reservation->status],
                [$reservation->checked_in_at === null ? 'completed' : 'cancelled'],
            )),
            'availableTables' => $availableTables,
        ]);
    }

    public function updateStatus(UpdateReservationStatusRequest $request, Reservation $reservation)
    {
        $target = $request->validated('status');
        $error = $this->reservations->transition($reservation->id, $target, $request->validated('expected_status'));
        if ($error !== null) {
            return back()->with('error', $error);
        }

        return back()->with('status', 'Reservation '.$reservation->reservation_number.' marked as '.$target.'.');
    }

    public function checkIn(CheckInReservationRequest $request)
    {
        $reservation = $this->reservations->checkIn($request->validated('reservation_number'));

        return redirect()
            ->route('admin.reservations.show', $reservation)
            ->with('status', 'Reservation '.$reservation->reservation_number.' checked in. The reservation remains confirmed.');
    }

    public function updateTable(AssignReservationTableRequest $request, Reservation $reservation)
    {
        $error = $this->reservations->reassign(
            $reservation->id, (int) $request->validated('table_id'),
            (int) $request->validated('expected_table_id'), $request->validated('expected_status'),
        );
        if ($error !== null) {
            return back()->with('error', $error);
        }

        return back()->with('status', 'Table assignment saved for reservation '.$reservation->reservation_number.'.');
    }
}
