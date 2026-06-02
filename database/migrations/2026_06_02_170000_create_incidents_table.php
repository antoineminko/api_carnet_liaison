<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('eleve_id');
            $table->unsignedBigInteger('enseignant_id');
            $table->unsignedBigInteger('classe_id')->nullable();
            $table->enum('type', ['desordre', 'bavardage', 'bagarre', 'injure', 'retenu', 'autre']);
            $table->text('description')->nullable();
            $table->date('date');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('eleve_id')->references('id')->on('eleves')->onDelete('cascade');
            $table->foreign('enseignant_id')->references('id')->on('enseignants')->onDelete('cascade');
            $table->foreign('classe_id')->references('id')->on('classes')->onDelete('set null');
            
            $table->index(['eleve_id', 'date']);
            $table->index('is_read');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
