<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_captures', function (Blueprint $table) {
            $table->uuid('idempotency_key')->nullable();
            $table->string('request_hash', 64)->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('activity', 100)->nullable();
            $table->string('background_id', 100)->nullable();
            $table->json('products')->nullable();
            $table->string('image_mime', 30)->nullable();
            $table->unsignedBigInteger('image_size')->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('purged_at')->nullable();
            $table->unique(['fit_and_go_device_id', 'idempotency_key'], 'photo_capture_device_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('photo_captures', function (Blueprint $table) {
            $table->dropUnique('photo_capture_device_key_unique');
            $table->dropColumn(['idempotency_key', 'request_hash', 'gender', 'activity', 'background_id', 'products', 'image_mime', 'image_size', 'consented_at', 'purged_at']);
        });
    }
};
