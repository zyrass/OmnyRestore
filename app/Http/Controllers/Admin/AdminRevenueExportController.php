<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminRevenueExportController extends Controller
{
    public function download(Request $request)
    {
        $year  = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $selectedDate = Carbon::createFromDate($year, $month, 1);
        $startOfMonth = $selectedDate->copy()->startOfMonth();
        $endOfMonth   = $selectedDate->copy()->endOfMonth();

        $orders = Order::where('payment_status', 'paid')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$startOfMonth, $endOfMonth])
            ->get();

        $stats = [
            'ht_cents'    => $orders->sum('total_price_cents'),
            'ttc_cents'   => $orders->sum(fn($o) => $o->total_price_cents + round($o->total_price_cents * 0.2)),
            'count'       => $orders->count(),
            'photos'      => $orders->sum('photo_count'),
            'ai_cost'     => $orders->sum('photo_count') * 15,
            'urssaf'      => 0,
        ];
        $stats['urssaf'] = (int) round($stats['ttc_cents'] * 0.212);
        $stats['net']    = $stats['ht_cents'] - $stats['ai_cost'] - $stats['urssaf'];

        // Données pour le tableau détaillé
        $dailyBreakdown = $orders->groupBy(fn($o) => $o->paid_at->format('d/m/Y'))
            ->map(fn($dayOrders) => [
                'count' => $dayOrders->count(),
                'ht'    => $dayOrders->sum('total_price_cents'),
            ])
            ->sortKeys();

        $pdf = Pdf::loadView('pdf.admin-revenue-report', [
            'year'           => $year,
            'month'          => $month,
            'stats'          => $stats,
            'dailyBreakdown' => $dailyBreakdown,
            'orders'         => $orders->sortBy('paid_at'),
            'label'          => $selectedDate->translatedFormat('F Y'),
        ]);

        return $pdf->download("rapport-financier-omnyrestore-{$year}-{$month}.pdf");
    }
}
