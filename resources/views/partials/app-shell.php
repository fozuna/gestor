<?php
use App\Helpers\CSRF;
use App\Services\BrandLogoService;

$active = $active ?? 'dashboard';
$tenantName = $tenantName ?? 'TRAXTER';
$role = $role ?? 'user';
$roleLabel = [
  'admin' => 'Administrador',
  'gestor' => 'Gestor',
  'user' => 'Usuário',
][(string)$role] ?? (string)$role;

$brandLogo = new BrandLogoService();
$sidebarLogo = $brandLogo->webLogoForBackground('#FE5516');
?>
<div class="min-h-screen bg-[#f8f9fa] text-zinc-900">
  <div class="flex min-h-screen">
    <aside class="hidden md:fixed md:inset-y-0 md:left-0 md:block md:w-72 overflow-hidden bg-[#FE5516] text-white shadow-2xl">
      <div class="p-5">
        <div class="brand-logo-box brand-logo-box--sidebar">
          <img
            src="<?= htmlspecialchars((string)$sidebarLogo['src'], ENT_QUOTES, 'UTF-8') ?>"
            alt="Logo TRAXTER"
            class="brand-logo-img"
            loading="eager"
            decoding="async"
          />
        </div>
        <div class="mt-2 inline-flex rounded-full border border-white/25 bg-white/10 px-2.5 py-1 text-xs text-white/90">
          <?= htmlspecialchars((string)$roleLabel, ENT_QUOTES, 'UTF-8') ?>
        </div>
      </div>
      <nav class="px-3 pb-6 space-y-1 text-sm">
        <a class="block rounded-xl px-4 py-3 transition <?= $active==='dashboard'?'bg-white/20 text-[#F5F5DC] shadow':'text-white hover:bg-white/15 hover:text-[#F5F5DC]' ?>" href="/dashboard">Painel</a>
        <a class="block rounded-xl px-4 py-3 transition <?= $active==='clients'?'bg-white/20 text-[#F5F5DC] shadow':'text-white hover:bg-white/15 hover:text-[#F5F5DC]' ?>" href="/clients">Clientes</a>
        <a class="block rounded-xl px-4 py-3 transition <?= $active==='projects'?'bg-white/20 text-[#F5F5DC] shadow':'text-white hover:bg-white/15 hover:text-[#F5F5DC]' ?>" href="/projetos">Projetos</a>
        <a class="block rounded-xl px-4 py-3 transition <?= $active==='budgets'?'bg-white/20 text-[#F5F5DC] shadow':'text-white hover:bg-white/15 hover:text-[#F5F5DC]' ?>" href="/orcamentos">Orçamentos</a>
        <a class="block rounded-xl px-4 py-3 transition <?= $active==='tasks'?'bg-white/20 text-[#F5F5DC] shadow':'text-white hover:bg-white/15 hover:text-[#F5F5DC]' ?>" href="/tarefas">Tarefas</a>
        <a class="block rounded-xl px-4 py-3 transition <?= $active==='finance'?'bg-white/20 text-[#F5F5DC] shadow':'text-white hover:bg-white/15 hover:text-[#F5F5DC]' ?>" href="/finance">Financeiro</a>
      </nav>
    </aside>

    <div class="flex-1 min-w-0 md:pl-72">
      <header class="sticky top-0 z-10 border-b border-zinc-200 bg-white/90 backdrop-blur">
        <div class="px-4 md:px-6 h-14 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="md:hidden rounded-lg bg-[#FE5516] px-2 py-1">
              <div class="brand-logo-box brand-logo-box--header-mobile">
              <img
                src="<?= htmlspecialchars((string)$sidebarLogo['src'], ENT_QUOTES, 'UTF-8') ?>"
                alt="Logo TRAXTER"
                class="brand-logo-img"
                loading="eager"
                decoding="async"
              />
              </div>
            </div>
            <div class="hidden md:block text-sm text-zinc-500">Ambiente</div>
            <div class="text-sm font-medium text-zinc-900"><?= htmlspecialchars((string)$tenantName, ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <div class="flex items-center gap-3">
            <button type="button" data-theme-toggle class="rounded-md border border-zinc-300 bg-white px-3 py-1.5 text-xs text-zinc-700 hover:bg-zinc-50 transition">Tema</button>
            <form method="POST" action="/logout">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars(CSRF::token(), ENT_QUOTES, 'UTF-8') ?>" />
              <button class="rounded-md bg-[#FE5516] px-3 py-1.5 text-xs font-semibold text-white hover:opacity-90 transition" type="submit">Sair</button>
            </form>
          </div>
        </div>
        <div class="md:hidden border-t border-zinc-200 bg-[#FE5516] px-4 py-2">
          <nav class="flex gap-2 overflow-x-auto text-sm">
            <a class="shrink-0 rounded-lg px-3 py-2 transition <?= $active==='dashboard'?'bg-white/20 text-[#FE5516]':'text-white hover:bg-white/15 hover:text-[#F5F5DC]' ?>" href="/dashboard">Painel</a>
            <a class="shrink-0 rounded-lg px-3 py-2 transition <?= $active==='clients'?'bg-white/20 text-[#F5F5DC]':'text-white hover:bg-white/15 hover:text-[#F5F5DC]' ?>" href="/clients">Clientes</a>
            <a class="shrink-0 rounded-lg px-3 py-2 transition <?= $active==='projects'?'bg-white/20 text-[#F5F5DC]':'text-white hover:bg-white/15 hover:text-[#F5F5DC]' ?>" href="/projetos">Projetos</a>
            <a class="shrink-0 rounded-lg px-3 py-2 transition <?= $active==='tasks'?'bg-white/20 text-[#F5F5DC]':'text-white hover:bg-white/15 hover:text-[#F5F5DC]' ?>" href="/tarefas">Tarefas</a>
            <a class="shrink-0 rounded-lg px-3 py-2 transition <?= $active==='finance'?'bg-white/20 text-[#F5F5DC]':'text-white hover:bg-white/15 hover:text-[#F5F5DC]' ?>" href="/finance">Financeiro</a>
          </nav>
        </div>
      </header>

      <main class="bg-[#f8f9fa] px-4 py-6 md:px-6">
        <?= $slot ?? '' ?>
      </main>
    </div>
  </div>
</div>

