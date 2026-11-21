<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            // Quem cancelou decide a regra do sinal: o estabelecimento
            // sempre devolve; o cliente, só dentro do prazo.
            $table->string('cancelado_por', 20)->nullable()->after('cancelado_em');
            // Marca do lembrete: o job roda várias vezes por hora e não
            // pode mandar o mesmo lembrete duas vezes.
            $table->dateTime('lembrete_enviado_em')->nullable()->after('cancelado_por');
        });

        Schema::table('pagamentos', function (Blueprint $table) {
            $table->dateTime('estornado_em')->nullable()->after('aprovado_em');
        });
    }

    public function down(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->dropColumn(['cancelado_por', 'lembrete_enviado_em']);
        });

        Schema::table('pagamentos', function (Blueprint $table) {
            $table->dropColumn('estornado_em');
        });
    }
};
