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

**`restrictOnDelete` nos agendamentos.** Agendamento é histórico financeiro. Apagar profissional, serviço ou cliente que tenha agendamentos falha no banco; o caminho certo é desativar o cadastro (`ativo`). Jornadas, bloqueios e o vínculo com serviços são dados do próprio profissional e usam `cascadeOnDelete`.

**Índice `(profissional_id, inicio, fim)`.** Atende a consulta mais frequente do sistema: "agendamentos deste profissional que cruzam este intervalo". Ela é usada no cálculo de disponibilidade e na checagem de conflito da reserva.

**Testes em MySQL, não em SQLite.** A reserva vai usar lock de linha (`SELECT ... FOR UPDATE`), que o SQLite não implementa. Testar no mesmo banco de produção evita que um teste passe por acaso. Por isso o CI sobe um service container MySQL.

**Testes sem build do front.** O `TestCase` chama `withoutVite()`. Os testes cobrem o backend e não dependem de `public/build`, então o job de testes do CI não precisa de Node.

**Código em português, com `$table` explícito.** Domínio, tabelas e testes estão em português. Os models declaram `$table` porque a pluralização do Laravel é em inglês ("profissionals"). Pelo mesmo motivo, as foreign keys informam a tabela (`constrained('profissionais')`).

**Migrations não dependem do código da aplicação.** O default `'pendente'` da coluna `status` é literal, e não `StatusAgendamento::Pendente->value`. Migrations precisam continuar rodando mesmo que o enum mude no futuro.
