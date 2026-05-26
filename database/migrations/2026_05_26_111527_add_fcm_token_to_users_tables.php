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
        Schema::table('parent_users', function (Blueprint $table) {
            $table->string('fcm_token')->nullable();
        });

        if (Schema::hasTable('enseignants')) {
            Schema::table('enseignants', function (Blueprint $table) {
                $table->string('fcm_token')->nullable()->after('email');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parent_users', function (Blueprint $table) {
            $table->dropColumn('fcm_token');
        });

        if (Schema::hasTable('enseignants')) {
            Schema::table('enseignants', function (Blueprint $table) {
                $table->dropColumn('fcm_token');
            });
        }
    }
};
