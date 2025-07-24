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
        Schema::create('usuarios_retos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()
                  ->constrained('usuarios')
                  ->nullOnDelete();
            $table->string('reto', 100)->nullable();
            $table->boolean('completado')->default(false);
            $table->date('fecha_asignacion')->nullable();
            $table->date('fecha_completado')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuarios_retos');
    }
};
