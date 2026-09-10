<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index()
    {
        return view('admin.reports.index');
    }

    public function salesWeekly()
    {
        return $this->download('sales', 'weekly', now()->subDays(7));
    }

    public function salesMonthly()
    {
        return $this->download('sales', 'monthly', now()->subDays(30));
    }

    public function reservationsWeekly()
    {
        return $this->download('reservations', 'weekly', now()->subDays(7));
    }

    public function reservationsMonthly()
    {
        return $this->download('reservations', 'monthly', now()->subDays(30));
    }

    public function feedbackWeekly()
    {
        return $this->download('feedback', 'weekly', now()->subDays(7));
    }

    public function feedbackMonthly()
    {
        return $this->download('feedback', 'monthly', now()->subDays(30));
    }

    private function download(string $type, string $period, CarbonInterface $from)
    {
        $data = match ($type) {
            'sales' => $this->reports->sales($from),
            'reservations' => $this->reports->reservations($from),
            'feedback' => $this->reports->feedback($from),
        };

        $periodLabel = $period === 'weekly' ? 'Last 7 days' : 'Last 30 days';

        $pdf = Pdf::loadView('admin.reports.pdfs.'.$type, [
            'period' => $period,
            'periodLabel' => $periodLabel,
            'from' => $from,
            'generatedAt' => now(),
            'data' => $data,
        ])->setPaper('a4');

        return $pdf->download(sprintf('%s-%s-report.pdf', $period, $type));
    }
}
