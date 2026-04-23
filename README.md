# TRAXTER CRM

Sistema SaaS multi-tenant para gestão de clientes, projetos, tarefas, financeiro e orçamentos.

## Stack
- PHP 8.1+
- MariaDB 10.6+
- Composer 2+
- Node.js 18+ (build de assets)
- TypeScript + Tailwind

## Setup local rápido
1. Instalar dependências PHP:

```bash
composer install
```

2. Instalar dependências front:

```bash
npm install
```

3. Gerar assets:

```bash
npm run build
```

4. Criar arquivo de ambiente:

```bash
copy .env.example .env
```

5. Provisionar banco e dados iniciais:

```bash
composer setup-db
composer migrate-db
```

6. Subir servidor local:

```bash
php -S 127.0.0.1:8000 -t public
```

## Pacote para Hostinger
Gerar pacote local pronto para upload manual em hospedagem compartilhada:

```bash
composer package-hostinger
```

Documentação específica:
- `docs/hostinger-shared-hosting-deploy.md`

7. Acessar:
- Login: `http://127.0.0.1:8000/login`
- Instalador web: `http://127.0.0.1:8000/install`

## Validação de ambiente
```bash
composer verify-db
composer test
```

## Versionamento GitHub (`fozuna/gestor`)
### Inicializar e configurar remoto
```bash
git init
git add .
git commit -m "chore: bootstrap project"
git branch -M main
git checkout -b develop
git checkout main
git remote add origin https://github.com/fozuna/gestor.git
```

### Publicar branches
```bash
git push -u origin main
git push -u origin develop
```

### Fluxo sugerido
- `main`: produção (somente releases estáveis)
- `develop`: integração contínua
- `feature/*`: novas funcionalidades
- `hotfix/*`: correções urgentes

## Segurança e segredos
- Nunca versionar `.env` real.
- Usar somente `.env.example` com placeholders.
- Rotacionar credenciais em produção.
- Configurar HTTPS obrigatório.

## Documentação adicional
- Módulo financeiro/clientes/tarefas: `docs/financeiro-clientes-tarefas.md`
- Módulo orçamentos: `docs/orcamentos-modulo.md`
- Gestão de logos: `docs/logo-management.md`
- Guia completo de produção: `docs/production-guide.md`
- Deploy Hostinger compartilhado: `docs/hostinger-shared-hosting-deploy.md`
