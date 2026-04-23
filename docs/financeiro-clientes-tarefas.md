# TRAXTER CRM — Financeiro, Clientes e Tarefas

## Interfaces HTTP

### Módulos independentes
- `GET /projetos`
  - Lista projetos com busca por nome, filtro por status, paginação e formulário de criação/edição
- `POST /projetos`
  - Cria projeto básico do módulo independente
- `GET /projetos/{id}`
  - Abre o dashboard do projeto com contexto selecionado, resumo financeiro e CRUD de tarefas do próprio projeto
- `POST /projetos/{id}/atualizar`
  - Atualiza projeto básico com validação de nome, status e cliente
- `POST /projetos/{id}/excluir`
  - Exclui projeto e registra auditoria
- `GET /tarefas`
  - Lista tarefas com filtros por status, responsável e prioridade, ordenação, paginação, CRUD e Kanban
- `POST /tarefas`
  - Cria tarefa e normaliza `due_date` para `YYYY-MM-DD`
- `GET /tarefas/{id}`
  - Exibe página de detalhe da tarefa
- `POST /tarefas/{id}/atualizar`
  - Atualiza tarefa com formulário pré-preenchido
- `POST /tarefas/{id}/excluir`
  - Exclui tarefa com confirmação obrigatória
- `POST /tarefas/{id}/mover`
  - Endpoint JSON do Kanban:
    - sucesso: `{ "success": true, "message": "..." }`
    - erro: `{ "success": false, "message": "..." }`

### Projetos
- `GET /projects`
  - Exibe cadastro de projeto, plano financeiro e kanban
- `GET /projects/show?id={projectId}`
  - Exibe detalhes do projeto com resumo financeiro, parcelas e tarefas vinculadas
- `POST /projects`
  - Campos principais:
    - `name`
    - `client_id`
    - `status`
    - `start_date`
    - `due_date`
    - `contract_value`
    - `payment_terms`
    - `entry_amount`
    - `first_installment_date`
    - `installments[n][due_date]`
    - `installments[n][amount]`

### Tarefas
- `POST /tasks`
  - Campos:
    - `title`
    - `project_id`
    - `status`
    - `priority`
    - `due_date`
    - `task_kind`
    - `billable_amount`
    - `description`
  - `due_date` aceita `DD/MM/AAAA` e `YYYY-MM-DD`, sempre persistindo em `YYYY-MM-DD`

### Financeiro
- `GET /finance?month=MM&year=YYYY`
  - Exibe KPIs, alertas de vencimento e parcelas do período
- `POST /finance/installments/pay`
  - Campos:
    - `invoice_id`
    - `amount_paid`
    - `payment_date`
    - `payment_method`
    - `note`
    - `redirect_month`
    - `redirect_year`

### Perfil do cliente
- `GET /clients/profile?id={clientId}&start_date=YYYY-MM-DD&end_date=YYYY-MM-DD`
  - Exibe resumo financeiro, histórico completo de parcelas do período, projetos ativos e tarefas
- `GET /clients/profile/tasks-pdf?id={clientId}&start_date=YYYY-MM-DD&end_date=YYYY-MM-DD`
  - Exporta PDF corporativo das tarefas do cliente com status, progresso, responsável e métricas consolidadas

## Regras de negócio

### Parcelas
- A soma da entrada e das parcelas deve ser igual ao valor total do projeto
- Datas são aceitas somente quando válidas no calendário
- Parcelas com vencimento anterior à data atual são tratadas como vencidas quando ainda não estão pagas
- Um novo pagamento só é aceito enquanto existir saldo restante na parcela
- Não é permitido valor pago maior que o saldo pendente
- Reenvio do mesmo pagamento com mesma data e mesmo valor é bloqueado como duplicidade
- Métodos de pagamento aceitos:
  - `pix`
  - `boleto`
  - `transferencia`
  - `cartao`
  - `dinheiro`
  - `outro`

### Perfil do cliente
- O dashboard destaca parcelas pendentes, vencidas e histórico completo das parcelas no intervalo selecionado
- O card financeiro usa apenas filtro por intervalo de datas com `datepicker` nativo
- O histórico exibe parcelas pagas e pendentes com valor, vencimento e data de pagamento
- As listagens de projetos exibem ação de detalhe com botão de ícone, redirecionando para a página dedicada do projeto
- O botão `Exportar PDF` no perfil do cliente gera relatório com:
  - cabeçalho corporativo (logo, cliente, data/hora, período)
  - tabela de tarefas com status e progresso
  - destaque condicional (atrasada vermelho, em andamento amarelo, concluída verde)
  - métricas agregadas (total, concluídas, atrasadas, taxa de conclusão)
  - rodapé com paginação
  - lista vazia com mensagem informativa

### Tarefas
- `in_scope` gera tarefa informativa sem valor
- `out_of_scope` e `one_off` geram tarefa cobrável com valor obrigatório
- O perfil do cliente separa visualmente tarefas cobráveis e informativas e soma os valores cobráveis
- Datas de tarefa são validadas no backend antes do insert; formatos inválidos retornam erro claro e não persistem no banco
- O módulo independente valida se o `project_id` pertence ao tenant antes de criar ou editar a tarefa
- O dashboard de projeto cria tarefas já vinculadas ao `project_id` do contexto, sem permitir vínculo vazio
- O Kanban persiste a mudança imediatamente no backend, registra auditoria e faz rollback visual quando a API retorna erro

### Totais financeiros de projeto
- Os cards de `Valor pendente` e `Valor recebido` são agregados por projeto sem multiplicação por join de tarefas
- Os valores de `paid_amount` e `pending_amount` são protegidos por guarda de domínio para não ultrapassar `contract_value`
- Em cenário anômalo de dados (`amount_paid` maior que `amount_total` ou totais inconsistentes), o sistema aplica clamp e registra auditoria em `action_logs` com ação `project.financial_totals.clamped`

## Auditoria
- Criação de plano financeiro do projeto gera evento em `action_logs`
- Cada baixa de parcela gera evento em `action_logs` com valor lançado, método, total acumulado e saldo remanescente

## Backup
- Toda criação de plano financeiro e baixa de parcela gera snapshot JSON em `storage/backups`
- O comando `php bin/backup.php {tenantId}` exporta um snapshot manual das principais tabelas do tenant

## Testes unitários
- `tests/Unit/InstallmentPlannerTest.php`
- `tests/Unit/PaymentValidationServiceTest.php`
- `tests/Unit/SecurityTest.php`
- `tests/Unit/TaskClassificationServiceTest.php`
- `tests/Unit/TaskServiceDateValidationTest.php`
- `tests/Unit/ClientTasksPdfReportServiceTest.php`

## Testes de integração
- `tests/Integration/FinanceRepositoryIntegrationTest.php`
- `tests/Integration/ClientProfileRepositoryIntegrationTest.php`
- `tests/Integration/ProjectDetailIntegrationTest.php`
- `tests/Integration/ProjectServiceCrudIntegrationTest.php`
- `tests/Integration/TaskServiceIntegrationTest.php`

## Correção HY093
- Arquivo corrigido: `app/Repositories/FinanceRepository.php`
- Método afetado: `listByTenant()`
- Causa raiz:
  - a query reutilizava o placeholder nomeado `:tenant_id` em dois pontos do mesmo statement preparado, um na subquery de `payments` e outro na query principal de `invoices`
  - com `PDO::ATTR_EMULATE_PREPARES = false`, o driver MySQL pode falhar com `SQLSTATE[HY093]: Invalid parameter number`
- Discrepância encontrada:
  - antes: `WHERE tenant_id = :tenant_id` dentro da subquery e `WHERE i.tenant_id = :tenant_id` na query externa
  - depois: `:payments_tenant_id` na subquery e `:tenant_id` na query externa, ambos com `bindValue()` explícito
- Validação aplicada:
  - teste de integração adicionado em `tests/Integration/FinanceRepositoryIntegrationTest.php`
  - cobertura com e sem filtro de período

## Correção HY093
- Arquivo corrigido: `app/Repositories/FinanceRepository.php`
- Método afetado: `listByTenant()`
- Problema encontrado:
  - a subquery de `payments` e a query principal reutilizavam o placeholder nomeado `:tenant_id` no mesmo statement preparado
  - com PDO MySQL e `ATTR_EMULATE_PREPARES = false`, esse reuso pode disparar `SQLSTATE[HY093]: Invalid parameter number`
- Discrepância corrigida:
  - antes: dois placeholders `:tenant_id` para apenas um nome de parâmetro reutilizado
  - depois: placeholders distintos `:payments_tenant_id` e `:tenant_id`, ambos vinculados explicitamente
- Validação aplicada:
  - testes unitários existentes preservados
  - teste de integração adicionado em `tests/Integration/FinanceRepositoryIntegrationTest.php` cobrindo carga do financeiro com e sem filtro de período

## Correção de cálculo dos cards de projeto
- Arquivos corrigidos:
  - `app/Repositories/ProjectRepository.php`
  - `app/Services/ProjectService.php`
  - `app/Services/ProjectFinancialGuard.php`
- Causa raiz:
  - consultas de projeto juntavam `tasks` e `invoices` no mesmo `SELECT` agregado
  - quando o projeto possuía múltiplas tarefas e múltiplas parcelas, ocorria multiplicação cartesiana entre linhas e os `SUM()` eram inflados
- Correção aplicada:
  - agregações movidas para subconsultas independentes por projeto (`tasks` e `invoices`)
  - cálculo financeiro normalizado com guarda de domínio (`ProjectFinancialGuard`) para garantir:
    - `paid_amount <= contract_value`
    - `pending_amount <= (contract_value - paid_amount)`
  - auditoria de anomalia registrada em `action_logs` quando o guard precisa ajustar valores (`project.financial_totals.clamped`)
- Cobertura adicionada:
  - unitário: `tests/Unit/ProjectFinancialGuardTest.php`
  - integração: `tests/Integration/ProjectDetailIntegrationTest.php` (cenários de não-duplicação e clamp)

## Monitoramento contínuo
- Sinal de regressão:
  - qualquer registro em `action_logs` com ação `project.financial_totals.clamped`
- Consulta de monitoramento:
  - `SELECT created_at, tenant_id, entity_id, meta_json FROM action_logs WHERE action = 'project.financial_totals.clamped' ORDER BY created_at DESC;`
- Rotina recomendada:
  - validar essa consulta em checklist de homologação
  - manter `composer test` no pipeline para bloquear regressões nos cenários de cálculo de projeto

## Cadastro financeiro de projetos (módulo /projetos)
### Problemas identificados
- O fluxo do módulo independente `/projetos` salvava campos financeiros na tabela `projects`, mas não gerava as parcelas (invoices) nem os lançamentos de caixa (`financial_entries`), impedindo o controle financeiro automático vinculado ao projeto.
- O método `ProjectService::create()` assumia `contract_value`/`entry_amount` como números já normalizados; em cenários reais a UI envia valores mascarados em pt-BR (`8.000,00`), o que pode quebrar o cálculo caso não seja convertido via `Security::parseMoney()`.

### Correções aplicadas
- O módulo `/projetos` passou a criar projetos usando o fluxo transacional que gera:
  - parcelas (`invoices`)
  - lançamentos de caixa (`financial_entries`)
  - auditoria (`action_logs`)
  - backup (`storage/backups`)
- O serviço passou a aceitar valores monetários em formato pt-BR e normalizar no backend (`Security::parseMoney()`).
- Se o usuário informar `installments_count` + `first_installment_date` e não enviar parcelas manualmente, o sistema gera automaticamente o plano de parcelas com `InstallmentPlanner::suggestInstallments()`.
- Em atualização de projeto, quando ainda não existem parcelas geradas para o projeto, o sistema consegue gerar o plano financeiro via `ensureFinancialPlan()` sem duplicar parcelas.

### Cobertura
- Integração: `tests/Integration/ProjectFinancialFlowIntegrationTest.php`

## Correção crítica: resíduos financeiros após exclusão de projeto
### Causa raiz
- Ambientes legados com `FK fk_invoices_project` em `ON DELETE SET NULL` podiam manter parcelas financeiras com `project_id = NULL` após excluir projeto.
- Esses resíduos continuavam sendo somados no perfil financeiro do cliente (`pending_installments`/`pending_total`) mesmo sem projeto ativo.

### Correções implementadas
- Integridade referencial reforçada para cascata financeira de projeto em:
  - `invoices.project_id -> projects.id` (`ON DELETE CASCADE`)
  - `financial_entries.project_id -> projects.id` (`ON DELETE CASCADE`)
  - `financial_entries.invoice_id -> invoices.id` (`ON DELETE CASCADE`)
  - `installment_plans.project_id -> projects.id` (`ON DELETE CASCADE`)
- Triggers de proteção adicionadas para impedir inconsistência futura:
  - `trg_invoices_project_financial_guard_insert`
  - `trg_invoices_project_financial_guard_update`
  - `trg_financial_entries_project_guard_insert`
  - `trg_financial_entries_project_guard_update`
- Script de limpeza de órfãos ampliado para remover também parcelas legadas desanexadas de projeto:
  - `invoices_detached_project_financial`
- Consulta do perfil financeiro endurecida para não considerar resíduos legados no card/histórico:
  - exclusão lógica de invoices com `project_id IS NULL`, `installment_type in ('entry','monthly')` e código `PRJ-%`.

### Validação de homologação
- Executar migração:
  - `php bin/migrate.php`
- Executar limpeza com validação:
  - `php bin/cleanup-financial-orphans.php`
  - `php bin/cleanup-financial-orphans.php --execute`
- Executar testes:
  - `php vendor/bin/phpunit --configuration phpunit.xml tests/Integration/ProjectServiceCrudIntegrationTest.php`
  - `php vendor/bin/phpunit --configuration phpunit.xml tests/Integration/ClientProfileRepositoryIntegrationTest.php`

## Parcelamento no módulo Financeiro (/finance)
### Objetivo
Adicionar suporte a entrada (opcional) e parcelamento diretamente no financeiro existente, com validações no frontend e backend e persistência em banco.

### Regras
- Entrada (opcional):
  - `valor_entrada` pode ser vazio/nulo
  - se houver `valor_entrada`, `data_entrada` é obrigatória
- Saldo:
  - `saldo_restante = valor_total - valor_entrada` (quando houver entrada)
  - sem entrada: `saldo_restante = valor_total`
- Primeira parcela:
  - com entrada: `data_primeira_parcela > data_entrada`
  - sem entrada: qualquer data válida
- Parcelamento:
  - `valor_parcela = saldo_restante / quantidade_parcelas`
  - ajuste de arredondamento é aplicado na última parcela para fechar o total
- Validações:
  - impede `valor_entrada > valor_total`
  - impede gerar parcelas se o projeto já possuir parcelas geradas

### Persistência
- Tabela: `installment_plans` (um plano por projeto/tenant)
- Campos:
  - `valor_total`, `valor_entrada`, `data_entrada`
  - `saldo_restante`, `quantidade_parcelas`, `valor_parcela`, `data_primeira_parcela`
  - `formas_pagamento`
- Geração automática:
  - cria `invoices` (entrada + mensais)
  - cria `financial_entries` correspondentes
  - registra auditoria em `action_logs` com `finance.installment_plan.created`

### Testes
- Unitário: `tests/Unit/FinanceInstallmentCalculatorTest.php`
- Integração: `tests/Integration/FinanceInstallmentPlanIntegrationTest.php`

## Padronização de logos
- O gerenciamento centralizado de logos (web + relatórios) está documentado em:
  - `docs/logo-management.md`
