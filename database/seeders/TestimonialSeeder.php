<?php

namespace Database\Seeders;

use App\Models\Testimonial;
use Illuminate\Database\Seeder;

class TestimonialSeeder extends Seeder
{
    public function run(): void
    {
        $testimonials = [
            [
                'author_name'     => 'Marie L.',
                'author_initials' => 'ML',
                'rating'          => 5,
                'is_published'    => true,
                'content'         => "J'avais une photo de mariage de mes grands-parents datant de 1958, déchirée en deux et très jaunie. Le résultat est bluffant — on distingue maintenant chaque détail de leurs visages. Un souvenir que je croyais perdu à jamais.",
            ],
            [
                'author_name'     => 'Jean-Pierre D.',
                'author_initials' => 'JD',
                'rating'          => 5,
                'is_published'    => true,
                'content'         => "Très satisfait du résultat sur 8 photos de famille des années 40, certaines avec des taches d'humidité importantes. Le fait de voir l'aperçu avant de payer est vraiment rassurant — aucune mauvaise surprise.",
            ],
            [
                'author_name'     => 'Sophie M.',
                'author_initials' => 'SM',
                'rating'          => 4,
                'is_published'    => true,
                'content'         => "Rapide et efficace. Le rendu final est excellent, même sur une photo très abîmée. Le processus est clair et le service client réactif. Je recommande pour toute photo de famille précieuse.",
            ],
            [
                'author_name'     => 'Thomas B.',
                'author_initials' => 'TB',
                'rating'          => 5,
                'is_published'    => true,
                'content'         => "Des photos de montagne des années 70, avec des rayures profondes et des couleurs très fanées. Le résultat m'a surpris — les couleurs d'origine ont été retrouvées avec une précision que je n'attendais pas.",
            ],
            [
                'author_name'     => 'Isabelle C.',
                'author_initials' => 'IC',
                'rating'          => 4,
                'is_published'    => true,
                'content'         => "Le principe d'aperçu avant paiement est vraiment bien pensé. J'ai pu rejeter les deux photos pour lesquelles l'IA n'avait pas pu faire grand-chose, et ne payer que les 5 autres. Honnête et transparent.",
            ],
        ];

        foreach ($testimonials as $data) {
            Testimonial::create($data);
        }
    }
}
