<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Mail\ReservationCancellationMail;
use App\Mail\ReservationConfirmationMail;
use App\Models\MenuItem;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\ReceiptService;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ReservationController extends Controller
{
    public function __construct(
        private ReservationService $reservations,
        private ReceiptService $receipts,
    ) {}

    public function create()
    {
        return view('customer.reservations.create');
    }

    public function table()
    {
        return view('customer.reservations.table', [
            'type' => 'table_only',
            'availableTables' => null,
            'menuItems' => collect(),
        ]);
    }

    public function tableFood()
    {
        return view('customer.reservations.table', [
            'type' => 'table_and_food',
            'availableTables' => null,
            'menuItems' => $this->availableMenuItems(),
        ]);
    }

    public function availability(Request $request)
    {
        $validated = $request->validate([
            'reservation_type' => ['required', 'in:table_only,table_and_food'],
            'reservation_date' => ['required', 'date', 'after_or_equal:today'],
            'reservation_time' => ['required', 'date_format:H:i'],
            'guest_count' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $start = Carbon::parse($validated['reservation_date'].' '.$validated['reservation_time']);

        if ($start->lessThanOrEqualTo(now())) {
            return back()->withInput()
                ->withErrors(['reservation_time' => 'The reservation must be in the future.']);
        }

        $request->flash();

        $availableTables = collect($this->reservations->availableTables($start))
            ->filter(fn ($entry) => $entry['available'] && $entry['table']->status !== 'maintenance')
            ->map(fn ($entry) => $entry['table'])
            ->values();

        return view('customer.reservations.table', [
            'type' => $validated['reservation_type'],
            'availableTables' => $availableTables,
            'guestCount' => $validated['guest_count'],
            'menuItems' => $validated['reservation_type'] === 'table_and_food' ? $this->availableMenuItems() : collect(),
        ]);
    }

    public function store(StoreReservationRequest $request)
    {
        $data = $request->validated();

        if ($data['reservation_type'] === 'table_only') {
            $reservation = $this->reservations->createTableOnly($data);
            $reservation->load('table');

            $this->sendReservationConfirmation($reservation);

            return redirect()->route('reservation.confirmation', $reservation);
        }

        $reservation = $this->reservations->createTableAndFood($data);
        $reservation->load('table', 'reservationFoods');

        $payment = Payment::create([
            'reservation_id' => $reservation->id,
            'provider' => 'fiuu',
            'provider_reference' => 'PAY-'.date('Y').'-'.Str::upper(Str::random(10)),
            'status' => 'pending',
            'amount' => $reservation->total,
            'currency' => 'MYR',
        ]);

        return redirect()->route('payment.create', $payment);
    }

    public function confirmation(Reservation $reservation)
    {
        abort_unless($reservation->status === 'confirmed', 404);

        $reservation->load('table', 'reservationFoods.menuItem');

        return view('customer.reservations.confirmation', [
            'reservation' => $reservation,
        ]);
    }

    public function receipt(Request $request, Reservation $reservation)
    {
        if (! URL::hasValidSignature($request)) {
            return response()->view('errors.invalid-link', [], 403);
        }

        abort_unless($reservation->reservation_type === 'table_and_food', 404);

        abort_unless($reservation->payment && $reservation->payment->status === 'paid', 404);

        return $this->receipts->generateReservationReceipt($reservation)
            ->download('receipt-'.$reservation->reservation_number.'.pdf');
    }

    public function cancelForm(Reservation $reservation)
    {
        if ($reservation->status === 'completed' || $reservation->status === 'cancelled') {
            return view('customer.reservations.status', [
                'reservation' => $reservation,
            ]);
        }

        return view('customer.reservations.cancel', [
            'reservation' => $reservation,
        ]);
    }

    public function cancel(Request $request, Reservation $reservation)
    {
        $this->reservations->cancel($reservation);

        try {
            Mail::to($reservation->customer_email)
                ->send(new ReservationCancellationMail($reservation->loadMissing('table')));
        } catch (\Throwable $e) {
            report($e);
        }

        return view('customer.reservations.cancelled', [
            'reservation' => $reservation,
        ]);
    }

    private function sendReservationConfirmation(Reservation $reservation): void
    {
        try {
            Mail::to($reservation->customer_email)
                ->send(new ReservationConfirmationMail($reservation));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function availableMenuItems(): Collection
    {
        return MenuItem::with('category')
            ->where('is_available', true)
            ->orderBy('name')
            ->get();
    }
}
