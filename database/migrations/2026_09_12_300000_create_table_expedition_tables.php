<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Table Product Knowledge (Table Expedition Hub) tables.
     */
    public function up(): void
    {
        // 1. Table Expedition RFID Items (Custom RFID config with full Edit & Delete for Table Expedition)
        Schema::create('table_expedition_items', function (Blueprint $table) {
            $table->id();
            $table->string('rfid_tag')->unique();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('activity_slug')->nullable(); // mountaineering, hiking, riding, camping, tactical, lifestyle
            $table->string('ideal_for')->nullable(); // e.g. "Mountaineering, Heavy Trekking, Alpine Climate"
            $table->string('video_url')->nullable(); // Product knowledge video demo
            $table->json('features')->nullable(); // Feature bullet points
            $table->json('technical_details')->nullable(); // Specs: weight, dimensions, materials, etc.
            $table->text('ai_summary')->nullable(); // AI Summary for product knowledge (FR-TABLE-06)
            $table->json('similar_product_ids')->nullable(); // Up to 5 similar product IDs (FR-TABLE-04)
            $table->string('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();
        });

        // 2. Table Expedition Standby & Usage Configs (FR-TABLE-01 & FR-TABLE-07)
        Schema::create('table_expedition_configs', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_expedition_items');
        Schema::dropIfExists('table_expedition_configs');
    }
};
