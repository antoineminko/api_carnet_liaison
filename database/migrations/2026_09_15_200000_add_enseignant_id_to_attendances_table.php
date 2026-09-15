<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute enseignant_id aux appels (additif, nullable) pour le reporting admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('attendances', 'enseignant_id')) {
                $table->unsignedBigInteger('enseignant_id')->nullable()->after('classe_id');
                $table->index('enseignant_id', 'attendances_enseignant_id_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (Schema::hasColumn('attendances', 'enseignant_id')) {
                $table->dropIndex('attendances_enseignant_id_idx');
                $table->dropColumn('enseignant_id');
            }
        });
    }
};
