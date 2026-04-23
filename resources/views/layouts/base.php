<?php
use App\Helpers\Config;
use App\Helpers\Path;

$app = Config::get('app');
$title = $title ?? $app['name'];
$content = $content ?? '';
$publicPath = Path::public('assets');
$appCssVersion = is_file($publicPath . '/app.css') ? (string)filemtime($publicPath . '/app.css') : '1';
$appJsVersion = is_file($publicPath . '/app.js') ? (string)filemtime($publicPath . '/app.js') : '1';
?>
<!doctype html>
<html lang="pt-BR" class="h-full scroll-smooth">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="stylesheet" href="/assets/app.css?v=<?= htmlspecialchars($appCssVersion, ENT_QUOTES, 'UTF-8') ?>" />

  <style>
    /* ================================
       LOGO / BRAND
    ================================= */
    .brand-logo-box {
      display: flex;
      align-items: center;
      overflow: hidden;
      line-height: 0;
      max-width: 100%;
    }

    .brand-logo-box--sidebar { height: 40px; width: min(230px, 100%); }
    .brand-logo-box--header-desktop { height: 28px; width: min(180px, 100%); }
    .brand-logo-box--header-mobile { height: 24px; width: min(138px, 100%); }
    .brand-logo-box--auth { height: 40px; width: min(230px, 100%); }

    .brand-logo-img {
      display: block;
      width: auto;
      height: 100%;
      max-width: 100%;
      object-fit: contain;
      object-position: left center;
    }

    /* ================================
       BOTÕES (ISOLADO DO FRAMEWORK)
    ================================= */

    /* Evita conflito com .btn-danger de frameworks */
    .btn-confirm-delete {
      background: #dd6b00ff !important;
      color: #111827;
      border: 1px solid #E7E5C9;
      padding: 0.5rem 1rem;
      border-radius: 0.375rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .btn-confirm-delete:hover {
      background: #ECECCF;
      border-color: #DBD8B8;
    }

    .btn-confirm-delete:active {
      background: #E2E1C0;
      border-color: #CFCCA7;
    }

    .btn-confirm-delete:focus-visible {
      outline: 2px solid rgba(220, 38, 38, 0.35);
      outline-offset: 2px;
    }

    /* ================================
       ÍCONES DE AÇÃO (PADRÃO GLOBAL)
    ================================= */
    .sr-only {
      position: absolute;
      width: 1px;
      height: 1px;
      padding: 0;
      margin: -1px;
      overflow: hidden;
      clip: rect(0, 0, 0, 0);
      white-space: nowrap;
      border: 0;
    }

    .icon-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 0.75rem;
      border: 1px solid #e4e4e7;
      background: #ffffff;
      color: #1f2937;
      opacity: 1;
      transition: background-color .18s ease, border-color .18s ease, color .18s ease, transform .12s ease;
      text-decoration: none;
    }
    .icon-btn svg {
      width: 16px;
      height: 16px;
      stroke-width: 2;
      flex: 0 0 auto;
    }
    .icon-btn--sm { width: 32px; height: 32px; }
    .icon-btn--md { width: 40px; height: 40px; }
    .icon-btn--lg { width: 48px; height: 48px; }
    .icon-btn:hover { background: #f4f4f5; border-color: #d4d4d8; }
    .icon-btn:active { transform: scale(0.97); }
    .icon-btn:focus-visible {
      outline: 2px solid rgba(59, 130, 246, 0.35);
      outline-offset: 2px;
    }
    .icon-btn[disabled], .icon-btn.is-disabled {
      opacity: .45;
      pointer-events: none;
    }
    .icon-btn--danger { color: #b91c1c; border-color: #fecaca; background: #fff5f5; }
    .icon-btn--danger:hover { color: #991b1b; border-color: #fca5a5; background: #fee2e2; }
    .icon-btn--primary { color: #1d4ed8; border-color: #bfdbfe; background: #eff6ff; }
    .icon-btn--primary:hover { color: #1e40af; border-color: #93c5fd; background: #dbeafe; }
    .icon-btn--success { color: #166534; border-color: #bbf7d0; background: #f0fdf4; }
    .icon-btn--success:hover { color: #14532d; border-color: #86efac; background: #dcfce7; }

    /* ================================
       RESPONSIVO
    ================================= */
    @media (max-width: 640px) {
      .brand-logo-box--sidebar,
      .brand-logo-box--auth {
        width: min(180px, 100%);
      }

      .brand-logo-box--header-desktop {
        width: min(140px, 100%);
      }
    }
  </style>

  <script type="module" src="/assets/app.js?v=<?= htmlspecialchars($appJsVersion, ENT_QUOTES, 'UTF-8') ?>" defer></script>
</head>

<body class="min-h-full bg-[#f8f9fa] text-zinc-900 antialiased">
  <?= $content ?>
</body>
</html>
