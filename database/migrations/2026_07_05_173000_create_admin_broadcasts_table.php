<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ecole_id');
            $table->string('type');
            $table->string('titre');
            $table->text('contenu');
            $table->string('fichier_url')->nullable();
            $table->json('cibles');
            $table->timestamps();

            $table->foreign('ecole_id')->references('id')->on('ecoles')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_broadcasts');
    }
};
