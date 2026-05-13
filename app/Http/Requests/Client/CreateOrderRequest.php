<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

/**
 * CreateOrderRequest — Règles de validation pour la création d'une commande client.
 *
 * Usage dans un composant Livewire/Volt :
 *   $this->validate((new CreateOrderRequest)->rules(), (new CreateOrderRequest)->messages());
 *
 * Usage dans un Controller HTTP classique (si refacto future) :
 *   public function store(CreateOrderRequest $request) { ... }
 *
 * Contexte métier :
 *   - Le client dépose 1 à 20 photos (JPEG, PNG, TIFF)
 *   - Chaque photo : max 20 Mo, formats image uniquement
 *   - Le damage_level est déterminé par l'IA (non soumis par l'utilisateur)
 *   - Les instructions sont optionnelles (max 1 000 caractères)
 *   - Le code coupon est optionnel et validé séparément par CouponService
 */
class CreateOrderRequest extends FormRequest
{
    /**
     * Seuls les utilisateurs authentifiés et vérifiés peuvent créer une commande.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasVerifiedEmail();
    }

    /**
     * Règles de validation pour la soumission complète du formulaire.
     *
     * Note : la validation des photos individuelles (formats, taille) est effectuée
     * lors de l'upload Livewire (wire:model.live). Ici on valide la collection.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // ─── Fichiers photos ────────────────────────────────────────────
            'photos'   => [
                'required',
                'array',
                'min:1',
                'max:20',
            ],
            'photos.*' => [
                'required',
                File::image()
                    ->mimes(['jpg', 'jpeg', 'png', 'tiff', 'tif'])
                    ->max(20 * 1024), // 20 Mo par photo
            ],

            // ─── Instructions client ─────────────────────────────────────────
            'instructions' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Règles de validation pour le coupon uniquement (appelées séparément).
     *
     * @return array<string, array<int, mixed>>
     */
    public static function couponRules(): array
    {
        return [
            'couponCode' => ['required', 'string', 'min:3', 'max:32'],
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
            'photos.required'      => 'Veuillez sélectionner au moins une photo à restaurer.',
            'photos.min'           => 'Veuillez sélectionner au moins :min photo.',
            'photos.max'           => 'Vous pouvez envoyer au maximum :max photos par commande.',
            'photos.array'         => 'Le champ photos doit être une liste de fichiers.',
            'photos.*.required'    => 'Chaque photo doit être un fichier valide.',
            'photos.*.image'       => 'Chaque fichier doit être une image (JPEG, PNG ou TIFF).',
            'photos.*.mimes'       => 'Formats acceptés : JPEG, PNG, TIFF uniquement.',
            'photos.*.max'         => 'Chaque photo ne doit pas dépasser 20 Mo.',
            'instructions.string'  => 'Les instructions doivent être du texte.',
            'instructions.max'     => 'Les instructions ne peuvent pas dépasser :max caractères.',
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
            'photos'       => 'photos',
            'photos.*'     => 'photo',
            'instructions' => 'instructions',
        ];
    }
}
