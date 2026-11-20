<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profissional_servico', function (Blueprint $table) {
            // Os nomes das tabelas são informados porque o Laravel pluralizaria
            // "profissional" em inglês ("profissionals").
            $table->foreignId('profissional_id')->constrained('profissionais')->cascadeOnDelete();
            $table->foreignId('servico_id')->constrained('servicos')->cascadeOnDelete();

            // Chave composta: impede vincular o mesmo serviço duas vezes ao
            // mesmo profissional sem precisar de um id artificial.
            $table->primary(['profissional_id', 'servico_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profissional_servico');
    }
};
