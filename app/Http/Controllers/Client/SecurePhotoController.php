<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * SecurePhotoController — OmnyRestore
 *
 * Sert les photos restaurées (collection 'retouched') de façon sécurisée.
 *
 * Accès autorisé uniquement si :
 *   1. L'utilisateur est authentifié et propriétaire de la commande
 *   2. La commande est en statut DONE (aperçu disponible) ou PAID/DELIVERED
 *   3. L'email-gate est passé (preview_unlocked_at IS NOT NULL)
 *   4. La photo n'est pas rejetée (is_rejected = false)
 *
 * Les admins ont leur propre route dans routes/admin.php qui n'a pas
 * cette restriction (ils peuvent voir toutes les photos).
 *
 * Architecture :
 *   - Les fichiers sont sur le disk 'local' (non accessible via /storage/)
 *   - Ce controller streame le fichier directement depuis storage/app/
 *   - Aucune URL directe publique n'existe pour les retouched
 */
class SecurePhotoController extends Controller
{
    /**
     * Sert une photo retouchée de façon sécurisée pour le client.
     *
     * GET /client/orders/{order}/photos/{media}
     *
     * @param Request $request
     * @param Order   $order   Route Model Binding
     * @param Media   $media   Route Model Binding
     */
    public function show(Request $request, Order $order, Media $media): Response
    {
        $user = $request->user();

        // 1. Vérifier la propriété de la commande (IDOR prevention)
        abort_unless(
            $order->user_id === $user->id,
            403,
            'Accès refusé à cette commande.'
        );

        // 2. Vérifier que la photo appartient à cet ordre et à la bonne collection
        abort_unless(
            $media->model_id === $order->id
            && $media->collection_name === 'retouched',
            404,
            'Photo introuvable.'
        );

        // 3. Vérifier le statut de la commande
        abort_unless(
            in_array($order->status, ['DONE', 'PAID', 'DELIVERED']),
            403,
            'Photos non disponibles pour cette commande.'
        );

        // 4. Email-gate : le client doit avoir cliqué le lien email
        abort_unless(
            $order->preview_unlocked_at !== null,
            403,
            'Veuillez d\'abord cliquer sur le lien reçu par email pour déverrouiller l\'aperçu.'
        );

        // 5. Ne pas servir les photos rejetées
        abort_if(
            $media->getCustomProperty('is_rejected', false),
            404,
            'Cette photo a été retirée de la commande.'
        );

        // 6. Lire le fichier depuis le disk sécurisé (local ou s3)
        $contents = Storage::disk($media->disk)->get($media->getPath());

        abort_if($contents === null || $contents === false, 404, 'Fichier introuvable.');

        // 7. Streamer le fichier avec le bon Content-Type
        $mimeType = $media->mime_type ?: 'image/jpeg';

        return response($contents, 200, [
            'Content-Type'        => $mimeType,
            'Content-Length'      => strlen($contents),
            'Cache-Control'       => 'private, no-store, no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
            // Interdit l'embedding dans des iframes externes
            'X-Frame-Options'     => 'SAMEORIGIN',
            // Désactive le caching navigateur (pas de Ctrl+S url dans la cache)
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }
}
