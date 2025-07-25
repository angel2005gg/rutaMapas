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
        Schema::create('clasificaciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->nullable();
            $table->text('descripcion')->nullable();
            $table->integer('puntos_minimos')->nullable();
            $table->integer('puntos_maximos')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clasificaciones');
    }
};
