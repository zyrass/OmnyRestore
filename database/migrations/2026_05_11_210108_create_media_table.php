<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Spatie MediaLibrary — table media
 *
 * IMPORTANT: on utilise uuidMorphs() au lieu de morphs() car nos modèles
 * (Order, User) utilisent des UUIDs comme clé primaire.
 * morphs() génère model_id en UNSIGNED BIGINT → incompatible avec UUID strings.
 * uuidMorphs() génère model_id en CHAR(36) → compatible.
 *
 * @see https://spatie.be/docs/laravel-medialibrary/v11/installation-setup
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();

            // uuidMorphs() = model_type (string) + model_id (CHAR 36)
            // Obligatoire car Order::$primaryKey est un UUID, pas un bigint
            $table->uuidMorphs('model');
            $table->uuid()->nullable()->unique();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable()->index();

            $table->nullableTimestamps();
        });
    }
};
