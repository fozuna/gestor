# Deploy Hostinger Shared Hosting

## Objetivo
- Corrigir `403 Forbidden` em hospedagem compartilhada Hostinger.
- Rodar a aplicação em subdomínio com `public_html` como raiz pública.
- Evitar dependência de `npm` e `composer` no servidor.

## O que causava o 403
- O projeto foi desenvolvido para iniciar por `public/index.php`.
- Na Hostinger, o subdomínio costuma servir diretamente `public_html`.
- Sem `index.php` e sem regras de rewrite adequadas na raiz pública, o Apache tenta abrir diretório ou bloqueia o acesso.
- Regras faltantes ou incorretas no `.htaccess` também podem provocar `403`.

## Fluxo do redirecionamento para `/install`
- O redirecionamento para `/install` acontece em `AuthController::loginView`.
- Ele ocorre quando `Installer::status()['installed']` retorna `false`.
- Isso nao representa checagem de licenca; e uma verificacao de instalacao/ambiente.

### Condicoes que disparam o redirect
- arquivo `.env` ausente
- `.env` incompleto para banco
- banco inacessivel
- tabelas-base ausentes: `users`, `tenants`, `memberships`, `settings`

### Logs de diagnostico
- Arquivo: `storage/logs/install-redirect-YYYYMMDD.log`
- O log grava:
  - origem do redirect
  - URL e query string
  - metodo HTTP
  - sessao
  - cookies
  - stack trace
  - status detalhado do instalador

## Solução aplicada
- `index.php` na raiz para fallback quando o projeto inteiro fica dentro de `public_html`.
- `.htaccess` na raiz com:
  - `DirectoryIndex`
  - `mod_rewrite`
  - fallback para `public/index.php`
  - bloqueio de arquivos sensíveis
- `public/.htaccess` com front controller padrão.
- Geração de recibos padronizada em `Dompdf`, sem `proc_open`, Node, Chrome ou binários do sistema.
- Bootstrap central em `bootstrap/runtime.php` com suporte a:
  - `.env`
  - `config/config.php.local`
  - `config/config.php.production`
  - override privado opcional via `config/config.php`
  - timezone e paths centrais
- O detector de instalação agora não depende só de `storage/installed.lock`:
  - se existir `.env` válido
  - se o banco responder
  - se as tabelas-base existirem
  - o sistema considera a aplicação instalada e recria o lock automaticamente quando possível
- Script local de empacotamento para gerar pacote pronto de upload:
  - `php bin/prepare-hostinger-package.php`
  - ou `composer package-hostinger`
- Script de diagnóstico de produção:
  - `php bin/check-production-readiness.php`
  - ou `composer check-prod`

## Estrutura final de upload
```text
public_html/
  .htaccess
  index.php
  assets/
    app.css
    app.js

private/
    config/
      app.php
      config.php.local
      config.php.production
      database.php
      session.php
  .env
  .env.example
  app/
  bin/
  bootstrap/
  install/
  public/
    index.php
    .htaccess
    assets/
      app.css
      app.js
  resources/
  routes/
  scripts/
  storage/
  vendor/
  composer.json
  composer.lock
```

## Estrutura publica no repositório
- O projeto agora versiona `public_html/index.php` e `public_html/.htaccess`.
- Esses arquivos servem como base publica padronizada para Apache/Hostinger.
- O empacotador reaproveita exatamente essa estrutura ao gerar `build/hostinger-upload/public_html`.

## Gerar pacote local
### Pré-requisitos locais
- `composer install --no-dev --optimize-autoloader`
- `npm install`
- `npm run build`

### Gerar pacote
```bash
composer package-hostinger
```

Saída:
```text
build/hostinger-upload/
  README-HOSTINGER.txt
  public_html/
  private/
```

## Upload manual
### Via FTP ou File Manager
1. Apague ou mova o conteúdo antigo do subdomínio.
2. Envie `build/hostinger-upload/public_html/*` para a pasta `public_html` do subdomínio.
3. Envie `build/hostinger-upload/private/*` para uma pasta `private` no mesmo nível de `public_html`.
4. Crie `private/.env` a partir de `private/.env.example`.
5. Ajuste as variáveis reais de banco, URL e sessão.

## Permissões
- Diretórios: `755`
- Arquivos: `644`
- Garantir escrita em:
  - `private/storage/`
  - `private/storage/backups/`
  - `private/storage/recibos/`

## .htaccess pronto para uso
### `public_html/.htaccess`
```apache
Options -Indexes
DirectoryIndex index.php

<IfModule mod_rewrite.c>
    RewriteEngine On

    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]

    RewriteRule ^ index.php [QSA,L]
</IfModule>

<FilesMatch "^\.">
    Require all denied
</FilesMatch>
```

## index.php público
### `public_html/index.php`
```php
<?php
declare(strict_types=1);

require __DIR__ . '/../private/public/index.php';
```

## Configuração recomendada de ambiente
```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://gestor.traxter.com.br
SESSION_SECURE=true
RECEIPT_PDF_RENDERER=dompdf
```

## Alternativa sem `.env`
- Se a hospedagem falhar ao carregar `.env`, o bootstrap escolhe automaticamente:
  - `private/config/config.php.local` em ambiente local
  - `private/config/config.php.production` em produção
- Se precisar de credenciais privadas fora do Git, crie `private/config/config.php`.
- O bootstrap usa a configuração do ambiente para preencher valores ausentes de:
  - `APP_ENV`
  - `APP_URL`
  - `APP_KEY`
  - banco
  - sessão
  - timezone

## Sem npm/composer no servidor
- O pacote final já leva:
  - `vendor/`
  - `public_html/assets/`
  - `private/public/assets/`
- Isso elimina dependência de build e instalação de dependências via SSH.
- Em runtime, a aplicação não depende de:
  - `npm`
  - `node`
  - `composer`
  - `proc_open`
  - binários do sistema

## Checklist final
- `public_html/index.php` existe.
- `public_html/.htaccess` existe.
- `public_html/assets/app.css` e `public_html/assets/app.js` existem.
- `private/vendor/autoload.php` existe.
- `private/.env` existe e está correto, ou os valores necessários estão em `private/config/config.php`.
- Permissões `755` em pastas e `644` em arquivos.
- `private/storage` tem escrita.
- `composer check-prod` retorna `OK` para `.env`, banco e installer.
- Navegação abre sem `403`.
- Rotas funcionam.
- CSS e JS carregam sem `404`.

## Observações
- Logos em `public/assets/images` são opcionais; se não existirem, o sistema usa fallback SVG.
- Recibos PDF usam `Dompdf`, adequado para Apache compartilhado.
