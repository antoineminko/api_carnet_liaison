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
        Schema::table('enseignants', function (Blueprint $table) {
            $table->unsignedBigInteger('active_ecole_id')->nullable()->after('ecole_id');
            $table->foreign('active_ecole_id')->references('id')->on('ecoles')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enseignants', function (Blueprint $table) {
            $table->dropForeign(['active_ecole_id']);
            $table->dropColumn('active_ecole_id');
        });
    }
};
