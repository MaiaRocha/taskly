# Taskly — Decisões Técnicas

## Status

Este documento registra as decisões técnicas aprovadas para o desenvolvimento do Taskly.

Ele deve ser tratado como uma das fontes de verdade do projeto, juntamente com:

- `docs/SPEC.md`
- `docs/UI-UX.md`

Mudanças de arquitetura, stack, autenticação, persistência, dependências relevantes ou padrões estruturais devem ser discutidas e aprovadas antes da implementação.

---

## 1. Arquitetura

O Taskly será desenvolvido como um **Modular Monolith**.

Frontend e backend permanecerão no mesmo repositório e, inicialmente, no mesmo deploy principal.

A comunicação entre frontend e backend será feita através de uma fronteira REST explícita:

```text
Vue 3 SPA
    ↓ HTTP / JSON
Laravel REST API
    ↓
Eloquent
    ↓
MariaDB
```

A escolha por REST é intencional para demonstrar experiência com APIs e integrações, sem introduzir a complexidade operacional de uma arquitetura distribuída.

### Não utilizar

- Microservices
- Inertia.js
- Repository Pattern
- CQRS
- Event Sourcing

Esses padrões não devem ser adicionados sem uma necessidade concreta e aprovação prévia.

---

## 2. Backend

Stack principal:

- PHP 8.3+
- Laravel 13
- MariaDB
- Eloquent ORM
- Form Requests
- Policies
- API Resources
- PHP Enums
- Laravel Filesystem
- Pest
- Laravel Pint

Os Controllers devem permanecer enxutos e focados em orquestração.

Actions podem ser utilizadas quando encapsularem uma operação de negócio relevante, por exemplo:

- `MoveTask`
- `StoreAttachment`
- `DeleteTask`
- `DeleteProject`

Não criar uma camada de Services ou Actions apenas por convenção.

---

## 3. Frontend

O frontend será uma SPA independente da renderização Laravel.

Stack:

- Vue 3
- TypeScript
- Vue Router
- Pinia
- Axios
- Vite
- Tailwind CSS
- Lucide Icons

`shadcn-vue` poderá ser utilizado de forma seletiva, desde que seus componentes sejam adaptados à identidade visual do Taskly.

Não utilizar Inertia.js.

O Axios deverá possuir configuração centralizada.

Pinia será utilizado apenas para estado global que realmente precise ser compartilhado.

---

## 4. Autenticação

A autenticação da SPA própria será baseada em:

- Laravel Fortify
- Laravel Sanctum
- sessão Laravel
- cookies HttpOnly
- proteção CSRF

Fluxo esperado:

```text
GET /sanctum/csrf-cookie
        ↓
POST /login
        ↓
Laravel Fortify
        ↓
Sessão autenticada
        ↓
Cookie HttpOnly
        ↓
REST API protegida
```

Não utilizar:

- JWT próprio
- Bearer Token armazenado em `localStorage`
- OAuth para login do Taskly
- Laravel Passport

Sanctum será utilizado no modo **stateful SPA authentication**.

Autenticação e autorização devem ser tratadas como responsabilidades diferentes.

---

## 5. Autorização e segurança

Os usuários só poderão acessar seus próprios dados.

Policies devem impedir acesso horizontal entre usuários.

A aplicação não deve confiar em valores sensíveis enviados pelo frontend, incluindo:

- `user_id`
- ownership de projetos
- ownership de tarefas
- `position`
- `completed_at`

Esses valores devem ser determinados ou validados pelo backend.

---

## 6. Domínio

Entidades principais:

- User
- Project
- Task
- Tag
- Attachment

### Project

Campos principais:

- id
- user_id
- name
- description
- color
- position
- timestamps

### Task

Campos principais:

- id
- project_id
- title
- short_description
- description
- status
- due_at
- position
- completed_at
- timestamps

### Status de Task

Usar PHP Enum com os valores:

```text
not_started
in_progress
completed
cancelled
```

Ao entrar em `completed`, `completed_at` deve receber a data/hora atual caso ainda esteja vazio.

Ao sair de `completed`, `completed_at` deve ser limpo.

Uma Task `cancelled` não é considerada concluída.

### Overdue

O estado de atraso será derivado.

Uma tarefa estará atrasada quando:

```text
due_at < now
AND
status != completed
AND
status != cancelled
```

Não criar coluna `overdue` no banco.

Datas devem ser armazenadas em UTC e apresentadas utilizando o timezone do navegador.

---

## 7. Tags

As tags pertencem ao usuário.

Campos principais:

- id
- user_id
- name
- normalized_name
- color
- timestamps

Regras:

- máximo de 5 tags por tarefa
- máximo de 30 caracteres por tag
- `normalized_name` deve ser único por usuário
- cores provenientes de uma paleta controlada

---

## 8. Attachments

Os anexos pertencem a uma Task.

Campos principais:

- id
- task_id
- original_name
- path
- mime_type
- size
- timestamps

Os arquivos devem ser privados e acessados através de endpoints autorizados.

Formatos permitidos inicialmente:

### Imagens

- jpg
- jpeg
- png
- webp

### Documentos

- pdf
- txt
- doc
- docx
- xls
- xlsx

Limites:

- máximo de 5 MB por arquivo
- máximo de 10 anexos por tarefa
- máximo de 5 arquivos por upload

A validação deve considerar MIME type real, não apenas extensão.

Ao excluir permanentemente uma Task ou Project, seus arquivos físicos relacionados também devem ser removidos de forma consistente.

---

## 9. Exclusões

Não utilizar Soft Deletes inicialmente.

Project e Task terão exclusão permanente.

A interface deve solicitar confirmação antes de operações destrutivas.

---

## 10. Ordenação e Kanban

Projetos e tarefas utilizarão uma coluna inteira `position`.

O MVP utilizará reindexação simples.

Não utilizar fractional indexing inicialmente.

Mover uma Task entre colunas do Kanban deverá persistir:

- novo status
- nova posição

---

## 11. API REST

A API utilizará JSON e semântica HTTP adequada.

Rotas principais previstas:

```text
GET    /api/user

GET    /api/projects
POST   /api/projects
GET    /api/projects/{project}
PATCH  /api/projects/{project}
DELETE /api/projects/{project}

GET    /api/projects/{project}/tasks
POST   /api/projects/{project}/tasks

GET    /api/tasks/{task}
PATCH  /api/tasks/{task}
DELETE /api/tasks/{task}

PATCH  /api/tasks/{task}/move

GET    /api/tags
POST   /api/tags

POST   /api/tasks/{task}/attachments
GET    /api/attachments/{attachment}
DELETE /api/attachments/{attachment}
```

API Resources devem ser utilizados para controlar os contratos JSON e evitar exposição direta dos Models.

---

## 12. Busca, filtros e métricas

Busca e filtros deverão ser executados no backend.

Filtros planejados:

- texto
- status
- tag
- prazo

Busca inicialmente por:

- title
- short_description

Métricas planejadas:

- total
- em andamento
- concluídas
- atrasadas

Essas funcionalidades entram após o núcleo funcional estar estável.

---

## 13. Paginação

Não utilizar paginação de Tasks inicialmente.

O Kanban precisa carregar as tarefas pertencentes ao projeto para permitir organização e movimentação entre colunas.

A necessidade de paginação deverá ser reavaliada caso o volume de dados justifique.

---

## 14. Testes

Framework:

- Pest

A prioridade será Feature Tests.

Cobertura prioritária:

- autenticação
- autorização entre usuários
- CRUD de projetos
- CRUD de tarefas
- transições de status
- `completed_at`
- persistência do Kanban
- anexos
- limites de upload
- autorização de arquivos
- filtros
- contratos REST
- códigos HTTP

---

## 15. Qualidade

Utilizar:

- Laravel Pint
- Pest
- Git
- GitHub Actions

Pipeline de CI planejado:

```text
instalação de dependências
        ↓
lint / Pint
        ↓
Pest
        ↓
frontend build
```

Laravel Telescope poderá ser utilizado somente no ambiente de desenvolvimento.

Laravel Precognition é opcional e só deverá ser considerado depois que o fluxo principal estiver estável.

---

## 16. Laravel Boost e IA

Laravel Boost faz parte do ambiente de desenvolvimento para fornecer contexto real da aplicação aos agentes de IA.

O Claude Code será o principal agente de implementação.

ChatGPT será utilizado como apoio para:

- planejamento
- revisão de decisões
- elaboração e revisão de prompts
- revisão crítica das respostas do agente
- documentação

O uso de IA deve ser rastreável e criticamente revisado.

Não aceitar código gerado por IA sem inspeção.

Prompts registrados na documentação de entrega devem representar decisões e interações relevantes, e não um dump completo das conversas.

A documentação deve permanecer verdadeira.

---

## 17. Git

O desenvolvimento utilizará Git desde o início.

Commits devem ser:

- pequenos
- coerentes
- descritivos
- relacionados a uma unidade lógica de trabalho

Não misturar grandes alterações não relacionadas no mesmo commit.

---

## 18. Deploy

A estratégia de deploy ainda não foi definida.

Não adicionar integração específica com:

- Laravel Cloud
- AWS
- serviços externos de deploy

até que essa decisão seja tomada.

---

## 19. Dependências que exigem aprovação

Não adicionar sem aprovação prévia:

- Redis
- Horizon
- Reverb
- WebSockets
- Scout
- Passport
- JWT
- Octane
- filas externas
- serviços pagos
- novas bibliotecas estruturais
- novos frameworks frontend

Dependências pequenas também devem possuir uma justificativa concreta.

---

## 20. Princípio de implementação

Prioridade do projeto:

```text
1. Funcionalidade obrigatória
2. Segurança
3. Estabilidade
4. Testes
5. Qualidade do frontend
6. Diferenciais
```

Evitar overengineering.

As decisões devem favorecer simplicidade, clareza, manutenção e aderência às convenções Laravel.

---

## 21. Projects API (Fase 4)

Endpoints implementados:

```text
GET    /api/projects
POST   /api/projects
GET    /api/projects/{project}
PATCH  /api/projects/{project}
DELETE /api/projects/{project}
```

`PUT` não faz parte do contrato e retorna `405`.

### Autorização

`index` é escoposto ao usuário autenticado. `create`/`update` usam `ProjectPolicy` via Form Request (`authorize()`). `show`/`delete` usam `Gate` + `ProjectPolicy` no Controller. Um Project existente pertencente a outro usuário retorna `403`; um Project inexistente retorna `404`.

### Position

`position` é controlado pelo servidor. Um novo Project recebe `(max(position) do usuário ?? -1) + 1`. O cliente não pode definir nem alterar `position` nesta fase. O `index` ordena por `position` ASC e, em empate, por `id` ASC.

### Coleção

`GET /api/projects` retorna a coleção completa, sem paginação.

### `ProjectResource`

Expõe `id`, `name`, `description`, `color`, `position`, `created_at`, `updated_at`. Não expõe `user_id`. Mantém o wrapper `data` padrão do Laravel.

### Cores

`Project::COLORS` usa somente as 6 cores auxiliares já aprovadas: `#06B6D4`, `#14B8A6`, `#EC4899`, `#F59E0B`, `#22C55E`, `#3B82F6`. `#635BFF` (Primary) permanece cor de identidade/ações e não entra na paleta de Projects.

---

## 22. Tasks API (Fase 5)

Endpoints implementados:

```text
GET    /api/projects/{project}/tasks
POST   /api/projects/{project}/tasks
GET    /api/tasks/{task}
PATCH  /api/tasks/{task}
DELETE /api/tasks/{task}
```

`PUT` não faz parte do contrato e retorna `405`. `PATCH /api/tasks/{task}/move` ainda não existe nesta fase.

### Autorização

`index` autoriza o Project via `ProjectPolicy::view`. `store` usa `TaskPolicy::create(User, Project)` via `StoreTaskRequest::authorize()`. `show`/`delete` usam `Gate` + `TaskPolicy` no Controller. `update` usa `TaskPolicy::update` via `UpdateTaskRequest::authorize()`. Uma Task ou Project existente pertencente a outro usuário retorna `403`; um recurso inexistente retorna `404`.

### Status

`status` é opcional no Store. Quando omitido, o servidor define `TaskStatus::NotStarted` explicitamente. Quando presente, deve ser um valor válido do enum; `null` explícito não significa default e é inválido (`422`).

### `due_at`

A API aceita apenas ISO-8601 com timezone explícito (`Z` ou offset `±HH:MM`, frações de segundo permitidas); um valor sem timezone é inválido. Datas no passado são permitidas. O input é normalizado para UTC antes de persistir, e o valor bruto do `DATETIME` é interpretado explicitamente como UTC ao ler — nenhuma das duas direções depende implicitamente da timezone padrão da aplicação.

Exemplo:

```text
entrada:    2026-12-15T18:00:00-03:00
banco:      2026-12-15 21:00:00
API:        2026-12-15T21:00:00.000000Z
```

### Position

`position` é controlado pelo servidor. Uma nova Task recebe `(max(position) do Project ?? -1) + 1`. O cliente não pode definir nem alterar `position` nesta fase. Essa decisão define apenas a posição inicial e não fecha a futura estratégia de reorder (posição global vs. por coluna/status). O `index` ordena por `position` ASC e, em empate, por `id` ASC.

### Coleção

`GET /api/projects/{project}/tasks` retorna a coleção completa, sem paginação.

### `TaskResource`

Expõe `id`, `project_id`, `title`, `short_description`, `description`, `status`, `due_at`, `position`, `completed_at`, `overdue`, `created_at`, `updated_at`, `tags`, `attachments_count`. `status` é exposto como o valor escalar do enum. `overdue` é calculado pelo Model e exposto explicitamente pelo Resource. `tags` foi adicionado na Fase 6 (ver §23); `attachments_count` foi adicionado na Fase 7 (ver §24) — a lista completa de Attachments não entra no `TaskResource`, só a contagem; não expõe o Project completo nem dados de usuário.

### `completed_at`

Continua controlado exclusivamente pelo Model. Um `PATCH` de `status` passa pela instância Eloquent (`$task->update(...)`), disparando a regra de domínio que sincroniza `completed_at`. O campo não é controlável diretamente pelo payload.

---

## 23. Tags API + associação Task ↔ Tag (Fase 6)

### Endpoints

```text
GET    /api/tags
POST   /api/tags
PATCH  /api/tags/{tag}
DELETE /api/tags/{tag}

PUT    /api/tasks/{task}/tags
```

Não existe `GET /api/tags/{tag}` (Tags são sempre consumidas como coleção, sem necessidade real de busca individual) nem `PUT /api/tags/{tag}` (`405`, mesmo padrão de Project/Task). A associação Task ↔ Tag não tem endpoints incrementais (`POST`/`DELETE` por Tag) — apenas o sync completo.

### Autorização

`index` de Tags é escopado a `$request->user()->tags()`. `create`/`update`/`delete` usam `TagPolicy` (create livre para qualquer usuário autenticado; view/update/delete restritos ao owner). Tag existente de outro usuário → `403`; inexistente → `404`.

A associação (`PUT /api/tasks/{task}/tags`) autoriza via `TaskPolicy::update` (alterar as Tags é tratado como alteração de estado da Task) — não existe Pivot Policy. Task de outro usuário → `403`; Task inexistente → `404`.

### Coleção de Tags

`GET /api/tags` retorna a coleção completa do usuário autenticado, sem paginação, ordenada por `normalized_name` ASC e, em empate, `id` ASC.

### Paleta de cores

`App\Support\ColorPalette::AUXILIARY` é a fonte única das 6 cores auxiliares (`#06B6D4`, `#14B8A6`, `#EC4899`, `#F59E0B`, `#22C55E`, `#3B82F6`). `Project::COLORS` foi preservado como alias de compatibilidade e aponta para `ColorPalette::AUXILIARY` — não existem duas listas independentes. `StoreTagRequest`/`UpdateTagRequest`/`TagFactory` usam a mesma fonte. A cor Primary (`#635BFF`) não pertence à paleta de Tags.

### Normalização e unicidade lógica

`Tag::normalize()` (`trim` + `mb_strtolower`, sem remoção de acentos, sem slug, sem colapso de espaços internos) é a única fonte da derivação de `normalized_name`, reutilizada pelo mutator de `name` e pelos Form Requests. `normalized_name` continua interno, nunca aceito do payload. A unicidade lógica (por `user_id` + `normalized_name`) é validada em `Store`/`Update` via `after()`, retornando `422` amigável antes de qualquer tentativa de gravação; o `Update` exclui a própria Tag da checagem. A constraint `UNIQUE` do banco permanece como última defesa contra condição de corrida. O mesmo nome lógico é permitido para usuários diferentes.

### `TagResource`

Expõe `id`, `name`, `color`, `created_at`, `updated_at`. Não expõe `user_id` nem `normalized_name`.

### Sync Task ↔ Tag

`PUT /api/tasks/{task}/tags`, body `{ "tag_ids": [...] }`, com semântica de substituição completa do conjunto (não incremental). Regras: `tag_ids` deve estar presente (`present`, não `required` — um array vazio é um valor válido, não "ausente"), ser array, no máximo 5 itens; cada item inteiro e distinto. `[]` remove todas as Tags da Task.

Os IDs são resolvidos exclusivamente dentro de `$request->user()->tags()`; um ID inexistente e um ID pertencente a outro usuário produzem o **mesmo** erro genérico em `tag_ids` (`422`), sem distinguir a causa e sem expor qualquer dado da Tag alheia. Essa checagem só roda depois que a validação estrutural de `tag_ids`/`tag_ids.*` já passou.

A operação de `sync()` no pivot é protegida por `DB::transaction()`, garantindo atomicidade da substituição integral; o carregamento da relação e a montagem do Resource ficam fora da transação. Nenhum outro endpoint (Tags CRUD, Task CRUD) usa transação.

### `TaskResource` e Tags

`TaskResource` passa a expor `tags` (coleção de `TagResource`) em todas as respostas atuais — `index`, `show`, `store`, `update` e o sync — com eager loading explícito em cada call site (`with('tags')` no índice, `load('tags')` nos demais). Uma Task sem Tags retorna `"tags": []`; não existe formato alternativo de Task.

### Ordenação das Tags de uma Task

`Task::tags()` ordena por `tags.normalized_name` ASC e `tags.id` ASC (colunas qualificadas), centralizado na relação — não em cada Controller. Não existe `position` no pivot nem reorder de Tags.

---

## 24. Attachments / Uploads (Fase 7)

### Endpoints

```text
GET    /api/tasks/{task}/attachments
POST   /api/tasks/{task}/attachments
GET    /api/attachments/{attachment}/download
DELETE /api/attachments/{attachment}
```

Não existe `GET /api/attachments/{attachment}` de metadata individual (a listagem por Task já cobre esse uso) nem endpoint `/preview` separado — o download já serve como preview quando o MIME permite exibição inline.

### Disk

Disco configurável via `ATTACHMENTS_DISK` (`.env`), lido em `config('filesystems.attachments_disk')` — nunca `'local'` fixo no código da feature. O disco `local` já é privado por padrão (não servido como diretório público estático).

### Upload

`multipart/form-data`, campo `files` (array). Regras: mínimo 1, máximo 5 arquivos por requisição; cada arquivo até 5 MB; tipos permitidos `jpg`, `jpeg`, `png`, `webp`, `pdf`, `txt`, `doc`, `docx`, `xls`, `xlsx`, validados via `Illuminate\Validation\Rules\File::types()` (inspeciona o conteúdo real do arquivo, não a extensão declarada pelo cliente).

Limite adicional de 10 anexos por Task, verificado dentro de uma `DB::transaction()` com a Task bloqueada via `lockForUpdate()`, antes de qualquer escrita física — excedê-lo rejeita a requisição inteira (`422`), sem gravação parcial. O lote de múltiplos arquivos é tratado como uma unidade: se qualquer arquivo falhar ao ser persistido, os arquivos já gravados nessa requisição são removidos do disco e nenhuma linha permanece.

### Path e metadata

Arquivos são gravados em `attachments/{task_id}/{hashName}` (nome aleatório com extensão derivada do MIME real, nunca o nome original do cliente). `original_name`, `mime_type` e `size` são sempre derivados do arquivo enviado no servidor; `task_id` vem exclusivamente da relação `$task->attachments()->create(...)`. Nenhum desses campos é aceito do payload.

### `AttachmentResource`

Expõe `id`, `original_name`, `mime_type`, `size`, `created_at`, `updated_at`, `download_url`. Não expõe `task_id` nem o `path` físico. `download_url` é relativo (`/api/attachments/{attachment}/download`).

### Download

Endpoint único, atrás de `auth:sanctum` e `AttachmentPolicy::view` — sem URL pública permanente, sem signed URL. Conteúdo é transmitido via `Storage::disk($disk)->response(...)` (streaming, sem carregar o arquivo inteiro em memória). `Content-Type` vem de `mime_type` persistido; o nome apresentado vem de `original_name`. `Content-Disposition` é `inline` para `image/jpeg`, `image/png`, `image/webp`, `application/pdf` e `text/plain`; `attachment` para os demais tipos permitidos (Office). Uma linha cujo arquivo físico não existe mais no disco é tratada como inconsistência interna: resulta em erro de servidor, nunca em `404` (que implicaria a inexistência do próprio registro), e a resposta não expõe o path físico.

### Delete Attachment

Autoriza primeiro. Se o arquivo físico já não existe, remove a linha diretamente. Se existe, tenta a exclusão física; se ela retornar falha mas o arquivo já não existir mais (convergência), prossegue normalmente; se o arquivo continuar existindo após a falha, a linha não é removida e a operação resulta em erro — evitando criar deliberadamente uma linha órfã.

### Delete Task / Delete Project

`DeleteTask` e `DeleteProject` (`app/Actions`) resolvem a limpeza física que o cascade do banco por si só não cobre. Em ambos, a exclusão no banco acontece primeiro (a Task/Project e suas linhas dependentes via cascade); só depois disso a limpeza física é tentada. `DeleteTask` remove o diretório inteiro `attachments/{task_id}` (não apenas os paths conhecidos pelas linhas), o que também elimina eventuais arquivos órfãos dentro dele. `DeleteProject` coleta os IDs das Tasks antes do delete (o cascade os removeria do banco) e repete a mesma limpeza de diretório para cada uma — uma falha ao limpar uma Task não impede a tentativa nas demais. A limpeza física é *best-effort*: uma falha é registrada via `Log::warning` (com contexto como `task_id`/`project_id`/disk, nunca o path completo na resposta HTTP) e nunca reverte ou transforma em erro uma exclusão que já foi concluída com sucesso no banco.

### `TaskResource`

Passa a expor `attachments_count` (contagem derivada via `withCount`/`loadCount`, nunca persistida) em todas as respostas atuais de Task (`index`, `show`, `store`, `update`, sync de Tags). A lista completa de Attachments continua fora do `TaskResource` — permanece exclusiva do endpoint dedicado de listagem.

---

## 25. Frontend — App Shell + Projects (Fase 8)

### Rotas

```text
/projects              projects.index
/projects/:projectId   projects.show
```

Ambas filhas de um único `AppShell` (`meta: { requiresAuth: true }`). `/` redireciona para `projects.index`; não existe uma rota `home` nem uma página de "Visão geral" — a listagem de Projects já é o destino de nível superior.

### Projeto ativo

O Project ativo (Sidebar, `ProjectDetailPage`) é derivado exclusivamente de `route.params.projectId`, nunca de um `activeProjectId` replicado em store — uma única fonte de verdade para essa informação.

### Projects Store (Pinia)

Setup store (`stores/projects.ts`) com `projects`, `status` (`idle`/`loading`/`loaded`/`error`), `fetchProjects`, `createProject`, `updateProject`, `deleteProject`, `reset`.

`fetchProjects` é chamado uma única vez, no `setup()` do `AppShell` — páginas filhas (index, detail) e a Sidebar leem do mesmo estado, sem refazer a requisição. Chamadas concorrentes compartilham a mesma Promise (`inFlight`); uma vez `loaded`, uma nova chamada é no-op a menos que `force` seja passado (retry após erro).

Um contador `generation`, incrementado em `reset()`, invalida qualquer requisição que ainda esteja em voo no momento do reset — sua resposta, ao chegar, é descartada em vez de repopular a store com dados de uma sessão anterior. `AppShell` chama `reset()` tanto no `setup()` quanto no `onBeforeUnmount()`, cobrindo login/logout sem reload completo de página.

`createProject`/`updateProject`/`deleteProject` atualizam o array local (`push`/substituição por índice/`splice`) a partir da resposta do servidor, apenas após sucesso confirmado — nenhuma dessas operações refaz `GET /api/projects`. Em erro, o array permanece inalterado e a exceção é relançada para a camada de UI tratar.

### Diálogos nativos

`Modal`, `Drawer` e `ConfirmDialog` (que reaproveita `Modal`) são implementados sobre `<dialog>` + `showModal()`/`close()` — sem focus trap manual. O fechamento visual é assíncrono (uma transição CSS roda antes do `close()` real), mantendo o atributo `open` nativo até a transição terminar.

Nenhuma classe utilitária de `display` (ex.: `flex`) pode ser aplicada incondicionalmente ao elemento `<dialog>`, pois uma regra de autor sobrepõe a regra de user-agent `dialog:not([open]) { display: none }` independentemente de especificidade — deixando o diálogo, mesmo fechado, ocupando espaço e interceptando cliques. Onde o layout do conteúdo exige `display` diferente de `block`, ele é condicionado ao atributo `[open]` (ex.: `hidden [&[open]]:flex`).

### Create Project Modal compartilhado

Existe uma única instância de `ProjectFormModal` (modo `create`) por sessão autenticada, montada pelo `AppShell` e controlada por um composable de estado efêmero em module scope (`useProjectCreateModal`, fora do Pinia — não é dado de domínio). Todo ponto de entrada (`PageHeader`, `EmptyState`, quick-add da Sidebar) abre a mesma instância. `AppShell` força esse estado de volta a fechado no `setup()` e no `onBeforeUnmount()`, para que ele nunca sobreviva a um ciclo de logout/login.

No mobile, a Sidebar (com o quick-add) vive dentro do `Drawer` de navegação. Abrir o Create Modal nunca acontece com o Drawer ainda aberto — o Drawer é fechado primeiro, e o Create Modal só abre depois que o `Drawer` emite `closed` (disparado após seu próprio `dialog.close()` real), evitando dois `<dialog>` modais simultâneos.

### Toast

`useToast`/`ToastViewport` são um composable simples (array reativo em module scope), não uma store Pinia — feedback de sucesso é estado de UI efêmero, não dado de domínio. Usado exclusivamente para sucesso de create/update/delete de Project; erros de validação (422) permanecem inline nos formulários, e falha de delete permanece inline no `ConfirmDialog`.

### `@lucide/vue`

Biblioteca de ícones do frontend. O pacote `lucide-vue-next` (usado brevemente no início da fase) foi removido e substituído por `@lucide/vue` — nenhuma referência ao pacote antigo permanece no código ou nas dependências.

---

## 26. Frontend — Tasks UI + Kanban + Attachments (Fase 9)

### Tasks Store (Pinia)

`stores/tasks.ts` mantém um bucket de Tasks por Project (`tasksByProject: Record<number, Task[]>`), com o mesmo tipo de proteção de resposta obsoleta do Projects Store elevado a dois níveis: um `sessionEpoch` global (incrementado só por `reset()`, cobre logout) e um `generationByProject` por Project (preparado para uma futura invalidação escopada, ainda sem caller nesta fase). Um `isValid(projectId, epoch, generation)` compartilhado guarda `fetchTasks`, `createTask`, `updateTask`, `deleteTask` e `syncTaskTags` — toda resposta só é aplicada se nem o epoch nem a generation daquele Project mudaram desde o início da requisição. `stores/tags.ts` segue o mesmo princípio com um único `sessionEpoch` (Tags são globais por usuário, não por Project). `AppShell` reseta os três stores (Projects, Tasks, Tags) tanto no `setup()` quanto no `onBeforeUnmount()`.

### Task Modal, não Drawer

Criação e edição de tarefa usam um Modal central (`TaskModal.vue`, reaproveitando `Modal.vue` com `size="lg"`), não um Drawer lateral. Um Drawer lateral foi a intenção original (ver histórico de `docs/UI-UX.md` §21), revisado para Modal antes do fechamento da fase por consistência visual com o Modal de Project — mesma família de bordas/sombra/header/footer/backdrop.

### Task + Tags — endpoints separados, falha parcial explícita

Task (`POST`/`PATCH /api/tasks`) e a sincronização de Tags (`PUT /api/tasks/{task}/tags`) são chamadas separadas. `TaskForm.vue` decide POST vs PATCH a partir de `persistedTask` (não de `mode`), garantindo que uma falha na sincronização de Tags nunca dispare um novo POST de Task em uma tentativa seguinte. Se a Task salva com sucesso mas o sync de Tags falha, o formulário não fecha, não finge sucesso, e oferece um retry que repete somente o `PUT` de Tags.

### Kanban sem reorder

O Kanban agrupa Tasks por `status` inteiramente no cliente, sem chamada adicional de API. Mudança de status é sempre um `PATCH /api/tasks/{task}` parcial (`{ status }`) — via menu "..." (todo tamanho de tela) ou via drag-and-drop nativo HTML5 (desktop apenas, desabilitado em dispositivos de ponteiro grosso). Não existe reorder dentro de uma coluna, nem escrita de `position` pelo cliente — o backend não expõe esse contrato nesta fase (ver §10). Um `Set<number>` (`movingTaskIds`) — não um único id — rastreia quais Tasks têm PATCH em voo, permitindo que duas Tasks distintas sejam movidas concorrentemente sem que uma limpe o estado de "movendo" da outra.

### Attachments — estado local, contador reconciliado pelo GET

`TaskAttachments.vue` mantém toda a lista de anexos e seus estados (loading/uploading/deleting) como estado local do componente, sem um Attachments Store dedicado — Attachments pertencem à Task atualmente aberta no Modal, não a um estado compartilhado entre páginas. O Tasks Store mantém apenas `attachments_count` (via `setTaskAttachmentsCount(projectId, taskId, count)`), atualizado depois de upload/delete bem-sucedidos e, de forma mais confiável, reconciliado a cada `GET /api/tasks/{task}/attachments` bem-sucedido (a lista completa é sempre a fonte de verdade mais recente disponível). Um `generation` local (fechamento do componente, incrementado em `onBeforeUnmount` e a cada troca de `taskId`) invalida qualquer resposta de fetch/upload/delete que resolva depois que o componente deixou de representar o contexto que a originou.

### `Modal.vue` — suporte correto a `open=true` já no mount

A primitiva `Modal.vue` foi corrigida para sincronizar o `<dialog>` nativo corretamente em dois casos: a transição reativa `open: false → true` de um componente já montado (via `watch`), e um componente que nasce com `open` já `true` (via `onMounted`, já que um `watch` sem `immediate: true` não dispara nesse caso, e um `watch` com `immediate: true` rodaria cedo demais, antes do `<dialog>` existir no DOM). Ambos os caminhos chamam a mesma função central (`syncDialogState`), que nunca chama `showModal()` em um dialog já aberto nem `close()` em um já fechado. Isso torna a primitiva robusta independentemente de o caller usar `v-if` para gate de montagem — nenhum caller precisou mudar por causa desta correção.
