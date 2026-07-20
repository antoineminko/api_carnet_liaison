<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('devoirs', function (Blueprint $table) {
            $table->string('type', 255)->default('maison')->change();
            $table->date('date_remise')->nullable()->change();
            $table->date('date_realisation')->nullable()->after('date_remise');
            $table->foreignId('cahier_texte_id')->nullable()->after('id')->constrained('cahier_textes')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('devoirs', function (Blueprint $table) {
            $table->dropForeign(['cahier_texte_id']);
            $table->dropColumn('cahier_texte_id');
            $table->dropColumn('date_realisation');
        });
    }
};
