<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parent_users', function (Blueprint $table) {
            $table->unsignedBigInteger('ecole_id')->nullable()->after('id');
            $table->foreign('ecole_id')->references('id')->on('ecoles')->nullOnDelete();
            $table->index('ecole_id');
        });

        // Drop the old global unique constraint on email, then add per-school unique
        Schema::table('parent_users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->unique(['email', 'ecole_id'], 'parent_email_ecole_unique');
        });
    }

    public function down(): void
    {
        Schema::table('parent_users', function (Blueprint $table) {
            $table->dropForeign(['ecole_id']);
            $table->dropIndex(['ecole_id']);
            $table->dropUnique('parent_email_ecole_unique');
            $table->dropColumn('ecole_id');
            $table->unique('email');
        });
    }
};
