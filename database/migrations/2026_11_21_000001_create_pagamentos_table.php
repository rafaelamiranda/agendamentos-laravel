<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagamentos', function (Blueprint $table) {
            $table->id();
            // Um pagamento de sinal por agendamento.
            $table->foreignId('agendamento_id')->unique()->constrained('agendamentos')->restrictOnDelete();
            $table->string('gateway', 30);
            // Id do pagamento no gateway. Único: é por ele que o webhook
            // encontra o pagamento, e a restrição impede duplicatas.
            $table->string('gateway_id', 64)->unique();
            $table->string('status', 20);
            $table->unsignedInteger('valor_centavos');
            $table->text('pix_copia_e_cola');
            $table->longText('pix_qr_code_base64');
            $table->dateTime('expira_em');
            $table->dateTime('aprovado_em')->nullable();
            // Pago, mas sem como confirmar (cancelado, horário ocupado, valor
            // divergente): o dinheiro precisa voltar para o cliente.
            $table->boolean('requer_estorno')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagamentos');
    }
};
