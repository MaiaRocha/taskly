# Uso de IA no desenvolvimento do Taskly

Este documento registra, de forma curada e factual, como ferramentas de IA foram usadas na construção do Taskly. Não é um log bruto de conversas — é um resumo técnico do processo, das decisões e das correções relevantes, para avaliação do desafio.

## Estratégia de trabalho

O desenvolvimento seguiu um modelo de **checkpoints pequenos e supervisionados**: cada fase do roadmap (`docs/SPEC.md` §60+) foi dividida em checkpoints com escopo explícito e restrito, implementados um de cada vez, sempre com aprovação humana antes de avançar para o próximo. Nenhuma alteração de arquitetura, dependência, backend ou escopo foi feita sem instrução explícita. Commits só acontecem quando o humano decide fechar uma fase — a IA nunca fez stage ou commit por conta própria.

## Ferramentas utilizadas

### Claude Code
Agente principal de implementação, operando diretamente neste repositório. Responsável por:
- ler o código e os contratos reais (rotas, Controllers, Requests, Resources, testes) antes de implementar, em vez de assumir formatos;
- planejar e implementar cada checkpoint dentro do escopo definido;
- executar checks (`vue-tsc`, build, Pest, `git diff --check`) e reportar resultados;
- produzir um relatório estruturado ao final de cada checkpoint.

### ChatGPT
Usado fora do repositório, como camada de revisão e QA:
- revisão dos planos e prompts antes de serem enviados ao Claude Code;
- crítica arquitetural e de escopo;
- refino da redação dos prompts de checkpoint;
- QA dos relatórios devolvidos pelo Claude Code;
- análise de screenshots/UX e levantamento de riscos antes do checkpoint seguinte.

ChatGPT não teve acesso de escrita ao repositório em nenhum momento — sua contribuição é sempre anterior à implementação (planejamento/prompt) ou posterior a ela (revisão do resultado).

### Humano (Maia Rocha)
Autoridade final sobre todas as decisões:
- aprovação de cada checkpoint antes do próximo;
- testes manuais no navegador (desktop e mobile);
- avaliação visual e de UX;
- validação de fluxos completos (criar, editar, excluir, mover, anexar);
- decisão final sobre escopo, prioridade e trade-offs;
- rejeição ou pedido de refinamento sempre que uma solução proposta não atendia ao esperado.

## Fluxo de trabalho

1. Um requisito ou checkpoint é definido, com escopo explícito e exclusões claras.
2. O agente de implementação inspeciona o repositório real (código, contratos de API, documentação) antes de propor ou implementar qualquer coisa.
3. O plano/abordagem é revisado criticamente antes da implementação começar.
4. A implementação só ocorre após essa revisão.
5. O agente executa os checks obrigatórios do checkpoint (type-check, build, testes de backend, diff).
6. O humano testa manualmente no navegador — desktop e mobile — os fluxos daquele checkpoint.
7. Problemas encontrados no teste manual voltam para uma nova rodada de diagnóstico e correção, antes de o checkpoint ser considerado fechado.
8. O commit só acontece quando uma fase inteira é fechada — nunca durante os checkpoints intermediários.

Esse processo prioriza checkpoints pequenos e revisáveis, um working tree sempre inspecionável antes de qualquer stage, e nenhuma alteração automática sem revisão humana.

## Prompting / Context Engineering

Os prompts enviados ao agente de implementação seguiam uma estrutura consistente, para reduzir scope creep e manter as decisões rastreáveis:

- escopo exato do checkpoint (o que implementar, e só isso);
- arquivos e contratos reais a inspecionar antes de implementar (nunca assumir formato de payload/rota);
- lista explícita do que **não** implementar naquele checkpoint;
- riscos já conhecidos a verificar (ex.: empilhamento de dialogs, concorrência);
- checks obrigatórios a rodar antes de reportar;
- formato esperado do relatório final;
- instrução explícita para parar antes de qualquer stage/commit.

Alguns trechos curtos e representativos desse padrão (não os prompts completos):

> "NÃO avance para o próximo checkpoint."

> "Confirme o contrato real antes de implementar."

## Exemplos reais de decisões e revisões

**A. Identidade visual própria** — a revisão visual da UI de Auth/Projects reforçou a diretriz já registrada em `docs/UI-UX.md` (§53) de que o ClickUp é referência de organização/UX, nunca um alvo de cópia literal — o Taskly manteve linguagem visual própria.

**B. Task Drawer → Task Modal** — a criação/edição de Task foi inicialmente implementada com um Drawer lateral. Após revisão visual, decidiu-se que essa experiência deveria usar um Modal central, para manter consistência com o Modal de Project. A implementação foi refeita nesse sentido antes do fechamento da fase.

**C. Bug real no ConfirmDialog de exclusão de Task** — o teste manual encontrou que o botão "Excluir tarefa" não abria o diálogo de confirmação. O diagnóstico (feito lendo o código, não por suposição) identificou a causa: o `ConfirmDialog` nascia com `open` já `true` atrás de um `v-if`, e o `watch` interno da primitiva `Modal` não observava esse estado inicial. A correção imediata foi no caller; mais tarde, no Checkpoint G de fechamento da fase, a própria primitiva `Modal.vue` foi robustecida para tratar corretamente o caso `open=true` já no mount, eliminando a classe inteira desse bug. Um bom exemplo do ciclo real: implementação → teste manual → bug encontrado → diagnóstico → correção pontual → correção estrutural.

**D. Kanban sem reorder** — o Kanban foi inicialmente planejado sem drag-and-drop, porque o backend não expõe um contrato de reorder/posição. Após avaliação de UX, drag-and-drop nativo (HTML5, sem biblioteca) foi adicionado somente para mudança de **status** — o `PATCH` continua enviando apenas `status`, `position` nunca é escrita pelo cliente, e o menu "..." permanece como mecanismo de fallback acessível e mobile.

**E. Kanban mobile** — a primeira versão do Kanban responsivo usava scroll horizontal entre as 4 colunas em qualquer tamanho de tela; o teste manual em mobile mostrou que essa experiência não era boa. O layout foi refeito em 3 níveis (desktop: 4 colunas; tablet: 2 colunas; mobile: uma coluna por vez com um seletor de status), e o mobile passou a depender exclusivamente do menu "...", sem drag por toque.

**F. Concorrência no Kanban** — uma revisão dedicada encontrou que um único `movingTaskId` não representava corretamente duas Tasks sendo movidas ao mesmo tempo (uma poderia limpar o estado de "movendo" da outra). Corrigido para um `Set<number>` (`movingTaskIds`).

**G. Lifecycle de Attachments** — antes da homologação do checkpoint, uma revisão dedicada identificou que uma resposta de upload/exclusão de anexo resolvendo depois que o Task Modal já havia sido fechado ainda conseguia alterar o Pinia Store. Foi adicionado um token de geração (`generation`) local ao componente, invalidando qualquer resposta fora de contexto; o `GET` da lista de anexos passou também a reconciliar `attachments_count` como fonte de verdade mais recente.

**H. Isolamento de sessão** — os stores de Tasks e Tags usam um `sessionEpoch` (e, no caso de Tasks, uma `generation` por Project) para impedir que uma requisição iniciada antes de um logout aplique seus dados depois que a sessão já terminou.

**I. Planejamento da Fase 10 reiniciado** — a primeira resposta de planejamento para a Fase 10 devolveu, por engano, um plano antigo referente à Fase 9. A revisão humana rejeitou esse plano, e um novo planejamento foi feito do zero sobre a base real (`b8a0895`), passando por três rodadas de revisão crítica antes da aprovação.

**J. Busca/filtros: decisão client-side documentada como divergência** — `docs/SPEC.md` recomendava busca executada no backend. A decisão final foi implementar client-side, já que o Project carrega sua coleção de Tasks completa e sem paginação — a preocupação de segurança por trás da recomendação original (não carregar dados de outro usuário para filtrar no frontend) não se aplicava a essa arquitetura. A divergência foi registrada explicitamente em `docs/SPEC.md`/`docs/DECISIONS.md`, com o caminho de migração para backend caso paginação seja introduzida no futuro — não foi silenciosamente ignorada.

**K. Contrato de URL refinado em múltiplas rodadas** — o desenho de `useTaskFilters` passou por revisões sucessivas antes da implementação: separação entre `selectedTagIds` (bruto da URL) e `effectiveTagIds` (validado contra as Tags reais da sessão), serialização de Tags como array na URL, e principalmente os cenários de corrida do debounce da busca (ex.: digitar e clicar "Limpar filtros" antes de 300ms) foram identificados e endereçados antes de qualquer linha de código ser escrita.

**L. Checkpoints agrupados por afinidade** — os checkpoints A (engine/URL), B (UI de busca/filtros) e C (métricas) foram implementados numa única rodada para acelerar a execução, por serem partes da mesma camada sem efeito colateral entre si. O checkpoint D (integração com List/Kanban/mobile) foi mantido separado por envolver mudança de comportamento observável (Cards realmente filtrados, alinhamento do Kanban mobile).

**M. Bug real de layout encontrado em QA visual** — depois da implementação do checkpoint de UI de filtros, a validação humana no navegador mostrou que os controles não ficavam todos na mesma linha no desktop, embora as classes parecessem corretas na leitura do código. O diagnóstico (inspeção do componente `Dropdown` real, não suposição) encontrou a causa: o filtro de Tags usava `<Dropdown>` sem a prop `inline`, e a primitiva assume `w-full` por padrão nesse caso — distorcendo o dimensionamento da toolbar. A correção foi mínima (adicionar `inline`), e o layout final foi validado manualmente em desktop e mobile.

**N. Task Activity History escolhido como diferencial além do mínimo** — a Fase 11 introduziu uma funcionalidade não exigida pelo escopo mínimo do MVP (`docs/SPEC.md` §76): um histórico de atividades por Task, somente leitura. O planejamento inicial foi revisado criticamente antes de qualquer implementação, resultando em aprovação "com 3 ajustes obrigatórios" — entre eles, um índice composto (`task_id, created_at, id`) que estava ausente do desenho original da migration e foi adicionado antes do Checkpoint A, já que é exatamente o índice que a única query da tabela (timeline de uma Task, mais recente primeiro) precisa.

**O. UTC ISO-8601 com `Z` como formato único da feature** — decidido que todo timestamp dentro de Activities (snapshots de `due_at` e o `created_at` do próprio Resource) usaria `toIso8601ZuluString()` (`2026-09-15T21:00:00Z`), e não o `.000000Z` padrão do restante da aplicação — verificado em teste de contrato exato antes de generalizar a decisão para toda a feature.

**P. Risco de filesystem identificado antes do commit, corrigido em B.1** — a primeira versão da exclusão de Attachment (Checkpoint B) apagava o arquivo físico antes de commitar a exclusão da linha no banco. A própria revisão identificou e reportou o risco (uma falha na transação depois do arquivo já apagado deixaria uma linha válida apontando para um arquivo inexistente) em vez de seguir adiante silenciosamente. O Checkpoint B.1 inverteu a ordem: banco primeiro (linha + Activity, atômico), limpeza física do arquivo só depois do commit, e só best-effort — uma falha aí é registrada em log, nunca vira exceção.

**Q. Lacuna real na exclusão de Tag, encontrada por revisão** — a revisão do Checkpoint B identificou que excluir uma Tag globalmente a desassociava de toda Task via cascade de FK do banco, sem nenhum caminho de código de aplicação envolvido — logo, sem nenhuma Activity sendo registrada nessas Tasks. Reportado antes de agir; corrigido no Checkpoint B.1, lendo as Tasks associadas à Tag antes do delete (o cascade destrói o pivot, única evidência de quais Tasks tinham aquela Tag) e registrando uma `tags_changed` por Task afetada, na mesma transação do delete.

**R. Estratégia de refresh reduzida após investigar o lifecycle real do Modal** — o desenho inicial do Checkpoint D cogitava refresh explícito para toda mutação relevante (status, due_at, Tags, Attachments). Uma investigação direta do código real (`TaskModal.vue`, `ProjectDetailPage.vue`) mostrou que salvar uma Task — create ou edit, incluindo o sync de Tags — sempre fecha o Modal, desmontando a Timeline inteira; reabrir a mesma Task já dispara um lazy load do zero. O escopo do refresh explícito foi então reduzido, corretamente, só para Attachments (upload/delete) — o único fluxo real que muta a Task com o Modal ainda aberto.

**S. Bug real de concorrência (`isLoadingMore` preso), encontrado e corrigido antes da homologação** — uma revisão dedicada de concorrência em `TaskActivityTimeline.vue` encontrou que `isLoadingMore` podia ficar `true` para sempre em dois cenários: um refresh invalidando um load-more em andamento, e uma troca de Task durante uma requisição em voo. O guard de resposta obsoleta (`isStale`) pulava deliberadamente o reset dessa flag para uma resposta descartada — correto nesse ponto isolado — mas nada mais a resetava depois disso. Corrigido antes da homologação manual, adicionando o reset explícito exatamente nos dois pontos que invalidam essas requisições (o próprio `refresh()` e a troca de `taskId`).

**T. Homologação manual confirmou o comportamento esperado** — a validação humana no navegador (incluindo a aba Network) confirmou lazy loading, renderização correta de cada tipo de evento, contador, integração no Task Modal e o refresh explícito de Attachments funcionando como projetado, sem regressão visível no Kanban ou no Task Modal.

**U. 500 no Kanban durante QA — ambiente local, não bug de aplicação** — durante a homologação manual do Checkpoint D, o Kanban retornou 500 por uma migration ainda não aplicada no banco de desenvolvimento local (a suíte de testes já rodava normalmente via `LazilyRefreshDatabase`, que aplica migrations no banco de teste automaticamente). Diagnosticado corretamente como pendência de ambiente, não regressão de código, e resolvido executando a migration localmente.

**V. Oportunidade de UX identificada antes do fechamento da Fase 11** — durante a revisão final, antes do commit da fase, a revisão humana notou que alterar o status de uma Task na Lista exigia abrir o Task Modal inteiro, mesmo sendo uma ação simples e frequente. Implementada como Fase 11.1: a badge de status da Lista virou um controle inline (menu com os 4 status), reutilizando o mesmo `PATCH` parcial que o Kanban já usava — sem endpoint novo, sem lógica de backend duplicada, e sem alterar o comportamento do Kanban.

## Revisão crítica da IA

Nenhum output do agente foi aceito automaticamente. Além dos exemplos acima:
- soluções visuais propostas foram ajustadas após avaliação de screenshots reais, não aceitas de primeira;
- a proposta inicial de drag-and-drop por toque no mobile foi rejeitada em favor de uma interação adequada a touch (seletor de status + menu), evitando complexidade desnecessária de gestos customizados;
- mais de um bug real (ex.: item C acima) só foi descoberto por teste manual no navegador, apesar de type-check, build e testes de backend passarem — checks automáticos comprovam ausência de regressão de contrato, não corretude de UX/fluxo.

## Testes e validação

**Automáticos**, rodados a cada checkpoint:
- `npx vue-tsc --noEmit`
- `npm run build`
- `php artisan test --compact` (Pest)
- `git diff --check`

**Manuais**, feitos pelo humano no navegador, incluindo (conforme o checkpoint): desktop e mobile, aba Network, criar/editar/excluir Task, Tags (seleção e criação), tarefas atrasadas (`overdue`), Kanban (drag e menu de mudança de status), empilhamento de `ConfirmDialog`, upload/download/exclusão de anexos, histórico de atividades da Task (lazy loading via Network, renderização de cada tipo de evento, refresh após upload/exclusão de anexo), e navegação entre Tasks/Projects para checar isolamento de estado.

Os checks automáticos nunca substituíram a validação manual — vários dos bugs reais encontrados (ex.: item C) só apareceram em teste manual, com todos os checks automáticos passando.

## Segurança

- nenhuma credencial ou segredo foi incorporado ao código;
- nenhum token de autenticação é armazenado em `localStorage`/`sessionStorage` — o frontend usa exclusivamente a sessão Sanctum já existente (cookie HttpOnly);
- autorização continua sendo responsabilidade do backend (Policies) em todos os endpoints; o frontend nunca foi tratado como fonte de verdade para ownership;
- o agente de implementação não teve autorização para alterar backend, rotas, migrations ou dependências fora do escopo explicitamente aprovado em cada checkpoint;
- qualquer mudança estrutural ou dependência nova exigia aprovação explícita antes de ser implementada.

## Registro curado por fase

Fases já registradas no histórico do repositório (commits reais):

- **Foundation** — inicialização e conclusão da base do projeto Laravel + Vue.
- **Authentication** — autenticação baseada em sessão (Fortify + Sanctum).
- **Domain** — modelagem do domínio (User, Project, Task, Tag, Attachment).
- **Projects API** — CRUD de Projects.
- **Tasks API** — CRUD de Tasks, status, `completed_at`, `overdue`.
- **Tags API** — Tags e sincronização Task ↔ Tag.
- **Attachments API** — upload/listagem/download/exclusão privados de anexos.
- **Projects Frontend** — App Shell, Sidebar, autenticação, CRUD de Projects no SPA.
- **Tasks UI / Kanban / Attachments Frontend (Fase 9)** — descrita em detalhe abaixo.
- **Busca, Filtros e Métricas de Tasks (Fase 10)** — descrita em detalhe abaixo.
- **Task Activity History (Fase 11)** — descrita em detalhe abaixo, por ser a fase mais recente e com contexto completo disponível.

Para as fases anteriores à Fase 9, este documento registra apenas o que é factualmente verificável pelo histórico de commits e pela documentação existente (`docs/DECISIONS.md` §21-§24) — sem reconstruir prompts ou decisões daquelas fases por suposição.

**Fase 9**, executada em checkpoints (A a G), cobriu: Tasks Store/Tags Store com proteção contra resposta obsoleta; List View e `TaskCard`; Task Modal (create/edit/delete); Tags (seleção + criação inline); Kanban com troca de status via menu e via drag-and-drop nativo em desktop, responsivo em 3 níveis; Attachments (upload/list/download/delete) com lifecycle isolado por componente; e o fechamento com robustecimento da primitiva `Modal.vue`, revisão de código morto e atualização desta documentação. Os exemplos B a H acima pertencem todos a esta fase.

**Fase 10**, executada em checkpoints (A+B+C numa rodada, D, e E de fechamento), cobriu: engine de busca/filtros client-side (`useTaskFilters`) com URL como estado canônico e busca com debounce protegido contra corrida; toolbar de filtros (`TaskFilters.vue`) e métricas (`TaskMetrics.vue`); integração real com List/Kanban (incluindo o alinhamento do Kanban mobile ao filtro global de status); distinção entre Project vazio e filtro sem resultado; e o fechamento com revisão completa do código, segurança e esta documentação. Os exemplos I a M acima pertencem todos a esta fase.

**Fase 11**, executada em checkpoints (planejamento; A — domínio; B — geração de eventos; B.1 — hardening de consistência; C — API + Timeline; D — refresh + UX final; E de fechamento; 11.1 — status inline na Lista), cobriu: a tabela/Model/enum/Action de `TaskActivity`; integração da geração de eventos nos fluxos reais de Task/Tags/Attachments/Tag; um endpoint somente leitura paginado; uma Timeline no Task Modal com lazy loading, paginação por botão e refresh explícito restrito ao único fluxo que o exige (Attachments); o fechamento com revisão de concorrência, portabilidade de teste de filesystem e esta documentação; e, como pequena evolução de UX antes do commit, a badge de status da Lista virando um controle inline de troca de status. Os exemplos N a V acima pertencem todos a esta fase.

## Limitações e responsabilidade humana

A IA foi usada como agente de implementação e como camada de revisão — nunca como decisora final. Toda decisão de escopo, arquitetura, UX e priorização foi validada por um humano antes de ser considerada definitiva. Código gerado por IA foi tratado como rascunho sujeito a revisão, não como entrega automática: passar type-check, build e testes automatizados foi tratado como condição necessária, mas não suficiente, para considerar um checkpoint concluído — a validação manual sempre teve a palavra final.
