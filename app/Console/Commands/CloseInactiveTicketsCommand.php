<?php

namespace App\Console\Commands;

use App\Models\SupportTicket;
use Illuminate\Console\Command;

class CloseInactiveTicketsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:close-inactive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Closes tickets that have been waiting for client response for more than 24 hours.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = now()->subHours(24);

        $tickets = SupportTicket::where('status', 'pending')
            ->where('updated_at', '<', $limit)
            ->get();

        $count = 0;
        foreach ($tickets as $ticket) {
            $ticket->update([
                'status' => 'closed',
                'closed_at' => now(),
            ]);
            $count++;
        }

        $this->info("Closed {$count} inactive tickets.");
    }
}
