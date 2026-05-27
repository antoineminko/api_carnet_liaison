<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enseignant_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_id')->constrained('parent_users')->onDelete('cascade');
            $table->foreignId('eleve_id')->nullable()->constrained('eleves')->onDelete('cascade');
            
            $table->dateTime('date_heure');
            $table->enum('type', ['physique', 'video'])->default('physique');
            $table->string('lien_video')->nullable();
            
            $table->enum('statut', ['en_attente', 'accepte', 'refuse', 'reporte'])->default('en_attente');
            $table->text('motif')->nullable();
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('appointments');
    }
};
