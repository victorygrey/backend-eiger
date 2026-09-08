<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfid_tags', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });

        Schema::table('rfid_tags', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->change();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('rfid_tags')->whereNull('product_id')->delete();

        Schema::table('rfid_tags', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });

        Schema::table('rfid_tags', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable(false)->change();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }
};
