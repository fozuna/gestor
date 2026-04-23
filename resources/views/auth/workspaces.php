<?php
use App\Services\BrandLogoService;

$title = 'Selecionar workspace · TRAXTER CRM';
$brandLogo = new BrandLogoService();
$authLogo = $brandLogo->webLogoForBackground('#09090b');
ob_start();
?>
<div class="min-h-screen flex items-center justify-center px-4 bg-zinc-950">
  <div class="w-full max-w-lg">
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
      <div class="mt-1 text-2xl font-semibold text-[#F5F5DC]">Selecione um workspace</div>
      <div class="mt-2 text-sm text-zinc-400">Você possui acesso a mais de uma empresa.</div>
    </div>

    <div class="rounded-2xl border border-zinc-900 bg-zinc-950/60 backdrop-blur p-6 shadow-[0_20px_80px_rgba(0,0,0,0.55)]">
      <?php if (!empty($error)): ?>
        <div class="mb-4 rounded-lg border border-red-900/40 bg-red-950/30 px-3 py-2 text-sm text-red-200">
          <?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="/workspaces/select" class="space-y-3">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />

        <div class="space-y-2">
          <?php foreach (($items ?? []) as $i): ?>
            <label class="block">
              <input class="peer sr-only" type="radio" name="tenant_id" value="<?= (int)$i['tenant_id'] ?>" required />
              <div class="rounded-xl border border-zinc-900 bg-zinc-950 px-4 py-3 transition peer-checked:border-[#FE5516]/60 peer-checked:bg-[#FE5516]/10 hover:bg-zinc-900/30">
                <div class="text-sm font-medium text-[#F5F5DC]"><?= htmlspecialchars((string)$i['tenant_name'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="mt-1 text-xs text-zinc-400">Perfil: <?= htmlspecialchars((string)$i['role'], ENT_QUOTES, 'UTF-8') ?></div>
              </div>
            </label>
          <?php endforeach; ?>
        </div>

        <button type="submit" class="w-full rounded-lg bg-[#FE5516] px-4 py-2.5 text-sm font-semibold text-zinc-950 hover:opacity-90 transition">Continuar</button>
      </form>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/base.php';

