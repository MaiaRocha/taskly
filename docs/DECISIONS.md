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
