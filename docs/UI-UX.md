# Taskly — Diretrizes de UI/UX

## Status

Este documento define a direção visual, os padrões de experiência e as regras de frontend do Taskly.

Ele deve ser utilizado em conjunto com:

- `docs/DECISIONS.md`
- `docs/SPEC.md`

A implementação não deve copiar literalmente produtos existentes.

A referência principal de organização e experiência é o ClickUp, utilizando seus princípios de produtividade e organização como inspiração, mas mantendo identidade própria para o Taskly.

Toda a interface visível ao usuário final é escrita em pt-BR. Código, nomes de tipos, componentes e rotas permanecem em inglês.

---

## 1. Objetivo visual

O Taskly deve transmitir:

- modernidade
- produtividade
- organização
- clareza
- leveza
- profissionalismo
- personalidade visual

A aplicação deve parecer um produto real e bem acabado, e não:

- um painel administrativo genérico
- um template pronto
- uma interface gerada automaticamente por IA
- uma cópia do ClickUp

---

## 2. Direção geral

A interface será:

- light mode
- moderna
- colorida de forma controlada
- produtiva
- relativamente densa
- organizada
- responsiva

O objetivo é permitir que o usuário visualize bastante informação sem tornar a aplicação visualmente pesada.

---

## 3. Referência ClickUp

O ClickUp será utilizado como referência principalmente para:

- hierarquia visual
- organização de projetos
- navegação lateral
- densidade de informação
- organização do Kanban
- apresentação das tarefas
- produtividade
- uso de cores
- interação através de drawers

Não copiar:

- logotipo
- ícones proprietários
- layout pixel a pixel
- textos
- branding
- componentes exclusivos

O Taskly deve possuir identidade própria.

---

## 4. Tema

Inicialmente haverá somente:

```text
Light Mode
```

Dark Mode não faz parte do escopo inicial.

O tema claro deve possuir:

- fundo geral levemente acinzentado
- superfícies brancas
- bordas suaves
- sombras discretas
- bom contraste

O fundo pode incorporar uma camada decorativa atmosférica ("aurora"): manchas suaves e difusas nas cores lavanda (Primary), azul e ciano, sempre puramente decorativas (nunca capturam clique ou foco) e posicionadas atrás de todo conteúdo funcional. A intensidade dessa camada varia por contexto — mais expressiva no Auth (§49/§50), sensivelmente mais discreta na área autenticada (§12) — mas nunca compromete a leitura de texto, cards, sidebar ou navegação, que permanecem superfícies opacas.

---

## 5. Paleta principal

Direção de cores:

```text
Primary:       #635BFF
Primary Hover: #554EE8
Primary Soft:  #EEEDFF
```

Cores auxiliares permitidas:

```text
Cyan:    #06B6D4
Teal:    #14B8A6
Pink:    #EC4899
Orange:  #F59E0B
Green:   #22C55E
Blue:    #3B82F6
```

O seletor de cor de Projeto (e futuramente Tag) é restrito a exatamente essas seis cores auxiliares, através de inputs `radio` nativos (visualmente estilizados, mas semanticamente reais) — nunca um seletor de cor livre. A cor Primary nunca aparece como opção nesse seletor.

Bases:

```text
Page Background: #F7F8FC
Surface:         #FFFFFF
```

As cores auxiliares devem ser usadas para:

- projetos
- tags
- status
- indicadores
- destaques sutis

Evitar grandes áreas com cores muito saturadas.

---

## 6. Tipografia

Fonte preferencial:

```text
Inter
```

Fallback adequado deve ser configurado.

A tipografia deve priorizar legibilidade.

Hierarquia sugerida:

```text
Page Title
Section Title
Card Title
Body
Metadata
Caption
```

Evitar títulos excessivamente grandes.

O Taskly é uma ferramenta de produtividade, portanto a densidade visual deve continuar eficiente.

---

## 7. Espaçamento

Utilizar escala consistente baseada em múltiplos de aproximadamente:

```text
4px
```

Priorizar espaçamentos como:

```text
4
8
12
16
20
24
32
```

Evitar valores arbitrários sem necessidade.

---

## 8. Bordas e sombras

Usar:

- bordas suaves
- radius moderado
- sombras discretas

Cards não devem parecer excessivamente flutuantes.

A separação entre elementos deve depender principalmente de:

- espaçamento
- contraste
- bordas sutis

e não de sombras fortes.

---

## 9. Ícones

Biblioteca aprovada:

```text
Lucide Icons
```

Pacote frontend esperado:

```text
@lucide/vue
```

Ícones devem complementar textos e ações.

Ações pouco óbvias não devem depender exclusivamente de ícones sem:

- label
- tooltip
- contexto suficiente

---

## 10. Componentes fundamentais

A aplicação deverá possuir componentes reutilizáveis quando fizer sentido.

Componentes previstos:

- Button
- IconButton
- Input
- Textarea
- Select
- MultiSelect
- Badge
- Avatar
- Dropdown
- Popover
- Tooltip
- Dialog
- Drawer
- Toast
- Skeleton
- Spinner
- Tabs
- Card
- DateTimePicker
- ConfirmDialog

Não criar abstrações prematuras.

Componentes devem surgir de padrões realmente reutilizados.

Diálogos modais (Dialog/Modal e Drawer) devem ser implementados sobre o elemento nativo `<dialog>` (`showModal()`/`close()`), aproveitando o gerenciamento nativo de foco, tecla Escape e camada de topo do navegador — sem reimplementar manualmente um focus trap.

Cards de listagem (ex.: Project Card) usam um único acento de cor — um indicador (dot) ao lado do nome — nunca uma borda lateral colorida adicional, mantendo o acento discreto e único por card.

---

## 11. shadcn-vue

`shadcn-vue` poderá ser utilizado seletivamente.

Caso utilizado:

- não manter aparência padrão sem adaptação
- personalizar cores
- ajustar spacing
- ajustar radius
- adaptar à identidade Taskly

O resultado final não deve parecer um projeto demonstrativo do shadcn.

---

## 12. App Shell

Em desktop, a aplicação autenticada segue:

```text
┌───────────────┬──────────────────────────────┐
│               │                              │
│   Sidebar     │        Main Content          │
│   (fixa)      │                              │
│               │                              │
└───────────────┴──────────────────────────────┘
```

Não há uma Top Bar persistente no desktop: a Sidebar concentra marca, navegação de projetos e menu do usuário, o que tornaria uma barra superior fixa adicional redundante. Cada página é responsável pelo próprio cabeçalho de contexto (título, descrição, ações) e pela própria largura máxima de conteúdo — o App Shell não impõe um `max-width` global.

Em mobile, o Shell exibe a barra compacta descrita em §13.

A área autenticada reutiliza a mesma linguagem de aurora do Auth (§4), com intensidade sensivelmente menor — uma textura de fundo, não um elemento de destaque. Essa camada é puramente decorativa e fica atrás de Sidebar e conteúdo; ambos permanecem superfícies opacas e não perdem contraste por causa dela. A decoração não participa da hierarquia funcional da tela.

---

## 13. Barra superior (mobile)

Diferente do App Shell em desktop, uma barra superior compacta existe **somente** em mobile/tablet (abaixo do breakpoint descrito em §40), quando a Sidebar fixa não está visível.

Ela contém apenas:

- botão de menu (abre o Drawer de navegação, ver §15)
- identidade Taskly

Marca, navegação e menu do usuário já vivem na Sidebar em qualquer largura — a barra mobile existe só para abrir o Drawer, não para replicar ações ou contexto. Contexto de página e ações específicas ficam no cabeçalho de cada página, não numa barra global.

---

## 14. Sidebar — Desktop

Em desktop, a Sidebar será fixa.

Largura final:

```text
256px
```

Deve conter:

- marca Taskly
- acesso aos projetos (a própria seção "Projetos" já é o link para a listagem — não existe uma página separada de "Visão geral")
- criação rápida de projeto ("+" ao lado da seção Projetos)
- lista real de projetos, cada um com um indicador de cor (dot) e nome
- área do usuário no rodapé (menu com opção de sair)

O projeto ativo é determinado pela rota atual (`/projects/:projectId`), nunca por um estado replicado em store — evita duas fontes de verdade para a mesma informação. A lista de projetos rola de forma independente entre a marca e a área do usuário, que permanece sempre visível.

A Sidebar deve permitir acesso rápido entre projetos.

---

## 15. Navegação — Mobile

Em mobile, a Sidebar fixa desaparece.

Utilizar:

```text
Navigation Drawer
```

acionado pelo menu da Top Bar.

O usuário deve conseguir:

- visualizar projetos
- trocar de projeto
- criar projeto
- acessar ações principais

sem comprometer a área útil da tela.

---

## 16. Página do projeto

A página principal de um projeto deverá possuir conceitualmente:

```text
Project Header
    ↓
Metrics
    ↓
Toolbar
    ↓
List View ou Kanban View
```

---

## 17. Project Header

Deve apresentar:

- nome do projeto
- cor do projeto
- descrição quando relevante
- ações do projeto

Ações secundárias poderão ficar em menu contextual.

Evitar ocupar espaço excessivo.

---

## 18. Métricas

`TaskMetrics.vue` mostra 4 indicadores compactos: Total (inclui canceladas), Em andamento, Concluídas, Atrasadas — sempre derivados da coleção completa do Project (nunca da lista filtrada, mesmo com filtros ativos). Layout: grid 2x2 no mobile, uma linha (`flex-wrap`) a partir de `sm:`. Informação rápida, texto sempre presente (número + rótulo, nunca só cor) — sem virar dashboard de BI.

---

## 19. Toolbar

`TaskFilters.vue` é uma única toolbar (card com borda/sombra), na ordem:

```text
[ Buscar tarefas... ] [ Todos os status ] [ Tags ] [ Atrasadas ] [ Limpar filtros ] [ X de Y tarefas ]
```

A partir de `md:` (768px), tudo numa única linha — a busca é o único controle flexível (`flex-1 min-w-0`, absorve o espaço disponível), os demais mantêm largura natural (`shrink-0`), e "Limpar filtros"/contador ficam alinhados à direita (`ml-auto`) quando existem. Abaixo de `md:`, empilha: busca (full-width) → status (full-width) → Tags+Atrasadas (agrupados) → Limpar+contador (agrupados, quebrando entre si só se necessário) — sem scroll horizontal da página.

Filtro por prazo foi implementado como toggle "Somente atrasadas" (booleano), não um intervalo de datas. Botão "Nova tarefa" continua no cabeçalho do Project (não na toolbar de filtros) — ver §17.

---

## 20. Seletor de visualização

O usuário deverá conseguir alternar claramente entre:

```text
List
Kanban
```

A opção ativa deve ficar visualmente evidente.

A troca não deve causar navegação confusa.

---

## 21. Task Modal

Criação e edição de tarefas utilizam um Modal central (`Modal.vue`, variante `size="lg"`), não um Drawer lateral — mesma família visual (arredondamento, borda, sombra, header com título e X, footer, backdrop) já usada pelo Modal de Project, para manter um único padrão de diálogo modal no app. Essa decisão substitui a intenção original de Drawer lateral, revisada visualmente antes do fechamento da Fase 9.

### Desktop

O Modal é centralizado, com largura própria de `size="lg"` (mais largo que o Modal padrão de Project, para acomodar campos + Tags + Attachments), sem ocupar a tela inteira — o restante permanece visível como backdrop.

### Mobile

O mesmo `Modal.vue` se adapta à largura disponível (com padding lateral mínimo), permanecendo confortável em telas a partir de ~360-390px, sem exigir scroll horizontal.

---

## 22. Conteúdo do Task Modal

O Modal (via `TaskForm.vue`) suporta:

- título
- descrição curta
- descrição completa
- status
- prazo
- tags (seleção + criação inline)
- anexos (`TaskAttachments.vue`, apenas em modo edição — ver §32)
- ações de salvar
- exclusão (ação discreta no rodapé do formulário, não no header)

Anexos não fazem parte do submit dos campos da tarefa — têm ciclo de vida próprio (endpoints dedicados), e só aparecem depois que a tarefa já existe (ver §32).

---

## 23. Criação e edição

Criação e edição de tarefa compartilham o mesmo Modal e o mesmo componente de formulário (`TaskForm.vue`), com o título do Modal ("Nova tarefa" / "Editar tarefa") e o rótulo do botão de ação deixando claro qual operação está em andamento.

---

## 24. Kanban

O Kanban é uma das experiências centrais do Taskly.

Colunas:

```text
Não iniciada
Em andamento
Concluída
Cancelada
```

Cada coluna deve apresentar:

- título
- quantidade de tarefas
- identificação visual discreta do status
- cards

---

## 25. Cores do Kanban

Não utilizar colunas inteiras com fundos fortemente saturados.

Utilizar cores em elementos como:

- indicador no header
- pequena faixa
- badge
- ícone
- detalhe visual

O foco deve permanecer no conteúdo das tarefas.

---

## 26. Task Cards

O mesmo `TaskCard.vue` é usado por List e Kanban, com uma prop `variant` controlando a diferença de conteúdo.

No Kanban (`variant="kanban"`), o card apresenta, quando disponíveis:

- título
- tags
- prazo
- indicador de atraso
- contagem de anexos (ícone `Paperclip` + número, só quando > 0)
- menu "..." (mudar status)

Sem repetir o status (já é a coluna) e sem `short_description` (mantém o card compacto o suficiente para caber várias colunas lado a lado). Na List (`variant="list"`), o card também mostra o badge de status e a `short_description`.

---

## 27. Drag and Drop

Drag and Drop nativo (HTML5, sem biblioteca) está disponível apenas em **desktop**, apenas para **mudar o status** de uma Task — nunca para reordenar dentro de uma coluna (drop na própria coluna é no-op) e nunca para escrever `position` (o backend não expõe esse contrato ainda).

Durante a movimentação:

- o card arrastado recebe opacidade reduzida + sombra mais forte, com cursor `grab`/`grabbing` (o menu "..." mantém cursor normal, não herda o `grab`);
- a coluna de destino válida recebe um destaque sutil (`primary-soft` + anel), nunca uma cor saturada cobrindo a coluna inteira;
- o card só troca de coluna visualmente depois que o `PATCH` de status é confirmado (sem movimento otimista) — em caso de erro, o card permanece na coluna original e um toast de erro é exibido;
- terminar um arraste real nunca também abre o Modal de edição (clique e drag são distinguidos de forma simples e robusta).

Em dispositivos com ponteiro grosso (touch), o `draggable` é desativado automaticamente — nesses dispositivos a mudança de status acontece exclusivamente pelo menu "...", que permanece funcional em qualquer tamanho de tela como mecanismo de fallback confiável (teclado, mobile, acessibilidade).

---

## 28. Kanban responsivo

O layout do Kanban responde à largura real do container (via CSS container queries, já que a Sidebar afeta o espaço disponível — não apenas à largura da viewport):

- **Desktop** (`>= 1100px` de container): 4 colunas lado a lado, drag-and-drop ativo (ver §27).
- **Tablet** (`>= 640px` e `< 1100px`): grid de 2 colunas por linha, todas as 4 colunas visíveis, cards com largura confortável.
- **Mobile** (`< 640px`): uma única coluna por vez, ocupando praticamente 100% da largura — nunca 4 colunas espremidas nem dependência de scroll horizontal do board inteiro. Acima da coluna, um seletor de status (pills/tabs compactas) com label + contagem por status permite trocar qual coluna é exibida; a seleção é evidente por múltiplos sinais visuais (fundo preenchido, peso da fonte), não só cor. A troca de status nesse tamanho de tela é sempre feita pelo menu "..." (sem drag).

Quando o filtro global de Status (§19) está ativo, a aba mobile se alinha automaticamente ao status filtrado assim que ele muda — mas só nesse momento: o usuário continua livre para tocar em qualquer outra aba depois, e essa escolha manual não é revertida sozinha enquanto o filtro global não mudar de novo. Limpar o filtro de Status não move a aba para lugar nenhum.

---

## 29. List View

A lista deve ser produtiva e relativamente compacta.

Informações úteis:

- título
- status
- tags
- prazo
- indicação de atraso

A tarefa deve ser facilmente identificável e editável.

Desktop pode utilizar uma organização semelhante a tabela/lista estruturada.

Mobile deve adaptar o layout sem exigir scroll horizontal desnecessário.

---

## 30. Tags

Tags utilizam:

- cores controladas — uma das 6 cores auxiliares da paleta compartilhada com Project, nunca uma cor livre;
- labels compactas (chip com dot de cor + nome);
- bom contraste.

Cards com diversas tags não quebram excessivamente a estrutura visual.

Limite funcional:

```text
máximo 5 tags por tarefa
```

A seleção de Tags acontece dentro do Task Modal (`TagPicker.vue`), com um formulário inline de criação de Tag nova (nome + cor) — ao criar, a Tag entra na lista disponível e é automaticamente selecionada para a tarefa em edição, sem exigir um segundo clique.

---

## 31. Due Date

Prazo deve ser visível nos cards quando informado.

Tarefas atrasadas devem receber indicação visual clara, mas não agressiva.

Exemplos possíveis:

- ícone
- texto
- badge
- cor de atenção

Não depender somente da cor para comunicar atraso.

---

## 32. Attachments

No Task Modal, a seção de anexos (`TaskAttachments.vue`) só aparece em modo edição — uma tarefa ainda não persistida (criação) mostra uma mensagem discreta ("Crie a tarefa para adicionar anexos.") em vez da seção funcional, já que o upload depende de a tarefa já ter um id.

A seção permite:

- visualizar nome (truncado com `title` quando muito longo), ícone por categoria (imagem/documento/planilha) e tamanho legível (KB/MB);
- adicionar arquivos (`multiple`, sem drag-and-drop de arquivos — apenas um botão "Adicionar arquivos");
- baixar (link autenticado por sessão, armazenamento privado — sem URL pública nem path de storage exposto);
- remover, com confirmação (`ConfirmDialog`).

Limites (validados no cliente para feedback imediato, e no backend como fonte final):

```text
até 5 arquivos por envio
até 10 anexos por tarefa
até 5 MB por arquivo
```

Preview de imagem não é um requisito desta fase (fica como possível melhoria futura). Erros de upload apresentam mensagens claras, nunca `alert()`.

---

## 33. Formulários

Formulários devem possuir:

- labels claras
- estados de foco
- indicação de erro
- mensagens de validação próximas ao campo
- estados disabled
- loading quando aplicável

Não depender somente de Toast para erros de validação de campos.

---

## 34. Loading States

Evitar páginas completamente vazias enquanto dados carregam.

Utilizar conforme contexto:

- Skeleton
- Spinner
- loading em botão

Não bloquear toda a interface quando apenas uma operação pequena estiver acontecendo.

---

## 35. Empty States

Criar Empty States úteis.

Exemplos:

### Sem projetos

Explicar brevemente e oferecer:

```text
Criar primeiro projeto
```

### Projeto sem tarefas

Oferecer:

```text
Criar primeira tarefa
```

### Busca sem resultado

Mostrar mensagem clara e permitir limpar filtros.

Empty States devem orientar uma próxima ação.

**Estado implementado:** dois estados distintos, nunca confundidos. Project realmente vazio (nenhuma Task cadastrada) mostra só "Este projeto ainda não possui tarefas." — sem Métricas/Filtros visíveis, já que não há nada para filtrar. Filtro sem resultado (há Tasks no Project, mas nenhuma atende aos filtros ativos) mostra "Nenhuma tarefa encontrada com estes filtros." + botão "Limpar filtros" — Métricas e a barra de filtros continuam visíveis acima, para o usuário entender e ajustar o que filtrou; List/Kanban não são renderizados nesse estado.

---

## 36. Success Feedback

Operações importantes devem apresentar feedback.

Exemplos:

- projeto criado
- tarefa salva
- tarefa removida
- anexo enviado

Preferir Toast discreto para confirmações rápidas.

Evitar excesso de notificações.

O Toast de sucesso combina um ícone de confirmação, um título curto e uma descrição contextual (por exemplo, citando o nome do recurso afetado), sobre fundo neutro (nunca uma cor sólida de destaque). Ele nunca é usado para erros de validação de campo (ver §33).

---

## 37. Error Feedback

Erros devem ser escritos para pessoas.

Evitar mostrar diretamente:

- stack trace
- exception class
- mensagens internas do servidor

A aplicação deve distinguir adequadamente:

- validação
- autenticação
- autorização
- recurso inexistente
- erro inesperado

---

## 38. Operações destrutivas

Exclusões devem solicitar confirmação.

Exemplos:

- excluir projeto
- excluir tarefa
- remover attachment quando o contexto justificar

O texto deve deixar claro o impacto da ação, citando o nome do recurso afetado (ex.: `Excluir "Marketing"?`).

A confirmação é feita por um diálogo dedicado (ConfirmDialog, construído sobre o mesmo Dialog nativo de §10), nunca `window.confirm()`. A ação destrutiva usa uma variante de botão própria, visualmente distinta das ações primária/secundária, e o diálogo permanece aberto com uma mensagem de erro caso a exclusão falhe.

---

## 39. Microinterações

Animações devem ser rápidas e discretas.

Duração conceitual aproximada:

```text
150ms a 220ms
```

Aplicações possíveis:

- hover
- abertura de drawer
- dropdown
- mudança de estado
- drag and drop
- toast

Evitar animações decorativas que prejudiquem produtividade.

---

## 40. Responsividade

Breakpoint definido entre Sidebar fixa e Drawer de navegação:

```text
Sidebar fixa (desktop): >= 1024px (breakpoint `lg`)
Drawer de navegação:    <  1024px
```

Abaixo de 1024px, tablet e mobile compartilham o mesmo padrão de navegação (Drawer); grids de conteúdo (ex.: Projects, ver §14 e a listagem de projetos) se adaptam de forma independente (1/2/3 colunas) conforme a largura disponível, sem um breakpoint dedicado próprio.

Não construir o frontend apenas para uma resolução específica.

---

## 41. Desktop

Desktop deve ser a experiência mais completa.

Priorizar:

- Sidebar fixa
- boa área de Kanban
- Task Modal mantendo contexto (backdrop translúcido preservando o restante da tela)
- Toolbar completa
- boa densidade de informação

---

## 42. Tablet

Tablet deve adaptar:

- Sidebar
- Task Modal
- Toolbar
- métricas
- Kanban (grid de 2 colunas nesta faixa — ver §28)

Evitar elementos apertados.

---

## 43. Mobile

Mobile deve ser tratado como experiência real.

Garantir:

- navegação funcional
- formulários confortáveis
- botões acessíveis
- Kanban utilizável (uma coluna por vez + seletor de status — ver §28)
- List View legível
- Task Modal adequado
- filtros acessíveis
- anexos utilizáveis

Não esconder funcionalidades obrigatórias simplesmente por falta de espaço.

---

## 44. Acessibilidade

### SHOULD

Considerar:

- contraste
- navegação por teclado
- foco visível
- labels corretas
- aria-label quando necessário
- tamanho adequado de áreas clicáveis
- mensagens de erro compreensíveis

Não utilizar apenas cores para representar estados importantes.

---

## 45. Componentização

Evitar componentes gigantes.

Exemplo conceitual:

```text
ProjectPage
├── ProjectHeader
├── ProjectMetrics
├── TaskToolbar
├── ViewSwitcher
├── TaskList
└── TaskKanban
    ├── KanbanColumn
    └── TaskCard
```

O Task Modal já é dividido em componentes menores: `TaskModal` (container), `TaskForm` (campos + Tags), `TaskAttachments` (anexos, com estado próprio).

Não fragmentar componentes sem benefício real.

---

## 46. Estado global

Pinia deve ser utilizado apenas quando o estado precisar ser compartilhado de maneira significativa.

Possíveis casos:

- usuário autenticado
- sessão
- dados globais realmente compartilhados (ex.: a lista de Projects, compartilhada entre Sidebar, listagem e detalhe)

Não colocar automaticamente cada recurso em uma Store. Estado de UI puramente efêmero (ex.: Toast, modal aberto/fechado) não precisa de Pinia — um composable simples é suficiente quando o estado não é dado de domínio.

---

## 47. Performance percebida

A interface deve parecer rápida.

Priorizar:

- feedback imediato
- skeletons adequados
- evitar reload completo de página
- evitar chamadas duplicadas
- preservar estado útil quando apropriado

Otimizações complexas só devem ser implementadas quando justificadas.

---

## 48. Conteúdo de demonstração

Seeds de demonstração devem utilizar conteúdo realista.

Evitar:

```text
Task 1
Task 2
Project Test
Lorem ipsum
```

Preferir exemplos como:

```text
Planejamento do lançamento
Revisar documentação da API
Validar fluxo de cadastro
Preparar materiais de apresentação
Corrigir feedback da homologação
```

Isso ajuda a aplicação a parecer um produto real durante avaliação e vídeo.

---

## 49. Login

A página de login deve ser:

- limpa
- moderna
- coerente com o branding Taskly
- responsiva

Não precisa utilizar a estrutura de Sidebar da aplicação autenticada.

Deve conter somente elementos necessários.

Login e Registro reaproveitam a marca (`Brand`, §51) e os mesmos tokens visuais (superfície, borda, texto) da aplicação autenticada — sem duplicar uma implementação própria da marca ou uma paleta paralela.

Usa a linguagem de aurora descrita em §4 com a intensidade decorativa mais alta do produto, reforçando a chegada do usuário sem comprometer a legibilidade do formulário.

Em desktop, a composição se divide em duas áreas: um lado institucional (marca, uma badge curta, um headline, um texto de apoio e alguns highlights curtos do produto) e o formulário num card de superfície (`surface`) — o card permanece sempre a área de maior contraste e foco visual da tela.

Em mobile, a composição simplifica para um único card centralizado (o lado institucional não aparece) — a marca acompanha o formulário, que continua a única prioridade em telas pequenas.

---

## 50. Registro

O cadastro deve manter a mesma identidade do login.

Compartilha a mesma composição de duas áreas do Login (§49) — incluindo o lado institucional em desktop e a simplificação para um único card em mobile.

Deve apresentar claramente:

- nome
- e-mail
- senha
- confirmação de senha
- ação de cadastro
- acesso ao login existente

---

## 51. Identidade Taskly

O nome Taskly é apresentado de maneira consistente através de um único componente de marca reutilizável (`Brand`), usado na Sidebar, na barra mobile e nas páginas de Login/Registro.

A marca combina um selo quadrado-arredondado na cor Primary com um ícone de check (Lucide) em branco, seguido do nome "Taskly" em peso semibold — inteiramente tipográfico/iconográfico, sem logotipo externo ou assets de imagem.

No lado institucional do Auth (§49), a marca ganha mais presença: aparece maior, acompanhada de uma badge curta, um headline e alguns highlights do produto — reforçando a identidade sem introduzir uma segunda marca ou paleta.

A identidade deve priorizar produto e funcionalidade.

---

## 52. Antes de implementar a UI principal

Antes de desenvolver a interface principal, o agente deve apresentar uma proposta de UI contendo pelo menos:

- estrutura geral do App Shell
- Sidebar
- Project Page
- List View
- Kanban
- Task Modal
- Login
- Register
- comportamento mobile
- principais componentes
- novas dependências necessárias, se houver

Essa proposta deve ser revisada antes de uma implementação visual substancial.

---

## 53. Uso do ClickUp pelo agente

Quando este documento mencionar ClickUp, interpretar como:

```text
referência de princípios de UX e organização
```

e nunca como:

```text
instrução para copiar a interface
```

O agente deve construir uma identidade própria para Taskly.

---

## 54. Critérios de aceite visual

Antes de considerar a interface principal pronta, validar:

### Desktop

- Sidebar funcional
- hierarquia clara
- List View funcional
- Kanban funcional
- Task Modal funcional
- filtros utilizáveis
- formulários bem apresentados

### Mobile

- navegação funcional
- projeto acessível
- lista legível
- Kanban utilizável
- Task Modal utilizável
- formulário utilizável
- ações principais acessíveis

### Feedback

- loading
- empty
- error
- validation
- success
- confirmação destrutiva

---

## 55. Definition of Done UI/UX

A interface só deve ser considerada pronta quando:

- possui identidade própria
- está coerente entre páginas
- funciona em desktop
- funciona em mobile
- possui estados de loading
- possui Empty States
- apresenta erros adequadamente
- apresenta validações adequadamente
- possui feedback de ações
- mantém boa legibilidade
- não apresenta erros relevantes no console
- não parece um template genérico
- não parece uma cópia do ClickUp
- está integrada à API real

---

## 56. Princípio final

O Taskly deve parecer:

```text
um produto de produtividade moderno,
organizado,
colorido na medida certa,
rápido de utilizar
e construído com atenção aos detalhes.
```

Qualidade visual não deve comprometer:

- segurança
- funcionalidade
- clareza
- performance
- manutenibilidade
