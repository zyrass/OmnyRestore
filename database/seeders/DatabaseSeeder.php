<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * DatabaseSeeder — OmnyRestore
 *
 * Seeds the development database with initial test data.
 * Run with: php artisan migrate:fresh --seed
 *
 * What gets seeded:
 *   1. Admin account (for back-office access)
 *   2. Test client accounts (for client portal testing)
 *   3. Sample orders in various statuses (for UI development)
 *
 * IMPORTANT: This seeder is for DEVELOPMENT ONLY.
 * The admin password is insecure ('password') — never run this in production.
 * Production accounts must be created manually with strong passwords.
 *
 * Credentials after seeding:
 *   Admin:  admin@omnyrestore.test / password
 *   Client: client@omnyrestore.test / password
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌱 Seeding OmnyRestore development database...');

        // ─── 1. Admin Account ─────────────────────────────────────────────
        // Create the main administrator account.
        // In production: create via php artisan tinker or a secure setup command.
        $admin = User::firstOrCreate(
            ['email' => 'admin@omnyrestore.test'],
            [
                'name'              => 'Alain Guillon',
                'password'          => Hash::make('password'), // NEVER use in production
                'role'              => 'admin',
                'email_verified_at' => now(), // Pre-verified for dev convenience
                'rgpd_consent_at'   => now(),
            ]
        );
        $this->command->info("  ✅ Admin created: {$admin->email}");

        // ─── 2. Test Client Accounts ──────────────────────────────────────
        // Create sample client accounts for testing the full client workflow.
        $clientData = [
            ['name' => 'Marie Dupont',   'email' => 'client@omnyrestore.test'],
            ['name' => 'Jean Martin',    'email' => 'jean@omnyrestore.test'],
            ['name' => 'Sophie Bernard', 'email' => 'sophie@omnyrestore.test'],
        ];

        foreach ($clientData as $data) {
            $client = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'              => $data['name'],
                    'password'          => Hash::make('password'),
                    'role'              => 'client',
                    'email_verified_at' => now(),
                    'rgpd_consent_at'   => now(),
                ]
            );
            $this->command->info("  ✅ Client created: {$client->email}");
        }

        // ─── 3. Testimonials ──────────────────────────────────────────────
        $this->call(TestimonialSeeder::class);
        $this->command->info('  ✅ Testimonials seedés.');

        $this->command->newLine();
        $this->command->info('🎉 Seeding complete!');
        $this->command->newLine();
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Admin',  'admin@omnyrestore.test',  'password'],
                ['Client', 'client@omnyrestore.test', 'password'],
                ['Client', 'jean@omnyrestore.test',   'password'],
                ['Client', 'sophie@omnyrestore.test', 'password'],
            ]
        );
        $this->command->warn('⚠️  These are development credentials — NEVER use in production!');
    }
}
