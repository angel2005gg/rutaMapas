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
        Schema::create('competencia_puntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competencia_id')
                  ->constrained('competencias')
                  ->onDelete('cascade');
            $table->foreignId('usuario_id')
                  ->constrained('usuarios')
                  ->onDelete('cascade');
            $table->integer('puntos')->default(0);
            $table->timestamps();

            $table->unique(['competencia_id', 'usuario_id']);
            $table->index(['competencia_id', 'puntos']);
            $table->index('usuario_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competencia_puntos');
    }
};
