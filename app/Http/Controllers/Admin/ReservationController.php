<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignReservationTableRequest;
use App\Http\Requests\Admin\UpdateReservationStatusRequest;
use App\Mail\FeedbackInvitationMail;
use App\Models\Reservation;
use App\Models\Table;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

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
        return [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => [],
        ];
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
        $reservation->load('table', 'reservationFoods.menuItem', 'payment', 'feedback');

        $availableTables = Table::where('status', 'available')
            ->where('id', '!=', $reservation->table_id)
            ->where('capacity', '>=', $reservation->guest_count)
            ->withCount(['reservations as active_count' => fn ($query) => $query->whereIn('status', ['pending', 'confirmed'])])
            ->get()
            ->filter(fn (Table $table) => $this->reservations->isTableAvailable($table, $reservation->reservation_date_time))
            ->values();

        return view('admin.reservations.show', [
            'reservation' => $reservation,
            'nextStatuses' => $this->transitions()[$reservation->status],
            'availableTables' => $availableTables,
        ]);
    }

    public function updateStatus(UpdateReservationStatusRequest $request, Reservation $reservation)
    {
        $target = $request->validated('status');
        $allowed = $this->transitions()[$reservation->status];

        if (! in_array($target, $allowed, true)) {
            return back()->with('error', sprintf(
                'Cannot move a reservation from %s to %s.',
                $reservation->status,
                $target,
            ));
        }

        $reservation->update(['status' => $target]);

        if ($target === 'completed') {
            $this->sendFeedbackInvitation($reservation);
        }

        return back()->with('status', 'Reservation '.$reservation->reservation_number.' marked as '.$target.'.');
    }

    private function sendFeedbackInvitation(Reservation $reservation): void
    {
        $feedbackUrl = URL::temporarySignedRoute(
            'feedback.create',
            now()->addHours(24),
            ['token' => 'reservation-'.$reservation->id],
        );

        try {
            Mail::to($reservation->customer_email)
                ->send(new FeedbackInvitationMail($reservation->customer_name, 'reservation '.$reservation->reservation_number, $feedbackUrl));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function updateTable(AssignReservationTableRequest $request, Reservation $reservation)
    {
        if (! in_array($reservation->status, ['pending', 'confirmed'], true)) {
            return back()->with('error', 'Only active reservations can be reassigned to another table.');
        }

        $table = Table::findOrFail($request->validated('table_id'));

        if ($table->status !== 'available') {
            throw ValidationException::withMessages([
                'table_id' => 'That table is not available. Choose an available table.',
            ]);
        }

        if ($table->capacity < $reservation->guest_count) {
            throw ValidationException::withMessages([
                'table_id' => 'That table only seats '.$table->capacity.' guests. Choose a table that can seat '.$reservation->guest_count.'.',
            ]);
        }

        if ($reservation->table_id === $table->id) {
            return back()->with('status', 'The reservation is already assigned to Table '.$table->table_number.'.');
        }

        if (! $this->reservations->isTableAvailable($table, $reservation->reservation_date_time)) {
            throw ValidationException::withMessages([
                'table_id' => 'That table is already reserved for this time. Choose another table.',
            ]);
        }

        $reservation->update(['table_id' => $table->id]);

        return back()->with('status', 'Reservation '.$reservation->reservation_number.' reassigned to Table '.$table->table_number.'.');
    }
}
