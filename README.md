# Taskly

[![CI](https://github.com/MaiaRocha/taskly/actions/workflows/ci.yml/badge.svg)](https://github.com/MaiaRocha/taskly/actions/workflows/ci.yml)

Gestão de tarefas por projeto, com List e Kanban, tags, anexos privados e histórico de atividades. Desenvolvido como desafio técnico full-stack — Laravel 13 + Vue 3.

## Sobre o projeto

Taskly é uma aplicação web para organizar tarefas dentro de projetos. Um usuário autenticado cria projetos, cadastra tarefas com prazo e descrição, organiza tags, anexa arquivos e acompanha o andamento pela visualização em Lista ou em Kanban — com busca, filtros, métricas por projeto e um histórico de atividades que registra as mudanças reais de cada tarefa.

## Funcionalidades

- Cadastro, login e logout com sessão persistente (Fortify + Sanctum, cookie de sessão — sem JWT, sem token em `localStorage`).
- Projetos: CRUD completo, com ownership por usuário.
- Tarefas: CRUD completo, com descrição curta/completa, prazo e 4 status (`not_started`, `in_progress`, `completed`, `cancelled`).
- Detecção de tarefa atrasada (`overdue`), sempre derivada no backend — nunca uma coluna própria.
- Visualização em Lista e em Kanban, com mudança de status inline (clique na badge, na Lista) e por menu/drag-and-drop (no Kanban).
- Busca e filtros sobre as tarefas do projeto.
- Métricas por projeto (contagens por status, atrasadas etc.).
- Tags por usuário (até 5 por tarefa, com criação inline).
- Anexos privados por tarefa (até 10 por tarefa, 5 arquivos por requisição, 5.120.000 bytes/~5 MB por arquivo, tipos `jpg`, `jpeg`, `png`, `webp`, `pdf`, `txt`, `doc`, `docx`, `xls`, `xlsx` — acesso só ao dono da tarefa).
- Histórico de atividades por tarefa (somente leitura, paginado), registrando criação, mudança de status, mudança de prazo, mudança de tags e adição/remoção de anexos.
- `DemoSeeder` opcional para popular uma conta de demonstração controlada.

Autorização por ownership em todas as entidades (Projects, Tasks, Tags, Attachments), aplicada por Policies no backend — o frontend nunca é fonte de verdade para isso.

## Stack & Arquitetura

| Backend | Frontend |
|---|---|
| Laravel 13 (PHP 8.3) | Vue 3 + TypeScript |
| MariaDB + Eloquent | Pinia |
| Fortify + Sanctum (sessão/cookie) | Vue Router |
| Form Requests, Policies, API Resources | Axios |
| PHP Enums | Vite |
| Actions pontuais (só onde justificado) | Tailwind CSS |
| Pest, Pint | — |

Arquitetura: monolito modular com REST API, consumido por uma SPA Vue. SPA e API são servidas na mesma origem no ambiente local, evitando chamadas cross-origin no fluxo normal:

```
Vue 3 SPA → REST API (Laravel) → Eloquent → MariaDB
```

Controllers ficam finos: validação em Form Requests, autorização em Policies, formatação de resposta em API Resources, regras de status/enum em PHP Enums, e Actions só para operações que realmente justificam uma classe própria (ex.: exclusão de tarefa com limpeza de anexos, registro de atividades). Detalhes e histórico das decisões em [`docs/DECISIONS.md`](docs/DECISIONS.md); especificação funcional completa em [`docs/SPEC.md`](docs/SPEC.md).

## Como rodar localmente

### Requisitos

- PHP 8.3+ e Composer
- Node.js 20.19+ e npm
- Docker (para subir o MariaDB via Docker Compose) — ou uma instância MariaDB acessível pelas mesmas variáveis de ambiente

### Passo a passo

```bash
# 1. Clonar e entrar no diretório
git clone https://github.com/MaiaRocha/taskly.git
cd taskly

# 2. Copiar o arquivo de ambiente
cp .env.example .env

# 3. Subir o MariaDB (lê DB_DATABASE/DB_USERNAME/DB_PASSWORD/DB_ROOT_PASSWORD/DB_PORT do .env acima)
docker compose up -d

# 4. Instalar dependências PHP
composer install

# 5. Gerar a APP_KEY
php artisan key:generate

# 6. Rodar as migrations
php artisan migrate

# 7. Instalar dependências JS
npm ci

# 8. Gerar os assets do frontend
npm run build

# 9. Subir o servidor da aplicação
php artisan serve
```

Acesse **http://localhost:8000** (mesmo valor de `APP_URL` em `.env.example`) — Laravel serve a SPA e a API na mesma origem, não há um servidor de frontend separado para acessar.

Para desenvolvimento ativo com hot reload, o passo 8 pode ser substituído por `composer run dev` (orquestra `php artisan dev`, que já sobe o servidor PHP e o Vite juntos) em vez de `npm run build` + `php artisan serve`.

**Atalho opcional:** depois dos passos 1–3 acima (repositório clonado, `.env` criado, MariaDB no ar), o script `composer run setup` substitui os passos 4–8 (`composer install`, `key:generate`, `migrate --force`, `npm install --ignore-scripts`, `npm run build`) em um único comando:

```bash
composer run setup
```

Ele não sobe o banco sozinho — por isso os passos 1–3 continuam obrigatórios antes. Depois dele, o passo 9 (`php artisan serve`) segue normalmente. O fluxo manual completo é o recomendado para quem quer entender/reproduzir cada etapa do ambiente.

> Nunca commite o arquivo `.env` (já coberto pelo `.gitignore`) nem a `APP_KEY` gerada.

## Dados de demonstração (DemoSeeder)

Opcional. Popula uma conta de demonstração com um cenário de equipe de desenvolvimento de software: **3 projetos**, **6 tags** e **12 tarefas**, cobrindo todos os status, prazos futuros/passados/sem prazo e tarefas atrasadas — com histórico de atividades coerente (gerado por mutações reais sobre as tarefas, nunca inserido artificialmente).

Antes de rodar, defina no seu `.env` (exemplo só para uso local):

```env
DEMO_USER_NAME="Demo User"
DEMO_USER_EMAIL="demo@taskly.local"
DEMO_USER_PASSWORD="local-dev-only-ChangeMe123"
```

`DEMO_USER_NAME` já tem um valor padrão seguro ("Demo User") se omitido; `DEMO_USER_EMAIL` e `DEMO_USER_PASSWORD` não têm padrão — sem eles definidos, o comando abaixo falha antes de criar qualquer dado.

```bash
php artisan db:seed --class=DemoSeeder --force
```

- Nunca é executado automaticamente — o `DatabaseSeeder` padrão fica vazio.
- Rodar o comando de novo com o mesmo e-mail já existente não altera nem duplica nada.

## Testes e CI

Suíte automatizada (Pest) cobrindo autenticação, autorização entre usuários, CRUD de Projects/Tasks, Tags, Attachments, Kanban e Activity History.

```bash
php artisan test --compact
vendor/bin/pint --test
npx vue-tsc --noEmit
npm run build
```

CI real no GitHub Actions ([`.github/workflows/ci.yml`](.github/workflows/ci.yml)), rodando em todo push/PR para `main`, com dois jobs independentes:

- **Backend (PHP)** — Composer install, Pint (`--test`) e Pest.
- **Frontend (Node)** — `npm ci`, `vue-tsc` e build.

## Documentação complementar

- [`docs/SPEC.md`](docs/SPEC.md) — especificação funcional e técnica completa.
- [`docs/DECISIONS.md`](docs/DECISIONS.md) — decisões arquiteturais registradas por fase.
- [`docs/UI-UX.md`](docs/UI-UX.md) — direção visual e critérios de interface.
- [`docs/AI-USAGE.md`](docs/AI-USAGE.md) — uso de IA no desenvolvimento, com exemplos reais de decisões e revisões.

## Uso de IA

O desenvolvimento seguiu um workflow assistido por IA, em checkpoints pequenos e supervisionados: Claude Code como agente principal de implementação, ChatGPT como apoio de planejamento e revisão fora do repositório, sempre com revisão humana e testes/validação manual antes de cada fase ser considerada concluída. Detalhes e exemplos reais em [`docs/AI-USAGE.md`](docs/AI-USAGE.md).

## Limitações e decisões conscientes

- Esta entrega foi preparada para execução local; o deploy público foi tratado como opcional no escopo do desafio.
- O Kanban muda o status de uma tarefa (menu ou drag-and-drop), mas não oferece reorder manual dentro de uma coluna — o backend não expõe esse contrato ainda.
- As tarefas de um projeto não são paginadas — o escopo atual carrega a coleção completa do projeto de uma vez.
- Anexos usam o filesystem local do servidor neste ambiente.

## Como avaliar rapidamente

1. Suba o ambiente (seção "Como rodar localmente").
2. Opcionalmente, rode o `DemoSeeder`.
3. Entre com a conta configurada.
4. Alterne entre List e Kanban.
5. Use busca e filtros, e veja as métricas do projeto.
6. Abra uma tarefa e veja o histórico de atividades.
7. Mude o status de uma tarefa (badge na Lista ou Kanban) e veja o evento aparecer no histórico.
8. Rode os testes (seção "Testes e CI").

## Autor

Maia Rocha
GitHub: [https://github.com/MaiaRocha](https://github.com/MaiaRocha)
