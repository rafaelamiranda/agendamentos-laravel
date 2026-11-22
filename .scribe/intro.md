# Introdução

Consulta de horários livres, reserva com sinal via Pix e webhook de pagamento.

<aside>
    <strong>URL base</strong>: <code>http://localhost:8080</code>
</aside>

API pública do sistema de agendamentos. O fluxo do cliente é:

1. **Consultar horários livres** de um profissional para um serviço numa data.
2. **Reservar** um desses horários: a API cria um agendamento `pendente` e devolve o **Pix do sinal**.
3. **Pagar o sinal** em até 15 minutos. O Mercado Pago avisa o **webhook**, o agendamento passa a `confirmado` e o cliente recebe um e-mail com o link de cancelamento.

Sem pagamento no prazo, a reserva vence e o horário volta a ficar livre.

<aside>Datas e horas usam ISO 8601 com o deslocamento do fuso (<code>2026-11-30T10:00:00-03:00</code>). Valores monetários são inteiros em centavos (<code>3000</code> = R$ 30,00).</aside>

