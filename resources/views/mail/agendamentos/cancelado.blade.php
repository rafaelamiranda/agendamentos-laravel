<x-mail::message>
# Agendamento cancelado

Olá, {{ $cliente }}.
@if ($peloEstabelecimento)
Precisamos cancelar o seu agendamento. Pedimos desculpas pelo transtorno.
@else
Confirmamos o cancelamento do seu agendamento.
@endif

<x-mail::panel>
**{{ $servico }}** com {{ $profissional }}<br>
{{ ucfirst($quando) }}
</x-mail::panel>

**Sinal ({{ $sinal }}):** {{ $textoSinal }}

Quando quiser, é só fazer uma nova reserva.

{{ config('app.name') }}
</x-mail::message>
