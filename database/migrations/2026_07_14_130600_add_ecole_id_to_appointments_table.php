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
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'ecole_id')) {
                $table->unsignedBigInteger('ecole_id')->nullable()->after('id');
                // Optional: if ecoles table exists and you want a foreign key
                // $table->foreign('ecole_id')->references('id')->on('ecoles')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'ecole_id')) {
                // $table->dropForeign(['ecole_id']);
                $table->dropColumn('ecole_id');
            }
        });
    }
};
