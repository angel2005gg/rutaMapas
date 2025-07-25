<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('correo');
            $table->string('codigo', 6);
            $table->string('password_hash'); // La contraseña que quiere usar
            $table->string('nombre')->nullable(); // Nombre temporal
            $table->timestamp('expires_at');
            $table->boolean('usado')->default(false);
            $table->timestamps();
            
            $table->index(['correo', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verifications');
    }
};