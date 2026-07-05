<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('fichier_url')->nullable()->after('content');
        });
        Schema::table('admin_informations', function (Blueprint $table) {
            $table->string('fichier_url')->nullable()->after('contenu');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('fichier_url');
        });
        Schema::table('admin_informations', function (Blueprint $table) {
            $table->dropColumn('fichier_url');
        });
    }
};
