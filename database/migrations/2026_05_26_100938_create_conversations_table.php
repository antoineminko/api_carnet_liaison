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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ecole_id')->nullable();
            $table->unsignedBigInteger('enseignant_id')->nullable();
            $table->unsignedBigInteger('parent_id');
            $table->timestamps();

            $table->unique(['ecole_id', 'enseignant_id', 'parent_id'], 'conv_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
