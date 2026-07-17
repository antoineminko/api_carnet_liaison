<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // On modifie l'enum pour un varchar afin de supporter n'importe quelle valeur (ex: retard_repete, devoirs_non_faits)
        DB::statement("ALTER TABLE incidents MODIFY COLUMN type VARCHAR(255) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // En cas de rollback on remet l'enum de base, sachant que cela peut crasher s'il y a des données non compatibles
        DB::statement("ALTER TABLE incidents MODIFY COLUMN type ENUM('desordre', 'bavardage', 'bagarre', 'injure', 'retenu', 'autre') NOT NULL");
    }
};
