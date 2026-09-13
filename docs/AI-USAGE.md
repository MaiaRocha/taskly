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

**Manuais**, feitos pelo humano no navegador, incluindo (conforme o checkpoint): desktop e mobile, aba Network, criar/editar/excluir Task, Tags (seleção e criação), tarefas atrasadas (`overdue`), Kanban (drag e menu de mudança de status), empilhamento de `ConfirmDialog`, upload/download/exclusão de anexos, e navegação entre Tasks/Projects para checar isolamento de estado.

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
- **Tasks UI / Kanban / Attachments Frontend (Fase 9)** — descrita em detalhe abaixo, por ser a fase mais recente e com contexto completo disponível.

Para as fases anteriores à Fase 9, este documento registra apenas o que é factualmente verificável pelo histórico de commits e pela documentação existente (`docs/DECISIONS.md` §21-§24) — sem reconstruir prompts ou decisões daquelas fases por suposição.

**Fase 9**, executada em checkpoints (A a G), cobriu: Tasks Store/Tags Store com proteção contra resposta obsoleta; List View e `TaskCard`; Task Modal (create/edit/delete); Tags (seleção + criação inline); Kanban com troca de status via menu e via drag-and-drop nativo em desktop, responsivo em 3 níveis; Attachments (upload/list/download/delete) com lifecycle isolado por componente; e o fechamento com robustecimento da primitiva `Modal.vue`, revisão de código morto e atualização desta documentação. Os exemplos B a H acima pertencem todos a esta fase.

## Limitações e responsabilidade humana

A IA foi usada como agente de implementação e como camada de revisão — nunca como decisora final. Toda decisão de escopo, arquitetura, UX e priorização foi validada por um humano antes de ser considerada definitiva. Código gerado por IA foi tratado como rascunho sujeito a revisão, não como entrega automática: passar type-check, build e testes automatizados foi tratado como condição necessária, mas não suficiente, para considerar um checkpoint concluído — a validação manual sempre teve a palavra final.
