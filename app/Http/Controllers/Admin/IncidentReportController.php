<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class IncidentReportController extends Controller
{
    public function download()
    {
        // On génère le PDF à partir de la vue dédiée
        $pdf = Pdf::loadView('pdf.incident-report', [
            'now' => now(),
        ]);

        return $pdf->download('OmnyRestore-Rapport-Incident-' . now()->format('Ymd-His') . '.pdf');
    }
}
