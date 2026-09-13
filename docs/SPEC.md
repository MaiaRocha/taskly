# Taskly — Especificação Funcional e Técnica

## Status

Este documento define o escopo funcional e técnico aprovado para o Taskly.

Ele deve ser utilizado em conjunto com:

- `docs/DECISIONS.md`
- `docs/UI-UX.md`

Em caso de dúvida, conflito ou necessidade de alterar arquitetura, stack, autenticação, segurança, persistência ou dependências relevantes, a implementação deve parar para revisão antes da alteração.

---

## 1. Objetivo do produto

Taskly é uma aplicação web de gerenciamento de tarefas.

O usuário deve conseguir:

- criar sua própria conta
- autenticar-se
- criar múltiplos projetos
- criar e organizar tarefas dentro dos projetos
- utilizar visualização em lista
- utilizar visualização Kanban
- alterar o status das tarefas
- definir prazos
- utilizar tags
- adicionar anexos
- editar todas as informações relevantes posteriormente

A aplicação deve possuir boa experiência tanto em desktop quanto em dispositivos móveis.

---

## 2. Níveis de prioridade

Os requisitos utilizam os seguintes níveis:

### MUST

Obrigatório para considerar o núcleo da aplicação concluído.

### SHOULD

Importante e deve ser implementado depois que o núcleo obrigatório estiver estável.

### COULD

Diferencial ou melhoria opcional, somente se houver tempo e se não comprometer estabilidade, testes ou qualidade.

---

## 3. Arquitetura aprovada

### MUST

A aplicação utilizará:

```text
Vue 3 SPA
    ↓
HTTP / JSON
    ↓
Laravel REST API
    ↓
Eloquent ORM
    ↓
MariaDB
```

Frontend e backend permanecerão:

- no mesmo repositório
- dentro da mesma aplicação
- inicialmente no mesmo deploy

Arquitetura geral:

**Modular Monolith com fronteira REST explícita.**

Não utilizar Inertia.js.

---

## 4. Stack

### Backend

- PHP 8.3+
- Laravel 13
- MariaDB
- Eloquent ORM
- Laravel Fortify
- Laravel Sanctum
- Form Requests
- Policies
- API Resources
- PHP Enums
- Laravel Filesystem
- Pest
- Laravel Pint
- Laravel Boost

### Frontend

- Vue 3
- TypeScript
- Vue Router
- Pinia
- Axios
- Vite
- Tailwind CSS
- Lucide Icons

`shadcn-vue` poderá ser utilizado seletivamente se houver justificativa e seus componentes forem personalizados para a identidade do Taskly.

---

## 5. Usuário

### MUST

O sistema deve permitir cadastro utilizando:

- nome
- e-mail
- senha
- confirmação de senha

O e-mail deve ser único.

A senha deve respeitar as regras definidas pelo backend.

O usuário deve conseguir:

- registrar-se
- efetuar login
- permanecer autenticado através da sessão
- efetuar logout

OAuth não faz parte do escopo obrigatório.

---

## 6. Autenticação da SPA

### MUST

Utilizar:

- Laravel Fortify
- Laravel Sanctum
- autenticação stateful para SPA
- sessão Laravel
- cookies HttpOnly
- proteção CSRF

Fluxo esperado:

```text
GET /sanctum/csrf-cookie
        ↓
POST /login
        ↓
Fortify valida credenciais
        ↓
Laravel cria sessão
        ↓
SPA passa a acessar endpoints protegidos
```

A SPA não deve armazenar token de autenticação em:

- `localStorage`
- `sessionStorage`

Não implementar JWT próprio.

### Endpoint de usuário autenticado

```http
GET /api/user
```

Deve retornar somente os dados necessários do usuário autenticado.

---

## 7. Autorização

### MUST

Todos os dados do domínio pertencem direta ou indiretamente a um usuário.

Um usuário jamais deve conseguir:

- visualizar projeto de outro usuário
- editar projeto de outro usuário
- excluir projeto de outro usuário
- visualizar tarefa de outro usuário
- editar tarefa de outro usuário
- mover tarefa de outro usuário
- acessar anexo de outro usuário
- excluir anexo de outro usuário

Policies devem ser utilizadas para proteger esses recursos.

Autenticação não deve ser considerada suficiente para autorização.

Devem existir testes específicos contra acesso horizontal entre usuários.

---

## 8. Project

### MUST

O usuário deve conseguir criar múltiplos projetos.

Campos:

```text
id
user_id
name
description
color
position
created_at
updated_at
```

### Regras

`user_id` deve ser definido pelo backend.

O frontend não deve controlar ownership.

`position` deve ser controlada pelo backend.

O nome do projeto é obrigatório.

Descrição poderá ser opcional.

A cor deve vir de uma paleta válida definida pela aplicação.

### Funcionalidades

O usuário deve conseguir:

- listar seus projetos
- criar projeto
- visualizar projeto
- editar projeto
- excluir projeto

Exclusão deve exigir confirmação na interface.

Não utilizar Soft Deletes inicialmente.

---

## 9. Task

### MUST

Uma tarefa sempre pertence a um projeto.

Campos:

```text
id
project_id
title
short_description
description
status
due_at
position
completed_at
created_at
updated_at
```

### Campos funcionais obrigatórios

A interface deve permitir:

- título
- descrição curta
- descrição completa
- data e hora de prazo
- status
- tags
- anexos

Todos esses dados devem poder ser editados posteriormente quando aplicável.

### Título

Obrigatório.

### Descrição curta

Utilizada principalmente em:

- Kanban
- visão resumida
- busca

### Descrição completa

Campo destinado ao detalhamento da tarefa.

### Prazo

`due_at` poderá ser nulo.

Quando informado, deve conter data e hora.

---

## 10. Status das tarefas

### MUST

Utilizar um PHP Enum.

Valores persistidos:

```text
not_started
in_progress
completed
cancelled
```

Representação funcional:

```text
not_started → Não iniciada
in_progress → Em andamento
completed   → Concluída
cancelled   → Cancelada
```

### completed_at

Ao alterar a tarefa para `completed`:

```text
se completed_at estiver vazio
→ preencher com horário atual
```

Ao retirar a tarefa de `completed`:

```text
completed_at → null
```

Uma tarefa cancelada não é uma tarefa concluída.

O frontend não deve definir diretamente `completed_at`.

---

## 11. Tarefa atrasada

### MUST

`overdue` será uma informação derivada.

Uma tarefa estará atrasada quando:

```text
due_at != null
AND
due_at < now
AND
status != completed
AND
status != cancelled
```

Não criar coluna `overdue` no banco.

A informação poderá ser exposta pela API Resource quando útil para a interface.

---

## 12. Datas e timezone

### MUST

Datas persistidas devem utilizar UTC.

A interface deve apresentar datas considerando o timezone do navegador do usuário.

A API deve utilizar representação de data/hora consistente e apropriada para JSON.

O frontend não deve assumir que o timezone do servidor é o timezone do usuário.

---

## 13. Tags

### MUST

Uma Tag pertence ao usuário.

Estrutura:

```text
id
user_id
name
normalized_name
color
created_at
updated_at
```

Relacionamento:

```text
Task ↔ Tag
many-to-many
```

### Regras

Máximo de:

```text
5 tags por tarefa
```

Nome:

```text
máximo de 30 caracteres
```

`normalized_name` deve impedir duplicidade lógica para o mesmo usuário.

Exemplo:

```text
Urgente
urgente
 URGENTE
```

devem ser tratados como a mesma tag dentro do contexto do mesmo usuário.

A estratégia exata de normalização deve ser simples, previsível e testada.

A cor deve pertencer a uma paleta permitida.

Um usuário não deve conseguir utilizar Tags pertencentes a outro usuário.

Ao implementar a associação de tags a uma tarefa (attach/sync), os IDs de tag recebidos devem ser resolvidos apenas dentro do conjunto de tags pertencentes ao usuário autenticado — nunca aceitos a partir de um ID arbitrário do payload sem essa verificação.

---

## 14. Attachments

### MUST

Uma Task poderá possuir anexos.

Estrutura:

```text
id
task_id
original_name
path
mime_type
size
created_at
updated_at
```

### Tipos permitidos

Imagens:

```text
jpg
jpeg
png
webp
```

Documentos:

```text
pdf
txt
doc
docx
xls
xlsx
```

### Limites

Por arquivo:

```text
5 MB
```

Por tarefa:

```text
máximo de 10 anexos
```

Por requisição de upload:

```text
máximo de 5 arquivos
```

### Segurança

Os arquivos devem ser armazenados de forma privada.

Não devem ficar disponíveis por uma URL pública previsível.

Download ou preview deve passar por autorização.

Validar:

- tamanho
- MIME type real
- quantidade
- ownership da tarefa

Não confiar apenas na extensão enviada pelo navegador.

### Exclusão

Ao excluir um Attachment:

- remover registro correspondente
- remover arquivo físico

Ao excluir permanentemente uma Task:

- remover seus attachments
- remover arquivos físicos correspondentes

Ao excluir permanentemente um Project:

- remover suas tasks
- remover attachments relacionados
- remover arquivos físicos relacionados

A operação deve evitar arquivos órfãos sempre que possível.

---

## 15. API REST

### MUST

A API deve utilizar JSON.

Controllers devem permanecer enxutos.

Form Requests devem tratar validação.

Policies devem tratar autorização.

API Resources devem controlar representação JSON.

### Rotas principais previstas

#### Usuário

```http
GET /api/user
```

#### Projetos

```http
GET    /api/projects
POST   /api/projects
GET    /api/projects/{project}
PATCH  /api/projects/{project}
DELETE /api/projects/{project}
```

#### Tarefas

```http
GET    /api/projects/{project}/tasks
POST   /api/projects/{project}/tasks

GET    /api/tasks/{task}
PATCH  /api/tasks/{task}
DELETE /api/tasks/{task}
```

#### Movimentação

```http
PATCH /api/tasks/{task}/move
```

#### Tags

```http
GET  /api/tags
POST /api/tags
```

#### Attachments

```http
POST   /api/tasks/{task}/attachments
GET    /api/attachments/{attachment}
DELETE /api/attachments/{attachment}
```

Rotas poderão ser refinadas durante a implementação desde que a semântica REST e as decisões arquiteturais sejam preservadas.

Mudanças estruturais devem ser aprovadas antes da implementação.

---

## 16. Semântica HTTP

### MUST

Utilizar códigos HTTP coerentes.

Exemplos esperados:

```text
200 OK
201 Created
204 No Content
401 Unauthenticated
403 Forbidden
404 Not Found
422 Unprocessable Entity
```

Não responder `200` para toda situação indiscriminadamente.

Erros de validação devem possuir formato consistente.

A SPA deve conseguir distinguir:

- erro de validação
- falta de autenticação
- falta de autorização
- recurso inexistente
- erro inesperado

---

## 17. Contratos JSON

### MUST

Não retornar Models diretamente como contrato público da API quando API Resources forem apropriados.

Resources devem expor somente dados necessários.

Evitar vazamento de:

- campos internos
- propriedades sensíveis
- informações de outros usuários

Os contratos devem ser previsíveis para o frontend.

---

## 18. Ordenação

### MUST

Projects e Tasks utilizarão `position` inteira.

Para o MVP será utilizada reindexação simples.

Não implementar fractional indexing inicialmente.

A posição recebida do frontend deve ser validada e normalizada no backend.

---

## 19. Kanban

### MUST

A aplicação deve possuir visualização Kanban.

Colunas:

```text
Não iniciada
Em andamento
Concluída
Cancelada
```

Cada coluna corresponde a um status da Task.

O usuário deve conseguir mover tarefas entre colunas.

### Persistência

Após movimentação, o backend deverá persistir:

- novo status
- nova posição

O frontend pode utilizar atualização otimista somente se possuir mecanismo confiável de rollback em caso de falha.

A implementação mais simples e segura deve ser priorizada.

**Estado implementado (Fase 9):** a persistência de `status` foi entregue via `PATCH /api/tasks/{task}` (payload parcial `{ status }`), acionada pelo menu "..." do Card em qualquer tamanho de tela e por drag-and-drop nativo em desktop. A persistência de `position` (reorder) segue deliberadamente adiada — não existe endpoint de move/reorder, e o cliente nunca escreve `position` (ver `docs/DECISIONS.md` §10, §22 e §26). Sem atualização otimista: o Card só muda de coluna após o `PATCH` confirmar sucesso.

---

## 20. Visualização em lista

### MUST

A aplicação deve permitir alternância entre:

```text
Lista
Kanban
```

A lista deve facilitar leitura e operação rápida.

Deve exibir informações úteis como:

- título
- status
- tags
- prazo
- indicação de atraso quando aplicável

Detalhes visuais estão definidos em `docs/UI-UX.md`.

---

## 21. Criação e edição de Task

### MUST

Criação e edição utilizam um Modal central (**Task Modal**, mesma família visual do Modal de Project — ver `docs/UI-UX.md` §21), substituindo a intenção original de Drawer lateral após revisão visual na Fase 9.

O formulário deve suportar:

- título
- descrição curta
- descrição completa
- status
- prazo
- tags
- attachments (somente após a Task já existir — ver `docs/UI-UX.md` §22)

A experiência mobile deve continuar funcional.

---

## 22. Busca

### SHOULD

Busca executada no backend.

Campos iniciais:

```text
title
short_description
```

A busca deve ser limitada aos dados pertencentes ao usuário autenticado.

Não carregar dados de outros usuários para depois filtrar no frontend.

---

## 23. Filtros

### SHOULD

Filtros previstos:

- status
- tags
- prazo

Filtros devem funcionar junto da busca quando aplicável.

A implementação deve favorecer URLs ou estado previsível quando isso melhorar usabilidade, sem adicionar complexidade desnecessária.

---

## 24. Métricas

### SHOULD

Métricas previstas por projeto:

```text
total
em andamento
concluídas
atrasadas
```

As métricas devem derivar dos dados reais.

Não persistir métricas que possam ser calculadas de forma simples.

---

## 25. Paginação

### Decisão inicial

Não paginar Tasks no MVP.

Motivo:

O Kanban precisa carregar as tarefas do projeto para permitir organização e movimentação entre colunas.

Essa decisão poderá ser revista se testes demonstrarem necessidade devido a volume de dados.

Projetos poderão receber paginação futuramente caso haja necessidade real.

---

## 26. Frontend — arquitetura

### MUST

A SPA utilizará Vue 3 com TypeScript.

Vue Router será responsável pela navegação.

Axios será configurado centralmente.

Pinia será utilizado apenas para estados realmente compartilhados.

Evitar transformar todos os dados em stores globais.

### Estrutura conceitual

Separar responsabilidades entre:

- páginas
- componentes
- composables quando úteis
- stores globais justificadas
- camada centralizada de comunicação HTTP

Evitar componentes excessivamente grandes.

---

## 27. Rotas frontend

### MUST

Rotas previstas conceitualmente:

```text
/login
/register
/projects
/projects/:projectId
```

A estrutura poderá ser refinada sem alterar o comportamento funcional esperado.

Rotas protegidas devem verificar estado de autenticação.

A autorização verdadeira continua sendo responsabilidade do backend.

---

## 28. Estado de autenticação no frontend

### MUST

A SPA deve conseguir:

- buscar usuário autenticado
- reconhecer sessão existente
- lidar com usuário não autenticado
- efetuar logout
- redirecionar adequadamente

Pinia poderá manter o estado compartilhado do usuário autenticado.

Não armazenar token secreto no navegador.

---

## 29. Camada HTTP

### MUST

Axios deve possuir uma configuração centralizada.

Deve tratar adequadamente:

- base URL
- CSRF
- credentials
- respostas 401
- respostas 419 quando aplicável
- respostas 422
- erros inesperados

Não duplicar configuração de Axios em diversos componentes.

---

## 30. UI/UX

### MUST

A implementação visual deve seguir:

```text
docs/UI-UX.md
```

Direção aprovada:

- inspiração organizacional no ClickUp
- identidade própria
- light mode
- visual moderno
- colorido sem excesso
- produtivo
- boa densidade de informação
- responsivo
- desktop excelente
- mobile muito bem adaptado

Não copiar literalmente o ClickUp.

---

## 31. Estados da interface

### MUST

A interface deve possuir feedback para:

- carregamento
- sucesso
- erro
- validação
- estado vazio
- operação em andamento
- confirmação destrutiva

Não deixar o usuário sem feedback após ações importantes.

### Exemplos

Utilizar conforme apropriado:

- Skeleton
- Spinner
- Toast
- mensagens inline
- Empty State
- Dialog de confirmação

---

## 32. Responsividade

### MUST

A aplicação deve funcionar adequadamente em desktop e mobile.

Desktop é a experiência principal, mas mobile não poderá ser apenas uma versão comprimida e quebrada.

Elementos críticos devem permanecer utilizáveis:

- navegação
- lista
- Kanban
- Task Modal
- formulários
- filtros
- ações
- anexos

Detalhes em `docs/UI-UX.md`.

---

## 33. Validação

### MUST

Toda validação relevante deve existir no backend.

Validação frontend poderá melhorar UX, mas não substitui validação do servidor.

Utilizar Form Requests quando apropriado.

Regras devem ser testadas.

---

## 34. Segurança

### MUST

Implementar proteção adequada contra:

- acesso horizontal
- mass assignment indevido
- upload inválido
- exposição pública de attachments
- alteração de ownership
- manipulação de posição sem validação
- alteração direta de `completed_at`
- acesso não autenticado às APIs privadas

Não confiar no frontend como fonte de segurança.

---

## 35. Banco de dados

### MUST

Banco principal:

```text
MariaDB
```

Utilizar:

- foreign keys
- índices necessários
- constraints coerentes
- tipos apropriados

Relacionamentos devem ser definidos no Eloquent.

Antes de otimizações prematuras, validar as queries reais.

---

## 36. Índices

### MUST quando aplicável

Considerar índices para campos utilizados frequentemente em:

- foreign keys
- ownership
- status
- prazo
- ordenação
- busca e filtros quando justificado

A estratégia exata deverá considerar as queries implementadas.

Não criar índices aleatórios sem relação com padrões reais de consulta.

---

## 37. Seeders e factories

### SHOULD

Criar factories úteis para testes.

Criar seeders que permitam demonstrar a aplicação com dados realistas quando necessário.

Dados de demonstração devem facilitar avaliação de:

- projetos
- tarefas
- diferentes status
- tags
- prazos
- tarefas atrasadas

Evitar conteúdo genérico sem utilidade visual.

---

## 38. Testes

### MUST

Framework:

```text
Pest
```

Prioridade:

```text
Feature Tests
```

Os testes devem validar comportamento, segurança e contratos importantes.

---

## 39. Testes de autenticação

### MUST

Cobrir pelo menos:

- registro válido
- e-mail duplicado
- login válido
- login inválido
- logout
- acesso autenticado
- acesso não autenticado

---

## 40. Testes de autorização

### MUST

Criar cenários com pelo menos dois usuários.

Validar que um usuário não consegue acessar recursos do outro.

Cobrir:

- Projects
- Tasks
- Attachments
- Tags quando aplicável

---

## 41. Testes de Project

### MUST

Cobrir:

- criação
- listagem
- visualização
- edição
- exclusão
- validação
- ownership

---

## 42. Testes de Task

### MUST

Cobrir:

- criação
- visualização
- edição
- exclusão
- status
- prazo
- ownership

---

## 43. Testes de completed_at

### MUST

Cobrir:

```text
not_started → completed
```

deve preencher `completed_at`.

Cobrir:

```text
completed → in_progress
```

deve limpar `completed_at`.

Cobrir também comportamento ao atualizar tarefa que já esteja concluída.

---

## 44. Testes do Kanban

### MUST

Cobrir:

- mudança de status
- mudança de posição
- persistência
- autorização
- payload inválido

**Estado implementado:** os testes reais (`tests/Feature/TaskControllerTest.php` e correlatos) cobrem mudança de status via `PATCH /api/tasks/{task}`, persistência, autorização e payload inválido. Não existem testes de "mudança de posição" porque não existe endpoint de move/reorder nesta fase (ver §71 e `docs/DECISIONS.md` §26) — não há o que testar ainda.

---

## 45. Testes de Tags

### MUST

Cobrir:

- criação
- normalização
- duplicidade por usuário
- limite de 5 por tarefa
- limite de caracteres
- impossibilidade de utilizar tag de outro usuário

---

## 46. Testes de Attachments

### MUST

Cobrir:

- upload permitido
- extensão/MIME inválido
- tamanho máximo
- quantidade máxima por upload
- quantidade máxima por tarefa
- autorização de acesso
- autorização de exclusão
- remoção associada quando Task for excluída

Utilizar fake storage quando apropriado.

---

## 47. Testes de API

### MUST

Validar:

- códigos HTTP
- formato JSON
- erros de validação
- autorização
- recursos inexistentes
- contratos importantes das API Resources

---

## 48. Busca e filtros

### SHOULD

Quando implementados, criar testes para:

- busca
- status
- tag
- prazo
- combinação de filtros
- isolamento por usuário

---

## 49. Laravel Pint

### MUST

Código PHP modificado deve ser formatado com Laravel Pint.

As regras geradas pelo Laravel Boost devem ser respeitadas.

---

## 50. Laravel Boost

### MUST durante desenvolvimento assistido

O agente deve utilizar Laravel Boost quando apropriado para:

- consultar documentação compatível com as versões instaladas
- inspecionar contexto da aplicação
- consultar schema
- investigar problemas
- trabalhar com informações reais do projeto

Não inventar APIs Laravel quando a documentação compatível puder ser consultada pelo Boost.

---

## 51. Claude Code

Claude Code será o principal agente de implementação assistida.

Antes de uma fase substancial de implementação, deve:

1. ler o contexto do projeto
2. verificar documentação relevante
3. analisar o estado atual
4. propor um plano
5. identificar riscos e ambiguidades
6. aguardar aprovação quando solicitado

Não iniciar mudanças arquiteturais silenciosamente.

---

## 52. Uso crítico de IA

### MUST

Código gerado por IA deve ser revisado.

A revisão deve considerar:

- aderência ao escopo
- segurança
- autorização
- bugs
- hallucinations
- versões corretas das APIs
- arquitetura
- legibilidade
- testes
- performance

Não aceitar implementação apenas porque compila.

---

## 53. Documentação do uso de IA

### MUST para entrega

Registrar de maneira curada:

- ferramentas utilizadas
- decisões relevantes apoiadas por IA
- prompts importantes
- revisões críticas
- correções feitas após análise humana

Não registrar toda conversa bruta.

Não esconder erros ou inventar interações que não ocorreram.

A documentação deve ser verdadeira e profissional.

---

## 54. Git

### MUST

Utilizar Git durante todo o desenvolvimento.

Commits devem representar unidades lógicas.

Evitar:

- commits gigantes
- mensagens vagas
- misturar funcionalidades não relacionadas

Antes de commit relevante:

- revisar diff
- executar testes necessários
- executar formatter/linter aplicável

---

## 55. CI

### SHOULD

Configurar GitHub Actions.

Pipeline planejado:

```text
checkout
   ↓
PHP dependencies
   ↓
Node dependencies
   ↓
Laravel Pint / lint
   ↓
Pest
   ↓
frontend build
```

CI deve ser implementada somente depois que os comandos funcionarem corretamente no ambiente local.

---

## 56. Performance

### SHOULD

Evitar:

- N+1 queries
- carregamentos desnecessários
- queries sem escopo de usuário
- chamadas HTTP redundantes
- estado frontend duplicado sem necessidade

Utilizar eager loading quando justificado pelas consultas reais.

Não otimizar prematuramente.

---

## 57. Telescope

### COULD

Laravel Telescope poderá ser utilizado apenas em desenvolvimento para:

- debugging
- inspeção de requests
- queries
- exceptions

Não tornar Telescope requisito funcional da aplicação.

---

## 58. Precognition

### COULD

Laravel Precognition poderá ser avaliado depois que:

- autenticação estiver estável
- CRUD principal estiver pronto
- validações backend estiverem funcionando
- frontend principal estiver estável

Não implementar apenas para aumentar quantidade de tecnologias utilizadas.

---

## 59. Fora do escopo inicial

Não adicionar sem aprovação:

- Redis
- Horizon
- Reverb
- WebSockets
- Passport
- JWT
- OAuth próprio
- Octane
- Scout
- microservices
- CQRS
- Event Sourcing
- Repository Pattern
- filas externas
- serviços pagos

---

## 60. Critérios de conclusão do backend de uma feature

Uma feature backend só deve ser considerada pronta quando aplicável:

- rota implementada
- autenticação configurada
- autorização implementada
- validação implementada
- regra de negócio implementada
- API Resource adequada
- códigos HTTP coerentes
- testes relevantes passando
- Pint executado
- ausência de problemas óbvios de segurança

---

## 61. Critérios de conclusão do frontend de uma feature

Uma feature frontend só deve ser considerada pronta quando aplicável:

- integração real com API
- loading state
- empty state
- tratamento de erro
- validação apresentada adequadamente
- sucesso apresentado adequadamente
- responsividade validada
- navegação funcional
- componentes adequadamente divididos
- ausência de erros relevantes no console
- build funcionando

---

## 62. Critérios de conclusão de uma funcionalidade completa

Antes de considerar uma funcionalidade encerrada:

```text
Backend
    +
Frontend
    +
Autorização
    +
Validação
    +
Testes
    +
UX
    +
Revisão
```

devem estar coerentes entre si.

---

## 63. Ordem de implementação

### Fase 1 — Foundation

Preparar:

- Laravel 13
- MariaDB
- Docker
- Vue 3
- TypeScript
- Vue Router
- Pinia
- Axios
- Tailwind CSS
- Vite
- Laravel Boost
- configuração básica do ambiente

Objetivo:

Ter backend e frontend iniciando corretamente com infraestrutura local reproduzível.

---

## 64. Fase 2 — Authentication

Implementar:

- Fortify
- Sanctum
- CSRF
- sessão
- register
- login
- logout
- `/api/user`
- Auth Store
- guards frontend
- testes

Objetivo:

Ter autenticação SPA segura e persistente.

---

## 65. Fase 3 — Domain foundation

Implementar:

- Models
- Enums
- Migrations
- Relationships
- Factories
- Policies

Entidades:

- Project
- Task
- Tag
- Attachment

Objetivo:

Estabelecer o modelo de domínio e regras de ownership.

---

## 66. Fase 4 — Projects

Implementar CRUD completo de Projects:

- backend
- API
- autorização
- frontend
- estados
- testes

---

## 67. Fase 5 — Tasks

Implementar CRUD completo de Tasks:

- campos obrigatórios
- status
- due date
- completed_at
- autorização
- API
- frontend
- testes

---

## 68. Fase 6 — Tags

Implementar:

- criação
- normalização
- cores
- associação com Tasks
- limites
- autorização
- testes

---

## 69. Fase 7 — Attachments

Implementar:

- upload
- validação
- armazenamento privado
- preview/download autorizado
- exclusão
- cleanup
- testes

---

## 70. Fase 8 — List e Kanban

Implementar:

- alternância de visualização
- Task Cards
- List View
- colunas Kanban
- dados reais da API
- responsividade

Ainda sem priorizar extras.

**Nota de numeração real:** a Fase 8 efetivamente executada foi inteiramente "App Shell + Projects" (não prevista como fase própria neste roadmap original). O conteúdo descrito aqui — List View, Task Cards, Kanban, Tags, Attachments — foi entregue sob o nome real de **Fase 9**, junto com Tags e Attachments (que este roadmap listava em fases anteriores) e sem o Drag and Drop completo da Fase 9 abaixo (ver nota em §71). Os números de fase deste roadmap ficaram, a partir daqui, deslocados em uma unidade em relação à execução real; `docs/DECISIONS.md` §21-§26 é a referência factual do que foi implementado em cada etapa.

---

## 71. Fase 9 — Drag and Drop

Implementar movimentação entre:

- status
- posições

Garantir persistência backend e tratamento de falha.

Adicionar testes relevantes.

**Estado implementado:** a movimentação por `status` (incluindo drag-and-drop nativo em desktop, status-only) foi entregue dentro da Fase 9 real (ver nota em §70), reaproveitando o `PATCH /api/tasks/{task}` já existente. Movimentação por `posições` (reorder) permanece fora de escopo — não há endpoint de move/reorder nem escrita de `position` pelo cliente (ver `docs/DECISIONS.md` §10, §22, §26).

---

## 72. Fase 10 — Product polish

Implementar SHOULDs prioritários:

- busca
- filtros
- métricas
- empty states
- feedback visual
- melhoria responsiva
- refinamentos de formulário

Avaliar Precognition somente aqui ou depois.

---

## 73. Fase 11 — Quality

Executar revisão ampla:

- segurança
- autorização
- queries
- N+1
- frontend console
- responsividade
- Pint
- Pest
- build
- revisão do Claude
- revisão humana
- cleanup

---

## 74. Fase 12 — Delivery

Preparar:

- GitHub Actions
- deploy
- README final
- documentação de IA
- revisão de SPEC
- revisão de decisões
- vídeo de demonstração
- arquitetura explicada
- decisões técnicas explicadas

---

## 75. Prioridade em caso de falta de tempo

Seguir esta ordem:

```text
1. Funcionalidade obrigatória
2. Segurança
3. Estabilidade
4. Testes essenciais
5. Qualidade visual e UX
6. Busca/filtros/métricas
7. Diferenciais opcionais
```

Não sacrificar funcionalidades obrigatórias para adicionar tecnologia demonstrativa.

---

## 76. Definition of Done do MVP

O MVP estará funcional quando um usuário conseguir:

1. criar conta
2. entrar no sistema
3. permanecer autenticado
4. criar um projeto
5. editar um projeto
6. excluir um projeto
7. criar tarefas
8. editar tarefas
9. excluir tarefas
10. definir prazo
11. utilizar os quatro status
12. associar tags
13. adicionar attachments
14. visualizar tarefas em lista
15. visualizar tarefas em Kanban
16. mover tarefas no Kanban
17. sair do sistema

Tudo respeitando ownership entre usuários.

---

## 77. Critério final de qualidade

O projeto deve demonstrar:

- domínio de Laravel
- domínio de Vue
- construção de API REST
- modelagem relacional
- segurança
- autorização
- testes
- Git
- capacidade de interpretar especificações
- qualidade visual
- responsividade
- uso consciente de IA
- capacidade de revisar criticamente código gerado por IA

A implementação deve parecer uma aplicação construída com intenção técnica e de produto, e não apenas código produzido automaticamente.
