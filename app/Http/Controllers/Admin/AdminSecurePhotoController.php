<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * AdminSecurePhotoController — OmnyRestore
 *
 * Sert les photos (originals + retouched) pour les admins via une route protégée.
 * Pas de contrainte email-gate ni de filtre is_rejected : l'admin doit tout voir.
 */
class AdminSecurePhotoController extends Controller
{
    public function show(Request $request, Order $order, Media $media): Response
    {
        // Appartient à cet ordre ?
        abort_unless(
            $media->model_id === $order->id
            && in_array($media->collection_name, ['retouched', 'originals']),
            404,
            'Photo introuvable.'
        );

        if (! file_exists($media->getPath())) {
            abort(404, 'Fichier introuvable sur le disque.');
        }

        $contents = file_get_contents($media->getPath());

        return response($contents, 200, [
            'Content-Type'   => $media->mime_type ?: 'image/jpeg',
            'Content-Length' => strlen($contents),
            'Cache-Control'  => 'private, no-store',
        ]);
    }
}
