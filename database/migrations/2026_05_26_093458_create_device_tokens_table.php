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
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id');
            $table->string('token')->unique();
            $table->string('platform')->nullable();
            $table->timestamps();

            // Foreign key (adjust 'parent_users' or 'parents' depending on the DB)
            // Assuming table is parents or parent_users based on earlier info
            // $table->foreign('parent_id')->references('id')->on('parent_users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
