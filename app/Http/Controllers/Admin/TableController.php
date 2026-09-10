<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTableRequest;
use App\Http\Requests\Admin\UpdateTableRequest;
use App\Models\Reservation;
use App\Models\Table;
use Illuminate\Validation\ValidationException;

class TableController extends Controller
{
    public function index()
    {
        $tables = Table::orderBy('table_number')->get();

        $upcomingByTable = Reservation::whereIn('status', ['pending', 'confirmed'])
            ->get()
            ->filter(fn (Reservation $reservation) => $reservation->reservation_date_time->greaterThanOrEqualTo(now()))
            ->groupBy('table_id')
            ->map->count();

        $tables->each(function (Table $table) use ($upcomingByTable): void {
            $table->setAttribute('upcoming_count', $upcomingByTable->get($table->id, 0));
        });

        return view('admin.tables.index', [
            'tables' => $tables,
        ]);
    }

    public function create()
    {
        return view('admin.tables.form', [
            'table' => null,
        ]);
    }

    public function store(StoreTableRequest $request)
    {
        Table::create($request->validated());

        return redirect()->route('admin.tables.index')
            ->with('status', 'Table '.$request->validated('table_number').' added.');
    }

    public function edit(Table $table)
    {
        return view('admin.tables.form', [
            'table' => $table,
        ]);
    }

    public function update(UpdateTableRequest $request, Table $table)
    {
        $data = $request->validated();

        if ($data['status'] === 'maintenance' && $table->status !== 'maintenance') {
            $this->assertNoUpcomingConfirmedReservations($table);
        }

        $table->update($data);

        return redirect()->route('admin.tables.index')
            ->with('status', 'Table '.$data['table_number'].' updated.');
    }

    private function assertNoUpcomingConfirmedReservations(Table $table): void
    {
        $hasUpcoming = $table->reservations()
            ->where('status', 'confirmed')
            ->get()
            ->contains(fn (Reservation $reservation) => $reservation->reservation_date_time->greaterThanOrEqualTo(now()));

        if ($hasUpcoming) {
            throw ValidationException::withMessages([
                'status' => 'Table '.$table->table_number.' has upcoming confirmed reservations. Reassign or cancel those reservations before placing the table into maintenance.',
            ]);
        }
    }
}
