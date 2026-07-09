<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecoles', function (Blueprint $table) {
            $table->string('logo')->nullable()->after('code');
            $table->string('image_fond')->nullable()->after('logo');
            $table->string('ville')->nullable()->after('image_fond');
            $table->text('description')->nullable()->after('ville');
            $table->string('email_admin')->nullable()->after('description');
            $table->string('password_admin')->nullable()->after('email_admin');
        });
    }

    public function down(): void
    {
        Schema::table('ecoles', function (Blueprint $table) {
            $table->dropColumn(['logo', 'image_fond', 'ville', 'description', 'email_admin', 'password_admin']);
        });
    }
};
