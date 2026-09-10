<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('products', function (Blueprint $table) { $table->json('pim_payload')->nullable(); $table->json('pim_image_payload')->nullable(); }); }
    public function down(): void { Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['pim_payload', 'pim_image_payload'])); }
};
