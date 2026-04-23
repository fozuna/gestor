<?php
use App\Services\BrandLogoService;

$title = 'Instalar · TRAXTER CRM';
$brandLogo = new BrandLogoService();
$authLogo = $brandLogo->webLogoForBackground('#09090b');
ob_start();
?>
<div class="min-h-screen flex items-center justify-center px-4 bg-zinc-950">
  <div class="w-full max-w-2xl">
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
      <div class="mt-1 text-2xl font-semibold text-[#F5F5DC]">Instalador</div>
      <div class="mt-2 text-sm text-zinc-400">Configure o banco e crie o admin inicial.</div>
    </div>

    <div class="rounded-2xl border border-zinc-900 bg-zinc-950/60 backdrop-blur p-6 shadow-[0_20px_80px_rgba(0,0,0,0.55)]">
      <?php if (!empty($ok)): ?>
        <div class="mb-4 rounded-lg border border-emerald-900/40 bg-emerald-950/30 px-3 py-2 text-sm text-emerald-200">
          <?= htmlspecialchars((string)$ok, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($error)): ?>
        <div class="mb-4 rounded-lg border border-red-900/40 bg-red-950/30 px-3 py-2 text-sm text-red-200">
          <?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <div class="mb-4 rounded-lg border border-zinc-900 bg-zinc-950/70 px-4 py-3 text-xs text-zinc-400">
        O setup cria o banco automaticamente se o usuário informado tiver permissão e evita duplicar o admin padrão.
      </div>

      <form method="POST" action="/install" class="grid gap-4">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />

        <div class="grid gap-4 md:grid-cols-2">
          <label class="block">
            <div class="text-xs text-zinc-400">APP_URL</div>
            <input name="app_url" type="url" value="<?= htmlspecialchars((string)($defaults['app_url'] ?? 'http://localhost:8000'), ENT_QUOTES, 'UTF-8') ?>" placeholder="http://localhost:8000" class="mt-1 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 outline-none focus:ring-2 focus:ring-[#FE5516]/50" />
          </label>
          <label class="block">
            <div class="text-xs text-zinc-400">Ambiente (empresa)</div>
            <input name="tenant_name" required value="<?= htmlspecialchars((string)($defaultTenantName ?? 'TRAXTER'), ENT_QUOTES, 'UTF-8') ?>" placeholder="TRAXTER" class="mt-1 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 outline-none focus:ring-2 focus:ring-[#FE5516]/50" />
          </label>
        </div>

        <div class="grid gap-4 md:grid-cols-4">
          <label class="block md:col-span-2">
            <div class="text-xs text-zinc-400">DB_HOST</div>
            <input name="db_host" required value="<?= htmlspecialchars((string)($defaults['db_host'] ?? '127.0.0.1'), ENT_QUOTES, 'UTF-8') ?>" placeholder="127.0.0.1" class="mt-1 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 outline-none focus:ring-2 focus:ring-[#FE5516]/50" />
          </label>
          <label class="block">
            <div class="text-xs text-zinc-400">DB_PORT</div>
            <input name="db_port" required value="<?= htmlspecialchars((string)($defaults['db_port'] ?? '3306'), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 outline-none focus:ring-2 focus:ring-[#FE5516]/50" />
          </label>
          <label class="block">
            <div class="text-xs text-zinc-400">SESSION_SECURE</div>
            <select name="session_secure" class="mt-1 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 outline-none focus:ring-2 focus:ring-[#FE5516]/50">
              <option value="false" <?= ($defaults['session_secure'] ?? 'false') === 'false' ? 'selected' : '' ?>>false</option>
              <option value="true" <?= ($defaults['session_secure'] ?? 'false') === 'true' ? 'selected' : '' ?>>true</option>
            </select>
          </label>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
          <label class="block">
            <div class="text-xs text-zinc-400">DB_DATABASE</div>
            <input name="db_name" required value="<?= htmlspecialchars((string)($defaults['db_name'] ?? 'traxter'), ENT_QUOTES, 'UTF-8') ?>" placeholder="traxter" class="mt-1 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 outline-none focus:ring-2 focus:ring-[#FE5516]/50" />
          </label>
          <label class="block">
            <div class="text-xs text-zinc-400">DB_USERNAME</div>
            <input name="db_user" required value="<?= htmlspecialchars((string)($defaults['db_user'] ?? 'root'), ENT_QUOTES, 'UTF-8') ?>" placeholder="root" class="mt-1 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 outline-none focus:ring-2 focus:ring-[#FE5516]/50" />
          </label>
          <label class="block">
            <div class="text-xs text-zinc-400">DB_PASSWORD</div>
            <input name="db_pass" type="password" class="mt-1 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 outline-none focus:ring-2 focus:ring-[#FE5516]/50" />
          </label>
        </div>

        <div class="mt-2 border-t border-zinc-900 pt-4 grid gap-4 md:grid-cols-2">
          <label class="block">
            <div class="text-xs text-zinc-400">Admin e-mail</div>
            <input name="admin_email" type="email" required value="<?= htmlspecialchars((string)($defaultAdminEmail ?? 'admin@traxter.com.br'), ENT_QUOTES, 'UTF-8') ?>" placeholder="admin@traxter.com.br" class="mt-1 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 outline-none focus:ring-2 focus:ring-[#FE5516]/50" />
          </label>
          <label class="block">
            <div class="text-xs text-zinc-400">Admin senha</div>
            <input name="admin_password" type="password" required minlength="8" placeholder="Ab23082524@" class="mt-1 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 outline-none focus:ring-2 focus:ring-[#FE5516]/50" />
          </label>
        </div>

        <button type="submit" class="mt-2 w-full rounded-lg bg-[#FE5516] px-4 py-2.5 text-sm font-semibold text-zinc-950 hover:opacity-90 transition">Instalar</button>
      </form>

      <div class="mt-4 text-xs text-zinc-500">Ao concluir, o instalador cria o arquivo <span class="text-zinc-300">.env</span>, aplica o schema completo e bloqueia reexecução web.</div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/base.php';

