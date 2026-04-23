## Revisão de internacionalização (pt-BR)

### Objetivo
Remover termos em inglês da interface (textos, labels, badges, cards, tooltips e mensagens) e padronizar a exibição em português brasileiro, mantendo os valores internos/armazenados (códigos) inalterados.

### Estratégia aplicada
- Manter códigos internos (ex.: `todo`, `doing`, `paid`, `pending`, `high`, `active`) como valores de formulário e valores persistidos no banco.
- Traduzir apenas a camada de apresentação (views) para exibição consistente em pt-BR.
- Padronizar termos globais de navegação para evitar mistura de idioma.

### Mapeamentos aplicados (exibição)
- Status de tarefa:
  - `todo` → `A fazer`
  - `doing` → `Em progresso`
  - `done` → `Concluído`
- Prioridade:
  - `high` → `Alta`
  - `medium` → `Média`
  - `low` → `Baixa`
- Status de projeto:
  - `active` → `Em andamento`
  - `paused` → `Pausado`
  - `done` → `Concluído`
  - `cancelled` → `Cancelado`
  - `inactive` → `Inativo`
- Status de parcela:
  - `paid` → `Paga`
  - `pending` → `Pendente`
  - `overdue` → `Vencida`
  - `cancelled` → `Cancelada`
- Tipos de tarefa (detalhe):
  - `billable` → `Cobrável`
  - `informative` → `Informativa`
  - `in_scope` → `No escopo`
  - `out_of_scope` → `Fora de escopo`
  - `one_off` → `Avulsa`

### Termos globais ajustados
- `Dashboard` → `Painel`
- `Workspace` → `Ambiente`
- Papel do usuário:
  - `admin` → `Administrador`
  - `gestor` → `Gestor`
  - `user` → `Usuário`

### Arquivos revisados/ajustados (UI)
- Navegação e layout:
  - `resources/views/partials/app-shell.php`
  - `resources/views/dashboard/index.php`
- Projetos:
  - `resources/views/projects/show.php`
  - `resources/views/projects/module.php`
  - `resources/views/projects/index.php` (tela legada)
- Tarefas:
  - `resources/views/tasks/index.php`
  - `resources/views/tasks/show.php`
- Clientes:
  - `resources/views/clients/profile.php`
- Instalação:
  - `resources/views/install/index.php`
  
### Ajuste de datas (inputs)
- Alguns navegadores exibem `mm/dd/yyyy` em inputs `type="date"` quando o sistema operacional está em inglês.
- Para evitar exibição em inglês nas telas de tarefas/projetos, os campos de prazo foram ajustados para input textual com placeholder `aaaa-mm-dd` e validação por `pattern`, mantendo normalização no backend.

### Validação
- Executar `composer test` e `npm run build`.
- Smoke manual nas telas:
  - `/projetos/{id}` (badges de parcelas e status do projeto)
  - `/tarefas` (status/prioridade na listagem e no Kanban)
  - `/clients/profile?id=...` (status de projetos e tarefas)
  - `/finance` (status de parcelas)
