<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->foreignId('caller_id'); // ID de l'appelant (enseignant ou parent)
            $table->string('caller_type'); // 'enseignant' ou 'parent'
            $table->foreignId('receiver_id'); // ID du receveur
            $table->string('receiver_type'); // 'enseignant' ou 'parent'
            $table->enum('type', ['audio', 'video'])->default('audio');
            $table->enum('status', ['ringing', 'accepted', 'rejected', 'missed', 'ended'])->default('ringing');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_seconds')->nullable(); // Durée en secondes
            $table->text('rejection_reason')->nullable(); // Raison du rejet
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
