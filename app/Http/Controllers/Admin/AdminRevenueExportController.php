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

        // ── Calcul de la Simulation depuis le Cache ──
        $simulatorSettings = \Illuminate\Support\Facades\Cache::get('admin_simulator_settings', []);
        
        $targetNetDirigeant = $simulatorSettings['dirigeant'] ?? 2500;
        $targetNetCollab = $simulatorSettings['collab'] ?? 1800;
        $fixedCosts = $simulatorSettings['fixed'] ?? 150;
        
        $collabInvoice = $targetNetCollab / (1 - 0.212); 
        $averageOrderPrice = $simulatorSettings['averageOrderPrice'] ?? ($stats['count'] > 0 ? ($stats['ttc_cents'] / 100 / $stats['count']) : 19);
        $iaRatio = $simulatorSettings['iaRatio'] ?? ($stats['ttc_cents'] > 0 ? ($stats['ai_cost'] / $stats['ttc_cents'] * 100) : 8.0);
        
        $stripePct = 0.015;
        $stripeFixedRatio = $averageOrderPrice > 0 ? (0.25 / $averageOrderPrice) : 0;
        
        $effectiveMarginRate = 1 - 0.212 - ($iaRatio / 100) - $stripePct - $stripeFixedRatio;
        
        $targetCaTtc = 0;
        if ($effectiveMarginRate > 0) {
            $targetCaTtc = ($targetNetDirigeant + $collabInvoice + $fixedCosts) / $effectiveMarginRate;
        }
        $targetOrders = $averageOrderPrice > 0 ? ceil($targetCaTtc / $averageOrderPrice) : 0;
        $estimatedStripeFees = ($targetCaTtc * $stripePct) + ($targetOrders * 0.25);
        $progressPercentage = $targetCaTtc > 0 ? min(100, round((($stats['ttc_cents'] / 100) / $targetCaTtc) * 100, 1)) : 0;

        $simulation = [
            'targetNetDirigeant' => $targetNetDirigeant,
            'targetNetCollab' => $targetNetCollab,
            'fixedCosts' => $fixedCosts,
            'collabInvoice' => $collabInvoice,
            'averageOrderPrice' => $averageOrderPrice,
            'iaRatio' => $iaRatio,
            'targetCaTtc' => $targetCaTtc,
            'targetOrders' => $targetOrders,
            'estimatedStripeFees' => $estimatedStripeFees,
            'progressPercentage' => $progressPercentage,
        ];

        $pdf = Pdf::loadView('pdf.admin-revenue-report', [
            'year'           => $year,
            'month'          => $month,
            'stats'          => $stats,
            'dailyBreakdown' => $dailyBreakdown,
            'orders'         => $orders->sortBy('paid_at'),
            'label'          => $selectedDate->translatedFormat('F Y'),
            'simulation'     => $simulation,
        ]);

        return $pdf->download("rapport-financier-omnyrestore-{$year}-{$month}.pdf");
    }
}
