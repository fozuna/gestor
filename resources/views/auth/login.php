<?php
use App\Services\BrandLogoService;

$title = 'Entrar · TRAXTER CRM';
$brandLogo = new BrandLogoService();
$authLogo = $brandLogo->webLogoForBackground('#09090b');
ob_start();
?>
<div class="min-h-screen flex items-center justify-center px-4 bg-zinc-950">
  <div class="w-full max-w-md">
    <div class="mb-6">
      <div class="brand-logo-box brand-logo-box--auth">
        <img
          src="<?= htmlspecialchars((string)$authLogo['src'], ENT_QUOTES, 'UTF-8') ?>"
          alt="Logo TRAXTER"
          class="brand-logo-img"
          loading="eager"
          decoding="async"
        />
      </div>
      <div class="mt-1 text-2xl font-semibold text-[#F5F5DC]">TRAXTER CRM</div>
      <div class="mt-2 text-sm text-zinc-400">Acesse seu workspace com segurança.</div>
    </div>

    <div class="rounded-2xl border border-zinc-900 bg-zinc-950/60 backdrop-blur p-6 shadow-[0_20px_80px_rgba(0,0,0,0.55)]">
      <?php if (!empty($error)): ?>
        <div class="mb-4 rounded-lg border border-red-900/40 bg-red-950/30 px-3 py-2 text-sm text-red-200">
          <?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="/login" class="space-y-4">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />

        <label class="block">
          <div class="text-xs text-zinc-400">E-mail</div>
          <input name="email" type="email" required autocomplete="email" class="mt-1 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 outline-none focus:ring-2 focus:ring-[#FE5516]/50" />
        </label>

        <label class="block">
          <div class="text-xs text-zinc-400">Senha</div>
          <input name="password" type="password" required autocomplete="current-password" class="mt-1 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 outline-none focus:ring-2 focus:ring-[#FE5516]/50" />
        </label>

        <button type="submit" class="w-full rounded-lg bg-[#FE5516] px-4 py-2.5 text-sm font-semibold text-zinc-950 hover:opacity-90 transition">Entrar</button>
      </form>

      <div class="mt-4 flex items-center justify-between">
        <button type="button" data-theme-toggle class="text-xs text-zinc-400 hover:text-[#F5F5DC] transition">Alternar tema</button>
        <a class="text-xs text-zinc-500 hover:text-zinc-300 transition" href="/install">Instalador</a>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/base.php';

