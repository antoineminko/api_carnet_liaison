<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('admin_informations', function (Blueprint $table) {
            $table->decimal('montant_paye', 10, 2)->nullable()->after('montant');
            $table->decimal('montant_restant', 10, 2)->nullable()->after('montant_paye');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_informations', function (Blueprint $table) {
            $table->dropColumn(['montant_paye', 'montant_restant']);
        });
    }
};
