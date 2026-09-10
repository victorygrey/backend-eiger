<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tablets', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('location')->nullable();
            $table->foreignId('featured_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('activation_code_hash');
            $table->string('device_token_hash')->nullable();
            $table->unsignedBigInteger('config_version')->default(1);
            $table->timestamp('last_seen_at')->nullable();
            $table->string('media_status')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tablet_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablet_id')->constrained('tablets')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tablet_id', 'product_id']);
            $table->unique(['tablet_id', 'sort_order']);
        });

        Schema::create('tablet_config_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablet_id')->constrained('tablets')->cascadeOnDelete();
            $table->unsignedBigInteger('version_number');
            $table->foreignId('featured_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->json('recommendation_product_ids');
            $table->timestamps();

            $table->unique(['tablet_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablet_config_versions');
        Schema::dropIfExists('tablet_recommendations');
        Schema::dropIfExists('tablets');
    }
};
