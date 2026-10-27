<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTableRequest;
use App\Http\Requests\Admin\UpdateTableRequest;
use App\Models\Reservation;
use App\Models\Table;
use App\Services\BusinessTime;
use App\Services\TableManagementService;

class TableController extends Controller
{
    public function __construct(private TableManagementService $management, private BusinessTime $businessTime) {}

    public function index()
    {
        $tables = Table::with(['reservations' => fn ($query) => $query->whereIn('status', ['pending', 'confirmed', 'completed'])
            ->with(['payment' => fn ($payment) => $payment->where('status', 'paid')])])
            ->orderBy('table_number')->get();
        $now = $this->businessTime->now();
        $tables->each(function (Table $table) use ($now): void {
            $reasons = $table->reservations->map(fn (Reservation $reservation) => $this->management->protectionReason($reservation, $now))->filter();
            $table->setAttribute('protected_count', $reasons->count());
            $table->setAttribute('protection_summary', $reasons->countBy());
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

        $this->management->update($table->id, $data);

        return redirect()->route('admin.tables.index')
            ->with('status', 'Table '.$data['table_number'].' updated.');
    }
}
