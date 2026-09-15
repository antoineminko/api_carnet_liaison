<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index de performance additifs uniquement.
 * Ne modifie aucune donnée ni contrainte métier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'is_read', 'sender_type'], 'messages_conv_read_sender_idx');
            $table->index(['conversation_id', 'created_at'], 'messages_conv_created_idx');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_type', 'user_id', 'is_read'], 'notifications_user_read_idx');
            $table->index(['user_type', 'user_id', 'created_at'], 'notifications_user_created_idx');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['eleve_id', 'date'], 'attendances_eleve_date_idx');
            $table->index(['classe_id', 'date'], 'attendances_classe_date_idx');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->index('parent_id', 'conversations_parent_id_idx');
            $table->index('enseignant_id', 'conversations_enseignant_id_idx');
            $table->index('ecole_id', 'conversations_ecole_id_idx');
            $table->index('status', 'conversations_status_idx');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->index('ecole_id', 'appointments_ecole_id_idx');
            $table->index(['enseignant_id', 'statut'], 'appointments_ens_statut_idx');
            $table->index(['parent_id', 'statut'], 'appointments_parent_statut_idx');
            $table->index('date_heure', 'appointments_date_heure_idx');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->index('prof_principal_id', 'classes_prof_principal_id_idx');
        });

        Schema::table('devoirs', function (Blueprint $table) {
            $table->index(['classe_id', 'date_remise'], 'devoirs_classe_remise_idx');
        });

        Schema::table('admin_informations', function (Blueprint $table) {
            $table->index(['eleve_id', 'is_read'], 'admin_infos_eleve_read_idx');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_conv_read_sender_idx');
            $table->dropIndex('messages_conv_created_idx');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_user_read_idx');
            $table->dropIndex('notifications_user_created_idx');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('attendances_eleve_date_idx');
            $table->dropIndex('attendances_classe_date_idx');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_parent_id_idx');
            $table->dropIndex('conversations_enseignant_id_idx');
            $table->dropIndex('conversations_ecole_id_idx');
            $table->dropIndex('conversations_status_idx');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_ecole_id_idx');
            $table->dropIndex('appointments_ens_statut_idx');
            $table->dropIndex('appointments_parent_statut_idx');
            $table->dropIndex('appointments_date_heure_idx');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->dropIndex('classes_prof_principal_id_idx');
        });

        Schema::table('devoirs', function (Blueprint $table) {
            $table->dropIndex('devoirs_classe_remise_idx');
        });

        Schema::table('admin_informations', function (Blueprint $table) {
            $table->dropIndex('admin_infos_eleve_read_idx');
        });
    }
};
