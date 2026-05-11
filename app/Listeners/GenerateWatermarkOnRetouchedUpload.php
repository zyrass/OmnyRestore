<?php

namespace App\Listeners;

use App\Jobs\GenerateWatermarkJob;
use App\Models\Order;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

/**
 * Listener: GenerateWatermarkOnRetouchedUpload
 *
 * Déclenché automatiquement par Spatie MediaLibrary quand un fichier
 * est ajouté à n'importe quelle collection média.
 *
 * Filtre : ne réagit que si :
 *   - Le modèle parent est une commande (`Order`)
 *   - La collection cible est `retouched`
 *
 * Dispatch `GenerateWatermarkJob` de manière asynchrone (queue).
 */
class GenerateWatermarkOnRetouchedUpload
{
    /**
     * Handle the MediaHasBeenAddedEvent event.
     */
    public function handle(MediaHasBeenAddedEvent $event): void
    {
        $media = $event->media;

        // Ne traiter que les uploads dans la collection 'retouched'
        if ($media->collection_name !== 'retouched') {
            return;
        }

        // Vérifier que le modèle parent est bien une commande
        $model = $media->model;
        if (! ($model instanceof Order)) {
            return;
        }

        // Dispatch asynchrone — le watermark est généré en arrière-plan
        GenerateWatermarkJob::dispatch($model);
    }
}
