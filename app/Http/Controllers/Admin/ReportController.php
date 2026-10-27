<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Date;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index()
    {
        return view('admin.reports.index');
    }

    public function salesWeekly()
    {
        return $this->download('sales', 'weekly', 7);
    }

    public function salesMonthly()
    {
        return $this->download('sales', 'monthly', 30);
    }

    public function reservationsWeekly()
    {
        return $this->download('reservations', 'weekly', 7);
    }

    public function reservationsMonthly()
    {
        return $this->download('reservations', 'monthly', 30);
    }

    private function download(string $type, string $period, int $days)
    {
        $generatedAt = Date::now('UTC')->toImmutable()->startOfSecond();
        $from = $generatedAt->subHours($days * 24);

        $data = match ($type) {
            'sales' => $this->reports->sales($from, $generatedAt),
            'reservations' => $this->reports->reservations($from, $generatedAt),
        };

        $periodLabel = $period === 'weekly' ? 'Last 7 days' : 'Last 30 days';

        $pdf = Pdf::loadView('admin.reports.pdfs.'.$type, [
            'period' => $period,
            'periodLabel' => $periodLabel,
            'from' => $from,
            'generatedAt' => $generatedAt,
            'data' => $data,
        ])->setPaper('a4');

        return $pdf->download(sprintf('%s-%s-report.pdf', $period, $type));
    }
}
