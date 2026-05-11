<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\User;

class ListUsers extends Command {
    protected $signature = 'debug:users';
    protected $description = 'List all users with their roles';
    public function handle(): void {
        $users = User::select('name','email','role','created_at')->orderBy('created_at')->get();
        $this->table(['Name','Email','Role','Created'], $users->map(fn($u) => [
            $u->name, $u->email, $u->role, $u->created_at?->format('d/m/Y H:i')
        ]));
    }
}
