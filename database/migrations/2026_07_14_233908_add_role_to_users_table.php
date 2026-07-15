<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom role ke tabel users.
     *
     * Role dipakai oleh Policy (mis. WorkOrderPolicy) untuk membedakan akses
     * "creator/pemilik record" vs "admin" (admin selalu boleh update/delete
     * terlepas siapa creator-nya). Sesuai target users di docs/prd.md:
     * Production Manager, PPIC, Operator, dan (implisit) Admin/superuser.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Default 'operator' karena ini role dengan akses paling terbatas
            // — user baru yang di-invite tanpa role eksplisit tidak otomatis
            // dapat privilege admin.
            $table->string('role', 20)
                ->default('operator')
                ->after('email')
                ->comment('ENUM: admin, production_manager, ppic, operator');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};