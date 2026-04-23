# Manutenção dos Indicadores Financeiros

## Remoção do indicador de despesas
- O item `Despesas` foi removido dos indicadores financeiros ativos do sistema.
- Motivo: não há lançamentos de despesas utilizados nas visões operacionais atuais.

## Impacto aplicado
- Dashboard financeiro:
  - removido o bloco visual `Despesas no ano`
  - removidas chaves e cálculos anuais de despesas
- Resumo do módulo financeiro:
  - removida a dependência de despesas no cálculo do saldo exibido

## Regra atual
- `Saldo` considera apenas a receita prevista/recebida exibida no contexto da tela.
- Nenhuma outra métrica de parcelas, pagamentos, projetos ou tarefas foi alterada.

## Observação futura
- Se o módulo passar a trabalhar com despesas reais em indicadores, o item deve ser reintroduzido com origem de dados validada e cobertura de testes.
