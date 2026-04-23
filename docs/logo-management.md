# Gerenciamento Centralizado de Logos

## Objetivo
- Centralizar o uso de logos no sistema web e relatórios.
- Selecionar automaticamente a versão correta por contraste de fundo:
  - `logo-clara.png` para fundos escuros
  - `logo-escura.png` para fundos claros

## Arquivos de logo
- Diretório padrão: `public/assets/images`
- Nomes esperados:
  - `logo-clara.png`
  - `logo-escura.png`

## Serviço central
- Classe: `App\Services\BrandLogoService`
- Responsabilidades:
  - detectar contraste de fundo via `hex` (`#fff`, `#ffffff`) e `rgb(...)`
  - aplicar fallback para variante escura quando não for possível detectar
  - retornar caminho web com versionamento por `filemtime` (cache busting)
  - retornar `data URI` para PDF
  - fallback para SVG inline quando arquivos não existirem
  - registrar erro em log quando logo não for encontrada

## Uso no web
- Método: `webLogoForBackground(string $background): array`
- Exemplo:
  - `['variant' => 'clara|escura', 'src' => '/assets/images/logo-...png?v=...']`
- Proporção responsiva:
  - usar classes globais em `resources/views/layouts/base.php`:
    - `.brand-logo-box`
    - `.brand-logo-box--sidebar`
    - `.brand-logo-box--header-desktop`
    - `.brand-logo-box--header-mobile`
    - `.brand-logo-box--auth`
    - `.brand-logo-img`
  - essas classes evitam distorção quando utilitários de CSS não estão disponíveis no build atual.
- Hierarquia visual no shell principal:
  - sidebar desktop: logo da marca + perfil de acesso
  - topo desktop: apenas contexto de ambiente (sem repetir logo)
  - topo mobile: mantém logo compacta (sem sidebar visível)
  - objetivo: reduzir repetição visual de marca e manter foco no conteúdo funcional.

## Uso em relatórios PDF
- Método: `pdfLogoDataUriForBackground(string $tenantName, string $background, ?string $customLogoPath = null): string`
- No relatório de tarefas:
  - se `GD` estiver disponível, usa imagem da logo
  - se `GD` não estiver disponível, usa cabeçalho textual (fallback sem quebrar geração)

## Cobertura de testes
- `tests/Unit/BrandLogoServiceTest.php`
  - seleção de variante para fundo claro/escuro
  - fallback de variante para fundo inválido
  - caminho web com cache versionado
  - fallback SVG quando assets não existem

## Manutenção
- Para trocar a identidade visual, basta substituir os dois arquivos em `public/assets/images`.
- Evite uso direto de caminhos de logo em views/serviços; sempre utilize `BrandLogoService`.
- Ao adicionar nova tela com logo:
  - renderize via `BrandLogoService`
  - aplique um dos wrappers `.brand-logo-box--*`
  - mantenha `img` com `.brand-logo-img` para preservar proporção.
