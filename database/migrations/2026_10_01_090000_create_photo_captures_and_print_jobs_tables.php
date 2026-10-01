<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_captures', function (Blueprint $table) {
            $table->id();
            $table->string('capture_code')->unique();
            $table->foreignId('fit_and_go_device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_reference')->nullable()->index();
            $table->string('photo_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('storage_disk')->default('eiger-media');
            $table->timestamp('captured_at')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photo_capture_id')->constrained()->cascadeOnDelete();
            $table->string('print_code')->unique();
            $table->string('printer_name')->nullable();
            $table->unsignedSmallInteger('copies')->default(1);
            $table->string('status')->default('queued')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('requested_at')->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
        Schema::dropIfExists('photo_captures');
    }
};
