<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tablets', function (Blueprint $table) {
            $table->string('activation_code_lookup_hash', 64)
                ->nullable()
                ->unique()
                ->after('activation_code_hash');
        });
    }

    public function down(): void
    {
        Schema::table('tablets', function (Blueprint $table) {
            $table->dropUnique(['activation_code_lookup_hash']);
            $table->dropColumn('activation_code_lookup_hash');
        });
    }
};
