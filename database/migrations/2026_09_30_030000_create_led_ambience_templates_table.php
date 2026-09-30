<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('led_ambience_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_key', 40)->unique();
            $table->string('name', 100);
            $table->string('video_url', 500)->nullable();
            $table->string('audio_url', 500)->nullable();
            $table->string('lighting_color', 20)->default('#e8500a');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        foreach ((array) config('led_ambience.templates') as $key => $definition) {
            $legacy = $this->legacyScene($key);

            DB::table('led_ambience_templates')->insert([
                'template_key' => $key,
                'name' => $definition['name'],
                'video_url' => $legacy?->video_url,
                'audio_url' => $legacy?->audio_url,
                'lighting_color' => $legacy?->lighting_color ?: $definition['lighting_color'],
                'description' => $legacy?->description,
                'sort_order' => $definition['sort_order'],
                'is_active' => $legacy?->is_active ?? true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('led_ambience_templates');
    }

    private function legacyScene(string $key): ?object
    {
        if (! Schema::hasTable('led_ambience_scenes')) {
            return null;
        }

        if ($key === 'idle') {
            return DB::table('led_ambience_scenes')
                ->where('scene_type', 'idle')
                ->orderByDesc('is_active')
                ->orderBy('sort_order')
                ->first();
        }

        return DB::table('led_ambience_scenes')
            ->where('activity_slug', $key)
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->first();
    }
};
