<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 *
 * Factory de commandes pour les tests.
 *
 * Usage :
 *   Order::factory()->create()                  → commande PENDING
 *   Order::factory()->done()->create()          → commande DONE (prête pour paiement)
 *   Order::factory()->paid()->create()          → commande PAID
 *   Order::factory()->for(User::factory())      → commande liée à un user factory
 *   Order::factory()->create(['status' => …])   → override du statut (via forceFill interne)
 *
 * Note : status et payment_status ne sont PAS dans Order::$fillable.
 * La factory utilise forceFill() pour les définir directement sans passer
 * par la machine d'état (ce qui serait problématique en tests).
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $photosCount   = $this->faker->numberBetween(1, 10);
        $basePriceCents = $photosCount * 1500; // 15 € par photo

        return [
            'user_id'           => User::factory(),
            'reference'         => 'ORD-' . now()->format('Y') . '-' . str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'description'       => $this->faker->sentence(),
            'photo_count'       => $photosCount,
            'damage_level'      => $this->faker->randomElement(['faible', 'modéré', 'sévère']),
            'instructions'      => $this->faker->optional()->sentence(),
            'base_price_cents'  => $basePriceCents,
            'total_price_cents' => $basePriceCents,
            'amount_ht'         => round($basePriceCents / 100 / 1.20, 2),
            'tva_rate'          => 20.00,
            'amount_ttc'        => round($basePriceCents / 100, 2),
            // status et payment_status définis via afterMaking (voir ci-dessous)
            'admin_notes'       => null,
            'coupon_code'       => null,
            'discount_cents'    => 0,
        ];
    }

    /**
     * Configure le modèle après création.
     * Utilise forceFill() pour bypasser la machine d'état (tests uniquement).
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Order $order) {
            // Si status/payment_status ne sont pas encore définis, on les initialise
            if (! $order->status) {
                $order->forceFill([
                    'status'         => 'PENDING',
                    'payment_status' => 'pending',
                ]);
            }
        });
    }

    /**
     * Commande en cours de traitement par l'admin.
     */
    public function inProgress(): static
    {
        return $this->state(function () {
            return [];
        })->afterMaking(function (Order $order) {
            $order->forceFill(['status' => 'IN_PROGRESS', 'payment_status' => 'pending']);
        });
    }

    /**
     * Commande traitée, prête pour paiement.
     */
    public function done(): static
    {
        return $this->state(function () {
            return [];
        })->afterMaking(function (Order $order) {
            $order->forceFill(['status' => 'DONE', 'payment_status' => 'pending']);
        });
    }

    /**
     * Commande payée.
     */
    public function paid(): static
    {
        return $this->state(function () {
            return [
                'payment_intent_id' => 'pi_test_' . Str::random(16),
                'paid_at'           => now()->subMinutes(10),
            ];
        })->afterMaking(function (Order $order) {
            $order->forceFill(['status' => 'PAID', 'payment_status' => 'paid']);
        });
    }

    /**
     * Commande livrée (ZIP disponible).
     */
    public function delivered(): static
    {
        return $this->state(function () {
            return [
                'payment_intent_id' => 'pi_test_' . Str::random(16),
                'paid_at'           => now()->subHours(2),
                'delivered_at'      => now()->subHour(),
                'zip_path'          => 'deliveries/test.zip',
                'zip_expires_at'    => now()->addDays(7),
            ];
        })->afterMaking(function (Order $order) {
            $order->forceFill(['status' => 'DELIVERED', 'payment_status' => 'paid']);
        });
    }

    /**
     * Commande annulée.
     */
    public function cancelled(): static
    {
        return $this->state(function () {
            return [];
        })->afterMaking(function (Order $order) {
            $order->forceFill(['status' => 'CANCELLED', 'payment_status' => 'pending']);
        });
    }
}
