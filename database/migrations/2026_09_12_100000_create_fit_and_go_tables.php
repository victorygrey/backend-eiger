<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Devices (Kiosk hardware & GPU Workstation configuration)
        Schema::create('fit_and_go_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('device_code')->unique();
            $table->string('location')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('gpu_endpoint')->nullable();
            $table->string('camera_source')->nullable();
            $table->string('status')->default('online'); // online, offline, active, maintenance
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamps();
        });

        // 2. Activities (Mountaineering, Riding, Hiking, etc. with image & Care MC level 2)
        Schema::create('fit_and_go_activities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('care_mc_level_2')->nullable();
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Categories (Hat, Apparel, Pants, Footwear with background presets)
        Schema::create('fit_and_go_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // hat, apparel, pants, footwear
            $table->string('name')->nullable();
            $table->string('display_name');
            $table->string('mc_level')->nullable(); // mc 3, mc 4
            $table->string('mc_keywords')->nullable(); // keywords to match products
            $table->string('background_image')->nullable();
            $table->string('icon')->default('bi-tag');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. Item Visibilities (show/hide products in AI Fit & Go as per FR-CMS-03)
        Schema::create('fit_and_go_item_visibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('category_code'); // hat, apparel, pants, footwear
            $table->string('activity_slug')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'category_code'], 'fit_and_go_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fit_and_go_item_visibilities');
        Schema::dropIfExists('fit_and_go_categories');
        Schema::dropIfExists('fit_and_go_activities');
        Schema::dropIfExists('fit_and_go_devices');
    }
};
