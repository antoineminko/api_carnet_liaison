<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->foreignId('reporter_id'); // ID du signaleur (enseignant ou parent)
            $table->string('reporter_type'); // 'enseignant' ou 'parent'
            $table->foreignId('reported_id'); // ID de la personne signalée
            $table->string('reported_type'); // 'enseignant' ou 'parent'
            $table->enum('reason', ['harassment', 'inappropriate_content', 'spam', 'fake_account', 'other']);
            $table->text('description')->nullable(); // Description détaillée
            $table->text('evidence')->nullable(); // Preuves (liens, captures, etc.)
            $table->enum('status', ['pending', 'in_review', 'resolved', 'rejected'])->default('pending');
            $table->text('admin_notes')->nullable(); // Notes de l'admin
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable(); // Admin qui a résolu
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
