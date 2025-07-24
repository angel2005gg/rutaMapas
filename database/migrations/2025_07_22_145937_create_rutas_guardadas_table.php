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
        Schema::create('rutas_guardadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()
                  ->constrained('usuarios')
                  ->nullOnDelete();
            $table->string('nombre_lugar', 100)->nullable();
            $table->text('direccion')->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rutas_guardadas');
    }
};
