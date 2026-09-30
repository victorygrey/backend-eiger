<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fit_and_go_devices', function (Blueprint $table): void {
            $table->text('activation_code_encrypted')->nullable()->after('activation_code_hash');
        });

        Schema::table('tablets', function (Blueprint $table): void {
            $table->text('activation_code_encrypted')->nullable()->after('activation_code_hash');
        });
    }

    public function down(): void
    {
        Schema::table('fit_and_go_devices', function (Blueprint $table): void {
            $table->dropColumn('activation_code_encrypted');
        });

        Schema::table('tablets', function (Blueprint $table): void {
            $table->dropColumn('activation_code_encrypted');
        });
    }
};
