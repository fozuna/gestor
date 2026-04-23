# Guia de Estilo de Ícones de Ação

## Objetivo
Padronizar botões de ação com ícones intuitivos, consistentes e acessíveis no sistema.

## Classes padrão
- `.icon-btn`: base de botão de ação por ícone.
- `.icon-btn--sm`: 32x32 px.
- `.icon-btn--md`: 40x40 px.
- `.icon-btn--lg`: 48x48 px.

## Tamanho dos ícones internos
- Padrão: `16x16` (`.icon-btn svg`).
- Uso recomendado:
  - contexto denso/listagem: 16x16 (`--sm`)
  - toolbar/formulário: 16x16 ou 24x24 (`--md`)
  - ação destaque: 24x24 ou 32x32 (`--lg`)

## Variações de cor por estado
- `.icon-btn--primary`: ações principais (filtro, exportar, atualizar).
- `.icon-btn--success`: ações positivas (confirmar/registrar).
- `.icon-btn--danger`: ações destrutivas (excluir).

### Estados visuais
- `normal`: cor de fundo e borda definidas por variante.
- `hover`: tom mais intenso e contraste maior.
- `active`: `scale(0.97)` para feedback tátil.
- `disabled`: `opacity: .45` e `pointer-events: none`.

## Acessibilidade
- Todo botão de ícone deve conter:
  - `title` (tooltip nativo)
  - `aria-label` descritivo
  - texto auxiliar com `.sr-only`
- Contraste:
  - ícone/texto vs fundo em conformidade com WCAG 2.1 AA.
  - evitar ícones com opacidade baixa em estado normal.

## Mapeamento semântico recomendado
- Editar: lápis
- Excluir: lixeira
- Visualizar: olho
- Voltar: seta à esquerda
- Confirmar/Salvar rápido: check
- Filtrar: sliders/linhas de filtro
- Exportar: documento/download
- Copiar: ícone de cópia

## Responsividade
- Preferir `--sm` em tabelas/listas.
- Preferir `--md` em toolbars de página.
- Evitar texto visível no botão de ação iconificada; usar `sr-only`.
