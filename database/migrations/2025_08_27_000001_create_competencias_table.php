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
        Schema::create('competencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comunidad_id')
                  ->constrained('comunidades')
                  ->onDelete('cascade');
            $table->unsignedInteger('duracion_dias');
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin')->index();
            $table->enum('estado', ['activa', 'cerrada'])->default('activa')->index();
            $table->foreignId('ganador_usuario_id')->nullable()
                  ->constrained('usuarios')
                  ->nullOnDelete();
            $table->foreignId('creada_por')->nullable()
                  ->constrained('usuarios')
                  ->nullOnDelete();
            $table->timestamps();

            $table->index(['comunidad_id', 'estado']);
            $table->index(['comunidad_id', 'fecha_fin']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competencias');
    }
};
