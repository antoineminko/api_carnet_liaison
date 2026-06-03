<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Champ pour la nouvelle date proposée lors d'un report
            $table->dateTime('new_proposed_date')->nullable()->after('date_heure');
            // Raison du report
            $table->text('report_reason')->nullable()->after('motif');
            // Mode de rencontre détaillé (présentiel, vocal, vidéo)
            $table->enum('mode', ['presentiel', 'vocal', 'video'])->default('presentiel')->after('type');
            // Objet du rendez-vous
            $table->string('objet')->nullable()->after('eleve_id');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['new_proposed_date', 'report_reason', 'mode', 'objet']);
        });
    }
};
