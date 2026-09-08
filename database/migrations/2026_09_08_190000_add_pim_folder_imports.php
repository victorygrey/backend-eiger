<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pim_imports', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id')->unique();
            $table->string('checksum', 64);
            $table->string('status');
            $table->text('message')->nullable();
            $table->unsignedInteger('attempts')->default(1);
            $table->timestamps();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->json('pim_media')->nullable();
            $table->string('pim_version')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['pim_media', 'pim_version']));
        Schema::dropIfExists('pim_imports');
    }
};
