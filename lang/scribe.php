<?php

// Textos fixos da documentação gerada pelo Scribe, traduzidos para o
// português. O Scribe usa este arquivo no lugar do original do pacote.

return [
    'labels' => [
        'search' => 'Buscar',
        'base_url' => 'URL base',
    ],

    'auth' => [
        'none' => 'Esta API não exige autenticação: a reserva é pública e o webhook é validado pela assinatura.',
        'instruction' => [
            'query' => <<<'TEXT'
                Para autenticar, envie o parâmetro **`:parameterName`** na query string.
                TEXT,
            'body' => <<<'TEXT'
                Para autenticar, envie o parâmetro **`:parameterName`** no corpo da requisição.
                TEXT,
            'query_or_body' => <<<'TEXT'
                Para autenticar, envie o parâmetro **`:parameterName`** na query string ou no corpo da requisição.
                TEXT,
            'bearer' => <<<'TEXT'
                Para autenticar, envie o header **`Authorization`** com o valor **`"Bearer :placeholder"`**.
                TEXT,
            'basic' => <<<'TEXT'
                Para autenticar, envie o header **`Authorization`** no formato **`"Basic {credenciais}"`**,
                em que `{credenciais}` é usuário e senha separados por dois-pontos (:), em base64.
                TEXT,
            'header' => <<<'TEXT'
                Para autenticar, envie o header **`:parameterName`** com o valor **`":placeholder"`**.
                TEXT,
        ],
        'details' => <<<'TEXT'
            Os endpoints que exigem autenticação aparecem com a marca `requires authentication`.
            TEXT,
    ],

    'headings' => [
        'introduction' => 'Introdução',
        'auth' => 'Autenticação',
    ],

    'endpoint' => [
        'request' => 'Requisição',
        'headers' => 'Headers',
        'url_parameters' => 'Parâmetros de URL',
        'body_parameters' => 'Parâmetros do corpo',
        'query_parameters' => 'Parâmetros de query',
        'response' => 'Resposta',
        'response_fields' => 'Campos da resposta',
        'example_request' => 'Exemplo de requisição',
        'example_response' => 'Exemplo de resposta',
        'responses' => [
            'binary' => 'Dados binários',
            'empty' => 'Resposta vazia',
        ],
    ],

    'try_it_out' => [
        'open' => 'Testar ⚡',
        'cancel' => 'Cancelar 🛑',
        'send' => 'Enviar requisição 💥',
        'loading' => '⏱ Enviando...',
        'received_response' => 'Resposta recebida',
        'request_failed' => 'A requisição falhou com o erro',
        'error_help' => <<<'TEXT'
            Dica: confira sua conexão de rede.
            Se você mantém esta API, verifique se ela está no ar e se o CORS está habilitado.
            O console do navegador mostra mais detalhes.
            TEXT,
    ],

    'links' => [
        'postman' => 'Ver coleção do Postman',
        'openapi' => 'Ver especificação OpenAPI',
    ],
];
