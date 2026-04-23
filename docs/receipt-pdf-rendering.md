# Renderização de Recibos em PDF

## Biblioteca utilizada
- Recibos: `puppeteer-core` + Chrome/Edge local
- Outros relatórios: permanecem em `Dompdf`

## Motivo da mudança
- `Dompdf` não renderizava corretamente o recibo compacto:
  - baixa fidelidade em CSS de página
  - limitação para altura variável real
  - comportamento inconsistente ao simular cupom/80mm

## Escopo
- A mudança afeta exclusivamente a geração de recibos.
- Nenhum outro relatório do sistema foi migrado.

## Arquivos principais
- `app/Services/PaymentReceiptService.php`
- `app/Services/ReceiptChromiumPdfRenderer.php`
- `scripts/render-receipt-pdf.mjs`

## Instalação
```bash
npm install
```

## Dependências externas
- Node.js disponível no ambiente
- Chrome ou Microsoft Edge instalado

## Override opcional
- Definir `CHROME_PATH` quando necessário para apontar para o executável do navegador.

## Formato do recibo
- Largura fixa: `80mm`
- Altura: calculada dinamicamente pelo Chromium com base no conteúdo renderizado
- Layout: cupom/comprovante vertical

## Validação recomendada
- Gerar um recibo de teste e verificar:
  - sem corte de conteúdo
  - sem grandes áreas em branco
  - largura compacta real
  - quebra automática de linhas longas
