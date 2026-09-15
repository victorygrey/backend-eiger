<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fit_and_go_activity_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('fit_and_go_activities')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['activity_id', 'product_id']);
        });

        // Existing automatic visibility assignments are reset for the new manual catalog.
        DB::table('fit_and_go_item_visibilities')->update(['is_visible' => false]);

        foreach (['mountaineering', 'tactical', 'lifestyle'] as $slug) {
            DB::table('fit_and_go_activities')->where('slug', $slug)->update(['is_active' => false]);
        }
        $defaults = [
            ['slug' => 'camping', 'name' => 'Camping', 'sort_order' => 1],
            ['slug' => 'hiking', 'name' => 'Hiking', 'sort_order' => 2],
            ['slug' => 'running', 'name' => 'Running', 'sort_order' => 3,
                'image' => 'https://images.unsplash.com/photo-1552674605-db6ffd4facb5?auto=format&fit=crop&w=800&q=80',
                'description' => 'Aktivitas lari dan olahraga luar ruang.'],
            ['slug' => 'riding', 'name' => 'Riding', 'sort_order' => 4],
            ['slug' => 'travelling', 'name' => 'Travelling', 'sort_order' => 5,
                'image' => 'https://images.unsplash.com/photo-1523381210434-271e8be1f52b?auto=format&fit=crop&w=800&q=80',
                'description' => 'Perjalanan dan eksplorasi kota maupun alam.'],
        ];
        foreach ($defaults as $activity) {
            $exists = DB::table('fit_and_go_activities')->where('slug', $activity['slug'])->exists();
            $values = $activity;
            unset($values['slug']);
            if ($exists) {
                unset($values['image'], $values['description']);
            } else {
                $values['created_at'] = now();
            }
            DB::table('fit_and_go_activities')->updateOrInsert(
                ['slug' => $activity['slug']],
                $values + ['is_active' => true, 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fit_and_go_activity_products');
    }
};
