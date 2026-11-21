<?php

return [

    /*
    | Quanto tempo antes do início um horário ainda pode ser reservado.
    | Evita reservas "para daqui a 5 minutos" que o estabelecimento não
    | conseguiria atender.
    */
    'antecedencia_minima_minutos' => (int) env('AGENDAMENTO_ANTECEDENCIA_MINIMA_MINUTOS', 120),

    /*
    | Até quantos dias à frente a agenda fica aberta. Limita reservas para
    | daqui a meses, quando preços e jornadas ainda podem mudar.
    */
    'antecedencia_maxima_dias' => (int) env('AGENDAMENTO_ANTECEDENCIA_MAXIMA_DIAS', 60),

    /*
    | Passo da grade de horários oferecidos (09:00, 09:30, 10:00...). É fixo
    | e independe da duração do serviço, para a agenda ficar previsível
    | para o cliente e para o profissional.
    */
    'intervalo_grade_minutos' => (int) env('AGENDAMENTO_INTERVALO_GRADE_MINUTOS', 30),

    /*
    | Prazo para pagar o sinal. Enquanto não vence, o agendamento pendente
    | segura o horário; depois disso o horário volta a ficar livre.
    */
    'prazo_pagamento_minutos' => (int) env('AGENDAMENTO_PRAZO_PAGAMENTO_MINUTOS', 15),

];
