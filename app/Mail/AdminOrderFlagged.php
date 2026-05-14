<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminOrderFlagged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public array $categories
    ) {}

    public function envelope(): Envelope
    {
        $hasCsam = in_array('sexual/minors', $this->categories);
        $subject = $hasCsam 
            ? "🚨 URGENCE LÉGALE : CSAM détecté dans {$this->order->reference}"
            : "⚠️ ALERTE NSFW : Contenu sensible détecté dans {$this->order->reference}";

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.admin.order-flagged',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
