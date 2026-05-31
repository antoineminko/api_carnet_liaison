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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('user_type'); // 'enseignant', 'parent', 'admin'
            $table->unsignedBigInteger('user_id');
            $table->string('type'); // 'message', 'appointment', etc.
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Additional data like conversation_id
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
