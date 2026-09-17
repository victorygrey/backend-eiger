<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atom_product_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_id')->unique();
            $table->string('external_id', 50)->index();
            $table->string('name');
            $table->string('slug')->index();
            $table->text('image')->nullable();
            $table->text('image_secondary')->nullable();
            $table->text('image_web')->nullable();
            $table->string('channel_code', 50)->nullable()->index();
            $table->unsignedInteger('sequence_number')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('source_created_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('source_deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('atom_product_sub_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_id')->unique();
            $table->foreignId('category_id')->constrained('atom_product_categories')->cascadeOnDelete();
            $table->string('external_id', 50)->index();
            $table->string('name');
            $table->string('slug')->index();
            $table->text('image')->nullable();
            $table->text('image_secondary')->nullable();
            $table->text('image_web')->nullable();
            $table->unsignedInteger('sequence_number')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('source_created_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('source_deleted_at')->nullable();
            $table->timestamps();
            $table->unique(['category_id', 'external_id']);
        });

        Schema::create('atom_product_activity_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_id')->unique();
            $table->string('external_id', 50)->index();
            $table->string('name');
            $table->string('slug')->index();
            $table->text('image')->nullable();
            $table->text('image_web')->nullable();
            $table->unsignedInteger('sequence_number')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('source_created_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('source_deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('atom_product_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_id')->unique();
            $table->foreignId('activity_group_id')->constrained('atom_product_activity_groups')->cascadeOnDelete();
            $table->string('external_id', 50)->index();
            $table->string('name');
            $table->string('slug')->index();
            $table->text('image')->nullable();
            $table->text('description')->nullable();
            $table->text('rating_description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('source_created_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('source_deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('atom_product_category_id')->nullable()->after('category')
                ->constrained('atom_product_categories')->nullOnDelete();
            $table->foreignId('atom_product_sub_category_id')->nullable()->after('atom_product_category_id')
                ->constrained('atom_product_sub_categories')->nullOnDelete();
        });

        Schema::table('product_activities', function (Blueprint $table) {
            $table->foreignId('atom_product_activity_id')->nullable()->after('pim_id')
                ->constrained('atom_product_activities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_activities', fn (Blueprint $table) => $table->dropConstrainedForeignId('atom_product_activity_id'));
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('atom_product_sub_category_id');
            $table->dropConstrainedForeignId('atom_product_category_id');
        });
        Schema::dropIfExists('atom_product_activities');
        Schema::dropIfExists('atom_product_activity_groups');
        Schema::dropIfExists('atom_product_sub_categories');
        Schema::dropIfExists('atom_product_categories');
    }
};
