# Módulo de Orçamentos

## Objetivo
- Permitir criar propostas comerciais independentes do projeto.
- Aprovar orçamento para gerar projeto automaticamente em fluxo transacional.
- Manter o fluxo atual de criação de projetos sem alterações de comportamento.

## Rotas
- `GET /orcamentos`
- `POST /orcamentos`
- `POST /orcamentos/{id}/atualizar`
- `POST /orcamentos/{id}/status`
- `GET /orcamentos/{id}/proposta`
- `GET /orcamentos/{id}/proposta/txt`
- `GET /orcamentos/{id}/proposta/pdf`

## Status
- `rascunho`: edição livre
- `enviado`: bloqueia edição de campos críticos
- `aprovado`: cria projeto automático e vincula `projeto_id`
- `recusado`: não gera projeto

## Reuso de lógica financeira
- `FinanceInstallmentCalculator` para validar/normalizar total, entrada e parcelas
- `InstallmentPlanner` para sugerir parcelas por data inicial
- `ProjectService::create()` para geração completa do projeto + financeiro na aprovação

## Integridade de dados
- Tabela `orcamentos` independente de `projects` até aprovação.
- Aprovação é transacional e impede duplicação de projeto para o mesmo orçamento.

## Testes
- Unitário:
  - `tests/Unit/BudgetServiceTest.php`
  - `tests/Unit/BudgetProposalServiceTest.php`
- Integração:
  - `tests/Integration/BudgetApprovalIntegrationTest.php`
