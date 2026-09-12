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

Quando implementadas, as métricas poderão mostrar:

- Total
- Em andamento
- Concluídas
- Atrasadas

Devem funcionar como informação rápida.

Evitar cards gigantes de dashboard.

O objetivo é produtividade, não construir uma tela de BI.

---

## 19. Toolbar

A Toolbar deverá acomodar conforme implementação:

- busca
- filtro por status
- filtro por tags
- filtro por prazo
- seletor List / Kanban
- botão New Task

O botão de criação de tarefa deve possuir bom destaque.

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

## 21. Task Drawer

Criação e edição de tarefas utilizarão um Drawer lateral à direita.

### Desktop

Largura aproximada:

```text
520px a 620px
```

Limite aproximado:

```text
45% a 50% da viewport
```

O restante da tela deve continuar oferecendo contexto do projeto.

### Mobile

O Drawer poderá ocupar:

```text
quase toda ou toda a largura
```

para preservar a usabilidade do formulário.

---

## 22. Conteúdo do Task Drawer

O Drawer deverá suportar:

- título
- descrição curta
- descrição completa
- status
- prazo
- tags
- anexos
- ações de salvar
- exclusão quando aplicável

A organização deve favorecer preenchimento rápido.

Campos menos importantes poderão ocupar posições secundárias.

---

## 23. Criação e edição

O padrão visual de criação e edição deve ser consistente.

Idealmente, utilizar o mesmo componente ou estrutura de formulário quando isso não gerar complexidade desnecessária.

O usuário deve entender claramente se está:

- criando
- editando

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

Os cards do Kanban devem ser relativamente ricos.

Devem apresentar, quando disponíveis:

- título
- descrição curta
- tags
- prazo
- indicador de atraso
- quantidade ou indicador de anexos

Os cards devem permitir leitura rápida sem abrir a tarefa.

---

## 27. Drag and Drop

Durante movimentação:

- card deve apresentar feedback visual
- destino deve ficar identificável
- interação deve parecer fluida
- cursor apropriado deve ser utilizado em desktop

Animações devem ser discretas.

Em caso de erro de persistência:

- informar usuário
- restaurar estado quando necessário

---

## 28. Kanban no mobile

Kanban mobile poderá utilizar:

```text
scroll horizontal
```

entre colunas.

Cada coluna deve possuir largura confortável para leitura dos cards.

Poderá ser avaliado:

```text
scroll snap
```

se melhorar a experiência.

Não comprimir quatro colunas simultaneamente na largura do celular.

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

Tags devem utilizar:

- cores controladas
- labels compactas
- bom contraste

Não utilizar cores aleatórias ilimitadas.

Cards com diversas tags não devem quebrar excessivamente a estrutura visual.

Limite funcional:

```text
máximo 5 tags por tarefa
```

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

No Task Drawer, anexos devem permitir:

- visualizar nome
- identificar tipo
- visualizar tamanho quando útil
- remover
- adicionar

Imagens poderão utilizar preview quando apropriado.

Erros de upload devem apresentar mensagens claras.

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
- Task Drawer mantendo contexto
- Toolbar completa
- boa densidade de informação

---

## 42. Tablet

Tablet deve adaptar:

- Sidebar
- largura do Drawer
- Toolbar
- métricas
- Kanban

Evitar elementos apertados.

---

## 43. Mobile

Mobile deve ser tratado como experiência real.

Garantir:

- navegação funcional
- formulários confortáveis
- botões acessíveis
- Kanban utilizável
- List View legível
- Task Drawer adequado
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

Task Drawer poderá ser dividido em componentes menores conforme necessidade.

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
- Task Drawer
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
- Task Drawer funcional
- filtros utilizáveis
- formulários bem apresentados

### Mobile

- navegação funcional
- projeto acessível
- lista legível
- Kanban utilizável
- Drawer utilizável
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
