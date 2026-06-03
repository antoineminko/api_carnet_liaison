<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('devoirs', function (Blueprint $table) {
            $table->enum('type', ['maison', 'classe', 'exercice'])->default('maison')->after('matiere');
        });
    }

    public function down()
    {
        Schema::table('devoirs', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
