<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jornadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profissional_id')->constrained('profissionais')->cascadeOnDelete();
            // 0 = domingo ... 6 = sábado, mesma convenção do Carbon (dayOfWeek).
            $table->unsignedTinyInteger('dia_semana');
            $table->time('inicio');
            $table->time('fim');
            $table->timestamps();

            $table->index(['profissional_id', 'dia_semana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jornadas');
    }
};
