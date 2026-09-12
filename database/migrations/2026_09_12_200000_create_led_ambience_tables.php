<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Immersive Ambience Digital (LED Dynamic Content) tables.
     */
    public function up(): void
    {
        // 1. Ambience Scenes (video, audio, lighting color, scene type: idle / active)
        Schema::create('led_ambience_scenes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('scene_type')->default('active'); // idle, active, default
            $table->string('activity_slug')->nullable(); // mountaineering, hiking, riding, camping, tactical, lifestyle
            $table->string('video_url')->nullable();
            $table->string('audio_url')->nullable();
            $table->string('lighting_color')->default('#e8500a'); // Hex color code for ambient light
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. LED Ambience RFID Items (Custom RFID config with Edit & Delete for LED Ambience)
        Schema::create('led_ambience_items', function (Blueprint $table) {
            $table->id();
            $table->string('rfid_tag')->unique();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('activity_slug')->nullable();
            $table->foreignId('scene_id')->nullable()->constrained('led_ambience_scenes')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('led_ambience_items');
        Schema::dropIfExists('led_ambience_scenes');
    }
};
