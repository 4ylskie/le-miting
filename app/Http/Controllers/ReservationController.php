<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckReservationAvailabilityRequest;
use App\Http\Requests\StoreReservationRequest;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;
use App\Services\BusinessTime;
use App\Services\CartService;
use App\Services\ReceiptService;
use App\Services\ReservationMenuCandidates;
use App\Services\ReservationService;
use App\Services\ReservationSlots;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    public function __construct(
        private ReservationService $reservations,
        private ReceiptService $receipts,
        private CartService $carts,
        private ReservationSlots $slots,
        private ReservationMenuCandidates $menuCandidates,
        private BusinessTime $businessTime,
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

    public function availability(CheckReservationAvailabilityRequest $request)
    {
        $validated = $request->validated();

        $start = $this->businessTime->reservationStart($validated['reservation_date'], $validated['reservation_time']);

        $this->slots->assertValid($start);

        $old = $request->old();
        $input = array_merge($old, $request->input());
        foreach (['reservation_type', 'reservation_date', 'reservation_time', 'guest_count'] as $field) {
            if ((string) ($old[$field] ?? '') !== (string) $validated[$field]) {
                unset($input['table_id']);
                break;
            }
        }
        $request->session()->flashInput($input);

        $availability = $this->reservations->tableAvailability($start, $validated['guest_count']);

        return view('customer.reservations.table', [
            'type' => $validated['reservation_type'],
            'availableTables' => $availability['tables'],
            'hasSuitableTables' => $availability['has_suitable_tables'],
            'partyTooLarge' => $availability['party_too_large'],
            'guestCount' => $validated['guest_count'],
            'floorPlan' => $this->floorPlan(),
            'menuItems' => $validated['reservation_type'] === 'table_and_food' ? $this->availableMenuItems() : collect(),
        ]);
    }

    public function slots(Request $request): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$this->businessTime->today()]]);

        return response()->json(['slots' => $this->slots->forDate($data['date'])]);
    }

    public function review(StoreReservationRequest $request): View
    {
        $data = $request->validated();
        $start = $this->businessTime->reservationStart($data['reservation_date'], $data['reservation_time']);
        $this->slots->assertValid($start);
        $table = Table::findOrFail($data['table_id']);

        if ($table->status !== 'available' || ! $this->reservations->isTableSuitable($table, $data['guest_count'])
            || ! $this->reservations->isTableAvailable($table, $start)) {
            throw ValidationException::withMessages(['table_id' => 'This table is no longer suitable or available. Please check availability again.']);
        }

        $items = collect();
        if ($data['reservation_type'] === 'table_and_food') {
            $quantities = collect($data['food_items'])->keyBy('menu_item_id');
            $items = $this->availableMenuItems()->whereIn('id', $quantities->keys())->map(fn ($item) => [
                'id' => $item->id, 'name' => $item->name, 'unit_price' => (float) $item->price,
                'quantity' => (int) $quantities[$item->id]['quantity'],
                'line_total' => round($item->price * $quantities[$item->id]['quantity'], 2),
            ]);
            if ($items->count() !== $quantities->count()) {
                throw ValidationException::withMessages(['food_items' => 'One of the selected dishes is no longer available.']);
            }
        }

        $request->flash();

        return view('customer.reservations.review', [
            'data' => $data, 'table' => $table, 'items' => $items,
            'total' => round($items->sum('line_total'), 2),
            'end' => $start->copy()->addMinutes(ReservationService::DURATION_MINUTES),
        ]);
    }

    public function recoverReview(Request $request): RedirectResponse
    {
        $data = Arr::only($request->old(), ['reservation_type', 'reservation_date', 'reservation_time', 'guest_count']);
        $request->session()->keep(['errors', '_old_input']);

        return count($data) === 4
            ? redirect()->route('reservation.availability', $data)
            : redirect()->route('reservation.create');
    }

    public function store(StoreReservationRequest $request)
    {
        $data = $request->validated();

        if ($data['reservation_type'] === 'table_only') {
            $reservation = $this->reservations->createTableOnly($data);
            $reservation->load('table');

            return redirect()->to($this->confirmationUrl($reservation));
        }

        $data['food_items'] = $this->carts->replaceReservationItems($data['food_items']);
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
        if (! $this->reservations->canBeCancelled($reservation)) {
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
        $cancelledReservation = $this->reservations->cancel($reservation);

        if (! $cancelledReservation) {
            return response()->view('customer.reservations.status', [
                'reservation' => $reservation->fresh(),
            ], 422);
        }

        return view('customer.reservations.cancelled', [
            'reservation' => $cancelledReservation->loadMissing('payment'),
        ]);
    }

    private function availableMenuItems(): Collection
    {
        return $this->menuCandidates->query()->with('category')
            ->orderBy('name')
            ->get();
    }

    /** @return array{positions: array<int, array{zone: string, row: int, column: int}>, rows: array<string, int>} */
    private function floorPlan(): array
    {
        $positions = [];
        $rows = ['Veranda' => 1, 'Main dining' => 2];
        foreach (Table::all() as $table) {
            $position = config('reservation.floor_plan.'.$table->table_number) ?? [
                'zone' => 'Main dining', 'row' => 3 + intdiv($table->id - 1, 4), 'column' => 1 + ($table->id - 1) % 4,
            ];
            $positions[$table->id] = $position;
            $rows[$position['zone']] = max($rows[$position['zone']], $position['row']);
        }

        return ['positions' => $positions, 'rows' => $rows];
    }

    private function confirmationUrl(Reservation $reservation): string
    {
        return URL::temporarySignedRoute(
            'reservation.confirmation',
            now()->addHours(24),
            ['reservation' => $reservation],
        );
    }
}
