# Agendamentos

Sistema de agendamentos para estabelecimentos de serviço (salão, clínica, estúdio). O cliente vê os horários livres de um profissional, reserva um serviço, paga um sinal via Pix e recebe lembretes. O administrador gerencia profissionais, serviços, jornadas, bloqueios e a agenda.

> Projeto de portfólio em construção. A documentação completa da API vem na etapa final.

## Stack

- Laravel 13, PHP 8.5 (Sail), MySQL 8.4, Redis (filas)
- Painel: starter kit oficial React (Inertia + React + TypeScript)
- Testes com Pest; qualidade com Laravel Pint e Larastan (nível 5)
- CI no GitHub Actions: um job para Pint + PHPStan e outro para o Pest em MySQL

## Como rodar

Requisitos: Docker. O PHP não precisa estar instalado na máquina.

```bash
cp .env.example .env

# Instala as dependências PHP usando um container descartável
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd)":/var/www/html -w /var/www/html \
    laravelsail/php84-composer:latest composer install

./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

| Serviço   | Endereço                |
|-----------|-------------------------|
| Aplicação | http://localhost:8080   |
| Mailpit   | http://localhost:8025   |

A aplicação usa `APP_PORT=8080` para não disputar a porta 80 com outros containers.

Usuário administrador do seeder: `admin@agendamentos.test`, senha `password`.

**SELinux (Fedora/RHEL):** se os containers não conseguirem ler ou gravar nos volumes, adicione `:z` aos volumes de bind no `compose.yaml` (por exemplo `'.:/var/www/html:z'`).

## Qualidade e testes

```bash
./vendor/bin/sail pint            # estilo
./vendor/bin/sail php vendor/bin/phpstan analyse
./vendor/bin/sail artisan test    # roda no banco "testing" do MySQL do Sail
```

## API

| Método | Rota | O que faz |
|---|---|---|
| `GET` | `/api/profissionais/{id}/disponibilidade?servico_id=&data=AAAA-MM-DD` | Horários livres do profissional para o serviço no dia |
| `POST` | `/api/agendamentos` | Reserva um horário; cria o agendamento `pendente` (5 por minuto por IP) |

Exemplo de reserva:

```json
POST /api/agendamentos
{
  "profissional_id": 1,
  "servico_id": 1,
  "inicio": "2026-11-30T10:00:00-03:00",
  "cliente": { "nome": "Maria Silva", "email": "maria@example.com", "telefone": "(11) 91234-5678" }
}
```

| Resposta | Quando |
|---|---|
| `201` | Reserva criada, com `expira_em` = agora + prazo do sinal |
| `409` | Horário indisponível: ocupado, fora da grade ou da jornada, fora da antecedência mínima ou máxima |
| `422` | Dados inválidos, ou o profissional não realiza o serviço |
| `429` | Limite de reservas por IP atingido |

## Modelo de domínio

| Tabela | Conteúdo |
|---|---|
| `profissionais` | nome, e-mail único, ativo |
| `servicos` | nome, descrição, duração, preço e sinal (centavos), ativo |
| `profissional_servico` | quais serviços cada profissional atende (chave primária composta) |
| `jornadas` | faixa de trabalho por dia da semana (0 = domingo … 6 = sábado) |
| `bloqueios` | folgas, férias, almoço |
| `clientes` | nome, e-mail único, telefone |
| `agendamentos` | profissional, serviço, cliente, início/fim, status, valores copiados do serviço, prazo do sinal |

Status do agendamento (`App\Enums\StatusAgendamento`): `pendente` (aguardando sinal, expira), `confirmado`, `concluido`, `cancelado`, `expirado`.

## Decisões técnicas

**Fuso horário único (America/Sao_Paulo).** O sistema atende um estabelecimento num único fuso. Por isso o `timezone` da aplicação é fixo em `config/app.php`, e as datas são gravadas e exibidas no horário de Brasília. Assim a agenda no banco é legível e as consultas por dia não precisam de conversão. Como o Brasil não tem mais horário de verão, não existem horas duplicadas ou puladas. Atender vários fusos pediria gravar em UTC e converter na exibição; aqui seria complexidade sem uso.

**"Agora" vem do PHP, não do MySQL.** As consultas usam `now()` do Laravel em vez de `NOW()` do banco. Assim valem o fuso da aplicação e o relógio congelado dos testes (`freezeTime`/`travel`).

**Datas imutáveis.** O starter kit configura `Date::use(CarbonImmutable::class)`. Em código de agenda, `$inicio->addMinutes(30)` devolve uma data nova e não altera `$inicio` sem querer.

**Valores em centavos (inteiros).** `float` não representa valores como 0,10 com exatidão, e somas e percentuais acumulariam erro de arredondamento. Inteiros em centavos são exatos e são o formato que gateways de pagamento costumam usar.

**Preço e sinal copiados para o agendamento.** O agendamento guarda `valor_centavos` e `sinal_centavos` do momento da reserva. Se o preço do serviço mudar depois, o que o cliente contratou continua valendo.

**Intervalos semiabertos `[inicio, fim)`.** O instante final não pertence ao intervalo. Dois intervalos se sobrepõem quando `A.inicio < B.fim` e `A.fim > B.inicio` (trait `TemIntervalo`, scope `sobrepondo`). Com isso, um atendimento das 10:00 às 11:00 não conflita com outro das 11:00 às 12:00, e os horários podem ser oferecidos em sequência sem folga artificial.

**Pendente vencido não ocupa horário, mesmo antes do job.** O scope `ocupandoHorario()` considera confirmados, concluídos e pendentes com `expira_em` no futuro. Se o job de expiração atrasar, o horário já volta a ficar livre na disponibilidade.

**Disponibilidade com grade fixa.** `CalculadoraDisponibilidade` oferece horários num passo fixo (`AGENDAMENTO_INTERVALO_GRADE_MINUTOS`, padrão 30), independente da duração do serviço. Um horário entra se o atendimento inteiro cabe na jornada (terminar exatamente no fim é permitido), não cruza bloqueio nem agendamento que ocupa horário e começa depois de agora + antecedência mínima (`AGENDAMENTO_ANTECEDENCIA_MINIMA_MINUTOS`, padrão 120). A grade fixa deixa a agenda previsível. O custo é que, depois de um serviço de 45 min às 9:00, o próximo horário oferecido é 10:00, e não 9:45.

**Duas consultas por dia, cruzamento em memória.** A calculadora carrega uma vez os bloqueios e os agendamentos do dia e testa cada horário candidato em memória, com `sobrepoe()`, a mesma regra semiaberta do scope. São 4 consultas no total, não importa quantos horários ou ocupações existam; um teste garante isso. Fazer uma consulta por candidato seria o problema clássico de N+1.

**Disponibilidade é informativa; a garantia é a reserva.** Entre o cliente ver um horário livre e confirmar, outra pessoa pode reservá-lo. Por isso a calculadora não "segura" nada; a reserva confere o conflito de novo, dentro de uma transação com lock de linha.

**Jornadas não cruzam a meia-noite.** Cada jornada é uma faixa dentro do dia (`inicio < fim`). Turnos noturnos seriam duas jornadas, uma em cada dia. Não é o caso de salão, clínica ou estúdio.

**Lock na linha do profissional, não nos agendamentos.** A reserva roda numa transação que começa com `SELECT ... FOR UPDATE` na linha do profissional (`ReservarHorario`). Duas reservas para o mesmo profissional passam uma de cada vez: a segunda espera o commit da primeira e, ao conferir a agenda, já enxerga o agendamento novo. Travar os agendamentos em conflito não resolve. Quando o horário está livre não há linha para travar, e um `FOR UPDATE` sem resultado não impede o insert de outra transação. A linha do profissional sempre existe e funciona como mutex por profissional, sem bloquear a agenda dos outros.

**O lock é a primeira consulta da transação.** No REPEATABLE READ do MySQL, o snapshot da transação nasce na primeira leitura comum. Se alguma leitura viesse antes do lock, a reserva que esperou conferiria a agenda com uma foto antiga e não veria a reserva concorrente. O teste de concorrência prova isso: com uma leitura antes do lock, ele detecta um agendamento duplicado.

**A foreign key não substitui o lock.** O `INSERT` em `agendamentos` pede um lock compartilhado na linha do profissional por causa da foreign key, então ele também espera. Mas espera depois de a agenda já ter sido conferida com dados antigos, e por isso gera duplicata. O lock explícito faz a espera acontecer antes da conferência.

**Testes de concorrência com processo separado.** `tests/Concorrencia` segura o lock numa segunda conexão e dispara a reserva num outro processo PHP, que fica esperando. Enquanto isso, o teste ocupa o horário e faz commit, e então confere que o outro processo recusou. Essa suíte usa `DatabaseTruncation` em vez de `RefreshDatabase`, porque dados dentro de uma transação não confirmada seriam invisíveis para a outra conexão.

**A disponibilidade não fica no Form Request.** O Form Request valida o formato e o vínculo profissional–serviço. Se o horário está livre depende do estado da agenda, e só é conferido com segurança dentro da transação, com o lock.

**409 para horário indisponível, 422 para dados inválidos.** Um horário ocupado não é erro de preenchimento: o pedido é válido, mas conflita com o estado atual da agenda. O `409 Conflict` deixa o front tratar os dois casos de forma diferente ("escolha outro horário" e "corrija o formulário").

**Datas da API em ISO 8601 com deslocamento.** A API devolve `2026-11-30T10:00:00-03:00` e aceita qualquer fuso na entrada, convertendo para o horário de Brasília antes de calcular o dia. `13:00Z` vira 10:00 em Brasília.

**Cliente identificado pelo e-mail, sem conta.** A reserva faz `updateOrCreate` pelo e-mail (normalizado em minúsculas), e nome e telefone ficam com os dados mais recentes. Assim o cliente reserva sem cadastro e o histórico fica agrupado.

**Limite de reservas por IP.** Cada pendente segura um horário durante o prazo do sinal. Sem limite, um único IP poderia ocupar a agenda inteira sem pagar. O limite é de 5 reservas por minuto por IP (`RateLimiter` `reservas`).

**Antecedência mínima e máxima.** Um horário só pode ser reservado a partir de agora + 120 min e até hoje + 60 dias (configuráveis). O limite máximo evita reservas para meses à frente, quando preços e jornadas ainda podem mudar.

**Mensagens em português sem pacote.** `lang/pt_BR` foi traduzido à mão (validação, autenticação, senhas, paginação), e os nomes dos campos foram mapeados ("O campo serviço é obrigatório"). O idioma de fallback é `en`, para que uma chave sem tradução apareça em inglês, nunca como texto cru.

**`restrictOnDelete` nos agendamentos.** Agendamento é histórico financeiro. Apagar profissional, serviço ou cliente que tenha agendamentos falha no banco; o caminho certo é desativar o cadastro (`ativo`). Jornadas, bloqueios e o vínculo com serviços são dados do próprio profissional e usam `cascadeOnDelete`.

**Índice `(profissional_id, inicio, fim)`.** Atende a consulta mais frequente do sistema: "agendamentos deste profissional que cruzam este intervalo". Ela é usada no cálculo de disponibilidade e na checagem de conflito da reserva.

**Testes em MySQL, não em SQLite.** A reserva usa lock de linha (`SELECT ... FOR UPDATE`), que o SQLite não implementa. Testar no mesmo banco de produção evita que um teste passe por acaso. Por isso o CI sobe um service container MySQL.

**Testes sem build do front.** O `TestCase` chama `withoutVite()`. Os testes cobrem o backend e não dependem de `public/build`, então o job de testes do CI não precisa de Node.

**Código em português, com `$table` explícito.** Domínio, tabelas e testes estão em português. Os models declaram `$table` porque a pluralização do Laravel é em inglês ("profissionals"). Pelo mesmo motivo, as foreign keys informam a tabela (`constrained('profissionais')`).

**Migrations não dependem do código da aplicação.** O default `'pendente'` da coluna `status` é literal, e não `StatusAgendamento::Pendente->value`. Migrations precisam continuar rodando mesmo que o enum mude no futuro.
