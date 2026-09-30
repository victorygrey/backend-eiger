<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fit_and_go_devices', function (Blueprint $table) {
            $table->string('activation_code_hash')->nullable()->after('product_selection_mode');
            $table->string('device_token_hash')->nullable()->after('activation_code_hash');
        });
    }

    public function down(): void
    {
        Schema::table('fit_and_go_devices', function (Blueprint $table) {
            $table->dropColumn(['activation_code_hash', 'device_token_hash']);
        });
    }
};
