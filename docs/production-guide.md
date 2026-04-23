# Guia Completo de Produção

## 1) Requisitos de servidor
### Sistema operacional
- Ubuntu 22.04 LTS (recomendado) ou Debian 12

### Runtime e serviços
- PHP 8.2 com FPM
- MariaDB 10.6+
- Nginx 1.22+ ou Apache 2.4+
- Node.js 18+ (somente para build durante deploy)
- Composer 2+

### Extensões PHP obrigatórias
- `pdo_mysql`
- `mbstring`
- `openssl`
- `json`
- `ctype`
- `session`
- `fileinfo`
- `gd` (recomendado para renderização de imagens em PDF)

## 2) Estrutura de diretórios recomendada
```bash
/var/www/gestor
  |- current -> /var/www/gestor/releases/2026xxxxxx
  |- releases/
  |- shared/
      |- .env
      |- storage/
```

## 3) Variáveis de ambiente
### Exemplo base (`shared/.env`)
```dotenv
APP_NAME="TRAXTER CRM"
APP_ENV=production
APP_URL=https://gestor.seudominio.com
APP_KEY=gere_uma_chave_forte
APP_DEBUG=false

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=traxter
DB_USERNAME=traxter_user
DB_PASSWORD=senha_forte

SESSION_NAME=traxter_session
SESSION_SECURE=true
SESSION_SAMESITE=Lax
APP_TIMEZONE=America/Campo_Grande
```

### Validação
```bash
php -r "require 'bootstrap/app.php'; echo getenv('APP_ENV') ?: 'missing';"
```

## 3.1) Timezone de aplicação e PHP
- Timezone padrão da aplicação: `America/Campo_Grande` (GMT-4) via `bootstrap/app.php`.
- Variável de ambiente: `APP_TIMEZONE=America/Campo_Grande`.

### Ajuste no `php.ini` (produção)
Editar o `php.ini` carregado pelo FPM/Apache:
```bash
php -i | grep "Loaded Configuration File"
```

Definir:
```ini
date.timezone = America/Campo_Grande
```

Reiniciar serviços:
```bash
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx
```

Validação:
```bash
php -r "echo date_default_timezone_get(), PHP_EOL;"
php -r "require 'bootstrap/app.php'; echo date('c'), PHP_EOL;"
```

## 4) Setup de banco de dados
### Criar usuário e base
```sql
CREATE DATABASE traxter CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'traxter_user'@'127.0.0.1' IDENTIFIED BY 'senha_forte';
GRANT ALL PRIVILEGES ON traxter.* TO 'traxter_user'@'127.0.0.1';
FLUSH PRIVILEGES;
```

### Aplicar schema e migrações
```bash
composer install --no-dev --optimize-autoloader
php bin/setup.php --no-env --no-lock --db-host=127.0.0.1 --db-port=3306 --db-name=traxter --db-user=traxter_user --db-pass=senha_forte
php bin/migrate.php
```

### Validação
```bash
composer verify-db
```

## 5) Configuração Nginx (recomendada)
Arquivo: `/etc/nginx/sites-available/gestor.conf`

```nginx
server {
    listen 80;
    server_name gestor.seudominio.com;
    root /var/www/gestor/current/public;
    index index.php;

    client_max_body_size 20m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Ativar:
```bash
sudo ln -s /etc/nginx/sites-available/gestor.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## 6) Configuração Apache (alternativa)
VirtualHost: `/etc/apache2/sites-available/gestor.conf`

```apache
<VirtualHost *:80>
    ServerName gestor.seudominio.com
    DocumentRoot /var/www/gestor/current/public

    <Directory /var/www/gestor/current/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/gestor_error.log
    CustomLog ${APACHE_LOG_DIR}/gestor_access.log combined
</VirtualHost>
```

Ativar:
```bash
sudo a2enmod rewrite
sudo a2ensite gestor.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

## 6.1) Hostinger / `public_html`
- Em hospedagem compartilhada da Hostinger, o domínio normalmente serve a pasta `public_html`.
- Este repositório agora suporta deploy direto dentro de `public_html` com:
  - `.htaccess` na raiz do projeto reescrevendo para `public/`
  - `index.php` na raiz delegando para `public/index.php`
  - `public/.htaccess` atuando como front controller padrão

### Estrutura recomendada na Hostinger
```bash
/home/uXXXX/domains/gestor.traxter.com.br/public_html
  |- app/
  |- bootstrap/
  |- config/
  |- public/
  |   |- index.php
  |   |- .htaccess
  |   |- assets/
  |- storage/
  |- vendor/
  |- .env
  |- .htaccess
  |- index.php
```

### Passos mínimos
```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php bin/migrate.php
```

### Causa típica do erro 403
- O Apache/Hostinger tenta abrir a raiz de `public_html`, mas sem `index.php` e sem rewrite para `public/` o acesso fica negado.
- Se o projeto foi publicado inteiro dentro de `public_html`, o `.htaccess` da raiz resolve esse cenário automaticamente.

### Checklist Hostinger
- Confirmar que `mod_rewrite` está habilitado no ambiente Apache da hospedagem.
- Confirmar que `vendor/` foi instalado com `composer install`.
- Confirmar que os assets foram gerados em `public/assets` com `npm run build`.
- Confirmar permissões de escrita em `storage/` para backups e recibos.
- Confirmar que existe `.env` válido na raiz do projeto dentro de `public_html`.

## 7) SSL / certificados
Usando Certbot (Nginx):
```bash
sudo apt update
sudo apt install certbot python3-certbot-nginx -y
sudo certbot --nginx -d gestor.seudominio.com
sudo certbot renew --dry-run
```

Checklist SSL:
- redirecionamento HTTP -> HTTPS ativo
- TLS 1.2+ habilitado
- HSTS habilitado (opcional/recomendado)

## 8) CI/CD (GitHub Actions)
Arquivo: `.github/workflows/deploy.yml` (modelo)
```yaml
name: Deploy
on:
  push:
    branches: [main]
jobs:
  test-and-deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - run: composer install --no-interaction --prefer-dist
      - run: php vendor/bin/phpunit --configuration phpunit.xml
      - name: Deploy via SSH
        run: echo "Executar rsync/scp + comandos remotos"
```

Segredos no GitHub:
- `SSH_HOST`
- `SSH_USER`
- `SSH_KEY`
- `DEPLOY_PATH`

## 9) Estratégia de deployment
### Zero/baixo downtime (release por pasta)
```bash
# no servidor
mkdir -p /var/www/gestor/releases/$(date +%Y%m%d%H%M%S)
rsync -avz --delete ./ /var/www/gestor/releases/<release>/
ln -sfn /var/www/gestor/shared/.env /var/www/gestor/releases/<release>/.env
ln -sfn /var/www/gestor/shared/storage /var/www/gestor/releases/<release>/storage
cd /var/www/gestor/releases/<release>
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php bin/migrate.php
ln -sfn /var/www/gestor/releases/<release> /var/www/gestor/current
sudo systemctl reload php8.2-fpm
sudo systemctl reload nginx
```

Rollback:
```bash
ln -sfn /var/www/gestor/releases/<release_anterior> /var/www/gestor/current
sudo systemctl reload php8.2-fpm
sudo systemctl reload nginx
```

## 10) Monitoramento e logging
### Logs da aplicação e infra
- Nginx: `/var/log/nginx/access.log`, `/var/log/nginx/error.log`
- PHP-FPM: `/var/log/php8.2-fpm.log`
- Sistema: `journalctl -u nginx -u php8.2-fpm`

### Recomendado
- Uptime monitoring: Uptime Kuma / Pingdom
- Métricas servidor: Netdata / Prometheus + Grafana
- Alertas: CPU, RAM, disco, erro HTTP 5xx, latência

## 11) Backup e disaster recovery
### Banco diário
```bash
mysqldump -u traxter_user -p traxter | gzip > /backup/traxter_$(date +%F).sql.gz
```

### Arquivos críticos
- `shared/.env`
- `shared/storage/backups`
- uploads (se existirem)

### Política recomendada
- retenção: 7 diários, 4 semanais, 6 mensais
- cópia externa: S3/Backblaze/servidor secundário
- teste de restore mensal obrigatório

Restore de validação:
```bash
gunzip -c /backup/traxter_2026-04-16.sql.gz | mysql -u traxter_user -p traxter_restore_test
```

## 12) Escalabilidade e performance
- PHP OPcache habilitado
- gzip/brotli habilitado no web server
- cache de assets com `Cache-Control` longo
- banco com índices revisados e plano de execução monitorado
- separar banco em host dedicado conforme crescimento
- usar CDN para assets estáticos

## 13) Checklist final de validação
1. `composer verify-db` sem erro
2. `composer test` (ou suite mínima crítica) verde
3. Login, dashboard e módulos críticos acessíveis em HTTPS
4. Exclusão protegida, transações e auditoria funcionando
5. Backup executado e restore de teste validado
6. Monitoramento e alertas ativos
7. Certificado SSL válido e renovação automática testada
