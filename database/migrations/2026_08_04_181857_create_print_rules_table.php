<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Print Rules define the conditions for the Print Photo feature.
     */
    public function up(): void
    {
        Schema::create('print_rules', function (Blueprint $table) {
            $table->id();
            $table->decimal('minimum_transaction', 15, 2)->default(0);
            $table->boolean('require_membership')->default(false);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_rules');
    }
};
