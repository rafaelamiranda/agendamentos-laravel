<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agendamentos', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete: agendamento é histórico financeiro. Apagar um
            // profissional, serviço ou cliente com agendamentos deve falhar;
            // o caminho certo é desativar o cadastro.
            $table->foreignId('profissional_id')->constrained('profissionais')->restrictOnDelete();
            $table->foreignId('servico_id')->constrained('servicos')->restrictOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->dateTime('inicio');
            $table->dateTime('fim');
            $table->string('status', 20)->default('pendente');
            // Copiados do serviço no momento da reserva: se o preço mudar
            // depois, o que o cliente contratou continua valendo.
            $table->unsignedInteger('valor_centavos');
            $table->unsignedInteger('sinal_centavos');
            $table->dateTime('expira_em')->nullable();
            $table->dateTime('cancelado_em')->nullable();
            $table->timestamps();

            // Atende a busca de conflitos: "agendamentos deste profissional
            // que cruzam o intervalo X", usada na disponibilidade e na reserva.
            $table->index(['profissional_id', 'inicio', 'fim']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agendamentos');
    }
};
