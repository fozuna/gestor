# Design de Páginas (desktop-first) — CRM SaaS multi-tenant

## Padrões globais
- Layout: Grid 12 colunas (container central), sidebar fixa (desktop) + header superior; conteúdo em cards.
- Breakpoints: Desktop ≥1024 (sidebar expandida), Tablet 768–1023 (sidebar colapsável), Mobile ≤767 (drawer).
- Tokens:
  - Background: #0B1220 (app) e #0F172A (cards)
  - Texto: #E5E7EB (primário), #94A3B8 (secundário)
  - Primária: #3B82F6; Sucesso: #22C55E; Alerta: #F59E0B; Erro: #EF4444
  - Tipografia: base 14–16px; títulos 20/24/32px; números KPI 28–36px
  - Botões: primário preenchido; secundário outline; hover +8% brilho; foco com ring 2px
- Componentes compartilhados:
  - AppShell: Sidebar + Topbar + Content
  - WorkspaceSwitcher (Topbar)
  - UserMenu: papel atual, “Sair”
  - DataTable (busca, paginação simples), Modal (confirmação), Toast (feedback)

---

## 1) Página: Login
### Meta Information
- Title: “Entrar | CRM”
- Description: “Acesse seu workspace com segurança.”
- OG: title/description + type=website

### Page Structure
- Layout: coluna central (max-width 420px), fundo escuro com ilustração sutil.

### Sections & Components
1. Header leve: logo + nome do produto.
2. Card de autenticação:
   - Campos: e-mail, senha (toggle mostrar)
   - CTA primário: “Entrar”
   - Alternativa: “Enviar link mágico” (se habilitado)
   - Links: “Esqueci minha senha”
3. Card/step pós-login (se múltiplos workspaces):
   - Lista de workspaces (nome + última atividade)
   - CTA: “Entrar no workspace”
   - CTA secundário: “Criar novo workspace” (nome do workspace)
4. Estados:
   - Loading no botão, mensagens de erro genéricas, lockout/limite de tentativas.

---

## 2) Página: Dashboard (/app/dashboard)
### Meta Information
- Title: “Dashboard | CRM”
- Description: “Visão geral do seu workspace.”

### Page Structure
- Layout: AppShell; conteúdo em 2 colunas (principal + lateral) no desktop.

### Sections & Components
1. Topbar:
   - Breadcrumb “Dashboard”
   - WorkspaceSwitcher
   - UserMenu (papel, sair)
2. KPIs (grid 2x2 cards):
   - “Tarefas atrasadas”, “Projetos ativos”, “Receitas (mês)”, “Despesas (mês)”
3. Próximas ações:
   - Lista de tarefas vencendo (card com status + due date)
   - CTA: “Ver Projetos & Tarefas”
4. Atalhos rápidos (cards menores):
   - “Novo cliente”, “Novo projeto”, “Novo lançamento”

---

## 3) Página: Clientes (/app/clientes)
### Meta Information
- Title: “Clientes | CRM”
- Description: “Cadastre e organize seus clientes.”

### Page Structure
- Layout: AppShell; tabela principal + painel lateral (detalhes) ou página de detalhes.

### Sections & Components
1. Header da página:
   - Título “Clientes” + CTA primário “Novo cliente”
   - Busca por nome + filtro “Ativos/Arquivados”
2. Lista (DataTable):
   - Colunas: Nome, Status, Criado em, Ações
3. Modal “Novo/Editar cliente”:
   - Campos: nome, status
   - Validação inline
4. Detalhe rápido:
   - Bloco “Projetos deste cliente” (linka para Projetos & Tarefas já filtrado)

---

## 4) Página: Projetos & Tarefas (/app/projetos)
### Meta Information
- Title: “Projetos & Tarefas | CRM”
- Description: “Acompanhe entregas e execução.”

### Page Structure
- Layout: AppShell; split view (lista de projetos à esquerda, tarefas à direita) no desktop.

### Sections & Components
1. Header:
   - Filtros: Cliente, Status do projeto, Responsável (tarefas)
   - CTAs: “Novo projeto” e “Nova tarefa”
2. Lista de projetos (coluna esquerda):
   - Cards com nome, cliente, progresso, due date
3. Tarefas (coluna direita):
   - Tabs/colunas de status: “A fazer”, “Em andamento”, “Concluída”
   - Cada item: título, responsável, prazo, quick actions (mudar status)
4. Drawer/Modal de edição:
   - Projeto: nome, cliente, status, prazo
   - Tarefa: título, status, responsável, prazo, comentário

---

## 5) Página: Financeiro (/app/financeiro)
### Meta Information
- Title: “Financeiro | CRM”
- Description: “Receitas, despesas e saldo do mês.”

### Page Structure
- Layout: AppShell; cards de resumo no topo + tabela de lançamentos.

### Sections & Components
1. Header:
   - Seletor de período (mês/ano)
   - CTA primário “Novo lançamento”
2. Resumo mensal (3 cards):
   - Receitas, Despesas, Saldo
3. Filtros:
   - Tipo (receita/despesa), categoria, cliente/projeto (opcional)
4. Lançamentos (DataTable):
   - Colunas: Data, Tipo, Categoria, Valor, Cliente/Projeto, Nota, Ações
5. Modal “Novo/Editar lançamento”:
   - Campos: tipo, valor, data, categoria, nota, cliente/projeto (opcional)
   - Confirmação para estorno/exclusão
