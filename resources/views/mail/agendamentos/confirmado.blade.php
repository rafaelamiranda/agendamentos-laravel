<x-mail::message>
# Agendamento confirmado

Olá, {{ $cliente }}! Recebemos o sinal e seu horário está garantido.

<x-mail::panel>
**{{ $servico }}** com {{ $profissional }}<br>
{{ ucfirst($quando) }}
</x-mail::panel>

| | |
|:--|--:|
| Valor do serviço | {{ $valor }} |
| Sinal pago | {{ $sinal }} |
| **A pagar no dia** | **{{ $restante }}** |

Precisa desmarcar? Cancelando com pelo menos {{ $prazoCancelamentoHoras }} horas de antecedência, o sinal é devolvido. Depois disso o cancelamento ainda é possível, mas o sinal não é devolvido.

<x-mail::button :url="$linkCancelamento" color="error">
Cancelar agendamento
</x-mail::button>

Até lá!<br>
{{ config('app.name') }}
</x-mail::message>
