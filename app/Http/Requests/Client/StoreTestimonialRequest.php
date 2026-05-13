<?php

namespace App\Http\Requests\Client;

use App\Models\Testimonial;
use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreTestimonialRequest — Règles de validation pour la soumission d'un avis client.
 *
 * Usage dans un composant Livewire/Volt :
 *   $this->validate(
 *       (new StoreTestimonialRequest)->rules(),
 *       (new StoreTestimonialRequest)->messages()
 *   );
 *
 * Usage dans un Controller HTTP classique (si refacto future) :
 *   public function store(StoreTestimonialRequest $request) { ... }
 *
 * Contexte métier :
 *   - Un avis ne peut être déposé que sur une commande à l'état DELIVERED
 *   - Un seul avis est autorisé par commande (unicité order_id)
 *   - La note est entre 1 et 5 étoiles
 *   - Le contenu doit être substantiel (min 20 chars) mais concis (max 500 chars)
 *   - L'avis est créé en état "non publié" (modération admin requise)
 */
class StoreTestimonialRequest extends FormRequest
{
    /**
     * Seuls les utilisateurs authentifiés peuvent soumettre un avis.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Règles de validation pour le formulaire d'avis client.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // ─── Champ note ─────────────────────────────────────────────────
            'testimonialRating' => [
                'required',
                'integer',
                'between:1,5',
            ],

            // ─── Champ contenu ───────────────────────────────────────────────
            'testimonialContent' => [
                'required',
                'string',
                'min:20',
                'max:500',
            ],
        ];
    }

    /**
     * Messages d'erreur personnalisés en français.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'testimonialRating.required'         => 'Veuillez sélectionner une note.',
            'testimonialRating.integer'          => 'La note doit être un nombre entier.',
            'testimonialRating.between'          => 'La note doit être comprise entre :min et :max étoiles.',
            'testimonialContent.required'        => 'Veuillez rédiger votre avis avant de le soumettre.',
            'testimonialContent.string'          => 'L\'avis doit être du texte.',
            'testimonialContent.min'             => 'Votre avis doit contenir au moins :min caractères pour être informatif.',
            'testimonialContent.max'             => 'Votre avis ne peut pas dépasser :max caractères.',
        ];
    }

    /**
     * Attributs lisibles pour les messages d'erreur.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'testimonialRating'  => 'note',
            'testimonialContent' => 'avis',
        ];
    }

    /**
     * Vérifie qu'un avis unique n'a pas déjà été soumis pour cette commande.
     * À appeler manuellement depuis le composant Livewire avant Testimonial::create().
     *
     * Usage :
     *   if (StoreTestimonialRequest::alreadySubmitted($order->id)) {
     *       $this->addError('testimonialContent', 'Vous avez déjà soumis un avis pour cette commande.');
     *       return;
     *   }
     *
     * @param  string|int $orderId  UUID ou ID de la commande
     */
    public static function alreadySubmitted(string|int $orderId): bool
    {
        return Testimonial::where('order_id', $orderId)->exists();
    }
}
