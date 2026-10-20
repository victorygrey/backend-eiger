<?php

use App\Support\CmsPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 30)->unique();
            $table->json('permissions');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('job_title', 150)->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('invitation_accepted_at')->nullable();
            $table->string('password_setup_token_hash', 64)->nullable();
            $table->timestamp('password_setup_expires_at')->nullable();
        });
        foreach (['superadmin' => 'SuperAdmin', 'admin' => 'Store Admin'] as $slug => $name) {
            DB::table('roles')->insert(['name' => $name, 'slug' => $slug, 'permissions' => json_encode(CmsPermissions::keys()),
                'is_system' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach (['Ownplatform intern', 'CX', 'Simulasi Role', 'RGB', 'Non Active', 'Article Manager', 'Marketing (Content)', 'CRCM', 'Ecommerce'] as $index => $name) {
            DB::table('roles')->insert(['name' => $name, 'slug' => 'eiger-role-'.($index + 1), 'permissions' => '[]',
                'is_system' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        // Existing accounts keep their passwords and their operational permissions.
        foreach (DB::table('users')->select('role')->distinct()->pluck('role') as $old) {
            $slug = strtolower(trim($old));
            if ($slug === 'superuser') {
                $slug = 'superadmin';
            }
            if (! DB::table('roles')->where('slug', $slug)->exists()) {
                DB::table('roles')->insert(['name' => $old, 'slug' => $slug, 'permissions' => '[]',
                    'is_system' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('users')->where('role', $old)->update(['role' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['job_title', 'invited_at', 'invitation_accepted_at', 'password_setup_token_hash', 'password_setup_expires_at']));
        Schema::dropIfExists('roles');
    }
};
