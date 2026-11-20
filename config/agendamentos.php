<?php

return [

    /*
    | Quanto tempo antes do início um horário ainda pode ser reservado.
    | Evita reservas "para daqui a 5 minutos" que o estabelecimento não
    | conseguiria atender.
    */
    'antecedencia_minima_minutos' => (int) env('AGENDAMENTO_ANTECEDENCIA_MINIMA_MINUTOS', 120),

    /*
    | Passo da grade de horários oferecidos (09:00, 09:30, 10:00...). É fixo
    | e independe da duração do serviço, para a agenda ficar previsível
    | para o cliente e para o profissional.
    */
    'intervalo_grade_minutos' => (int) env('AGENDAMENTO_INTERVALO_GRADE_MINUTOS', 30),

];
