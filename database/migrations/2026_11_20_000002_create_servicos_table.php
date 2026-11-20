<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->unsignedSmallInteger('duracao_minutos');
            // Valores em centavos (inteiros): float não representa 0,10 com
            // exatidão e somas de preços acumulariam erro de arredondamento.
            $table->unsignedInteger('preco_centavos');
            $table->unsignedInteger('sinal_centavos');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicos');
    }
};
