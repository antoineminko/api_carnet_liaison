<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_signaling', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['offer', 'answer', 'ice_candidate']);
            $table->text('sdp')->nullable(); // Pour offer/answer
            $table->string('sdp_mid', 255)->nullable(); // Pour ICE candidate
            $table->integer('sdp_m_line_index')->nullable(); // Pour ICE candidate
            $table->text('candidate')->nullable(); // Pour ICE candidate
            $table->boolean('processed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_signaling');
    }
};
