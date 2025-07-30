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
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->nullable();
            $table->string('correo', 150)->nullable()->unique();
            $table->text('foto_perfil')->nullable();
            $table->string('google_uid', 255)->nullable()->unique();
            $table->integer('racha_actual')->default(0);
            $table->integer('puntaje')->default(0); // ← AGREGAR ESTA LÍNEA
            $table->foreignId('clasificacion_id')->nullable()
                  ->constrained('clasificaciones')
                  ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
