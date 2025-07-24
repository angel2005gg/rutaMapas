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
        Schema::create('usuario_comunidad', function (Blueprint $table) {
            // Removemos el id ya que usaremos una clave primaria compuesta
            $table->foreignId('usuario_id')
                  ->constrained('usuarios')
                  ->onDelete('cascade');
            $table->foreignId('comunidad_id')
                  ->constrained('comunidades')
                  ->onDelete('cascade');
            $table->timestamps();
            
            // Definimos la clave primaria compuesta
            $table->primary(['usuario_id', 'comunidad_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuario_comunidad');
    }
};
