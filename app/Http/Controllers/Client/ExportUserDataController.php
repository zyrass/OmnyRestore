<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Response;

class ExportUserDataController extends Controller
{
    /**
     * Exporte les données personnelles du client au format JSON (Art. 20 RGPD).
     */
    public function export(Request $request)
    {
        $user = $request->user();

        // On charge les relations nécessaires pour un export exhaustif
        $user->load([
            'orders' => function($query) {
                $query->with('media', 'delivery');
            },
            'supportTickets.messages',
            'testimonials'
        ]);

        $data = [
            'export_date' => now()->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at->toIso8601String(),
                'rgpd_consent_at' => $user->rgpd_consent_at ? $user->rgpd_consent_at->toIso8601String() : null,
            ],
            'orders' => $user->orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'reference' => $order->reference,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'amount_ttc' => $order->amount_ttc,
                    'created_at' => $order->created_at->toIso8601String(),
                    'paid_at' => $order->paid_at ? $order->paid_at->toIso8601String() : null,
                    'delivered_at' => $order->delivered_at ? $order->delivered_at->toIso8601String() : null,
                    'photos_count' => $order->photo_count,
                    'description' => $order->description,
                ];
            }),
            'tickets' => $user->supportTickets->map(function ($ticket) {
                return [
                    'id' => $ticket->id,
                    'reference' => $ticket->reference,
                    'subject' => $ticket->subject,
                    'status' => $ticket->status,
                    'created_at' => $ticket->created_at->toIso8601String(),
                    'messages_count' => $ticket->messages->count(),
                ];
            }),
            'testimonials' => $user->testimonials->map(function ($testimonial) {
                return [
                    'id' => $testimonial->id,
                    'content' => $testimonial->content,
                    'rating' => $testimonial->rating,
                    'is_published' => $testimonial->is_published,
                    'created_at' => $testimonial->created_at->toIso8601String(),
                ];
            })
        ];

        // Audit Log pour tracer l'export des données
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'export_personal_data',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'payload' => ['type' => 'json_export']
        ]);

        $fileName = 'omnyrestore_export_donnees_' . now()->format('Y-m-d') . '.json';

        return Response::json($data, 200, [
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
