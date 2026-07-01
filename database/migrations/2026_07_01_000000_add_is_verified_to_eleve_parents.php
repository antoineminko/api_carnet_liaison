<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eleve_parents', function (Blueprint $table) {
            $table->boolean('is_verified')->default(false)->after('relation');
        });
    }

    public function down(): void
    {
        Schema::table('eleve_parents', function (Blueprint $table) {
            $table->dropColumn('is_verified');
        });
    }
};
