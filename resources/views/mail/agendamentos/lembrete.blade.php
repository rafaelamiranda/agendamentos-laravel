<x-mail::message>
# Seu horário está chegando

Olá, {{ $cliente }}! Passando para lembrar do seu agendamento:

<x-mail::panel>
**{{ $servico }}** com {{ $profissional }}<br>
{{ ucfirst($quando) }}
</x-mail::panel>

O sinal de {{ $sinal }} já foi pago; no dia, falta acertar **{{ $restante }}**.

Se não puder vir, cancele pelo botão abaixo para liberar o horário. Neste momento o cancelamento ainda é possível, mas pode já estar fora do prazo de {{ $prazoCancelamentoHoras }} horas para devolução do sinal.

<x-mail::button :url="$linkCancelamento" color="error">
Cancelar agendamento
</x-mail::button>

Até breve!<br>
{{ config('app.name') }}
</x-mail::message>
