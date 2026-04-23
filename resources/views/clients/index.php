<?php
$active = 'clients';
ob_start();
?>
<?php
$slot = (function () use ($clients, $ok, $error, $editClient): string {
    ob_start();
    $isEditing = is_array($editClient);
    $formAction = $isEditing ? '/clients/' . (int)$editClient['id'] . '/atualizar' : '/clients';
    $formTitle = $isEditing ? 'Editar cliente' : 'Novo cliente';
?>
  <div class="flex items-end justify-between gap-4">
    <div>
      <div class="text-sm text-zinc-500">Relacionamento</div>
      <div class="mt-1 text-2xl font-semibold text-zinc-900">Clientes</div>
    </div>
    <a href="/projetos" class="rounded-xl border border-zinc-200 bg-white px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-50 transition">Ver projetos</a>
  </div>

  <?php if (!empty($ok)): ?>
    <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= htmlspecialchars((string)$ok, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <div class="mt-6 grid gap-4 xl:grid-cols-[1.05fr_1.4fr]">
    <div id="new-client" class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
      <div class="text-sm font-medium text-zinc-900"><?= $formTitle ?></div>
      <form method="POST" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>" class="mt-4 space-y-3" data-loading-form>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Helpers\CSRF::token(), ENT_QUOTES, 'UTF-8') ?>" />
        <label class="block">
          <div class="text-xs text-zinc-500">Nome</div>
          <input name="name" required value="<?= htmlspecialchars((string)($editClient['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30" />
        </label>
        <label class="block">
          <div class="text-xs text-zinc-500">E-mail</div>
          <input name="email" type="email" value="<?= htmlspecialchars((string)($editClient['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30" />
        </label>
        <label class="block">
          <div class="text-xs text-zinc-500">Telefone</div>
          <input name="phone" value="<?= htmlspecialchars((string)($editClient['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-phone-mask class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30" />
        </label>
        <label class="block">
          <div class="text-xs text-zinc-500">Status</div>
          <select name="status" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30">
            <option value="active" <?= (($editClient['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Ativo</option>
            <option value="inactive" <?= (($editClient['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inativo</option>
          </select>
        </label>
        <label class="block">
          <div class="text-xs text-zinc-500">Notas</div>
          <textarea name="notes" rows="3" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30"><?= htmlspecialchars((string)($editClient['notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </label>
        <div class="flex gap-2">
          <button type="submit" data-loading-button data-loading-text="Salvando..." class="flex-1 rounded-xl bg-[#FE5516] px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90 active:scale-[0.99] transition"><?= $isEditing ? 'Salvar alterações' : 'Criar cliente' ?></button>
          <?php if ($isEditing): ?>
            <a href="/clients" class="rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 active:scale-[0.99] transition">Cancelar</a>
          <?php endif; ?>
        </div>
        <div class="hidden rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-500" data-loading-indicator>Processando cliente...</div>
      </form>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
      <div class="flex items-center justify-between gap-3">
        <div class="text-sm font-medium text-zinc-900">Base de clientes</div>
        <div class="text-xs text-zinc-500"><?= count($clients) ?> cadastrados</div>
      </div>
      <div class="mt-4 overflow-hidden rounded-2xl border border-zinc-200">
        <table class="min-w-full divide-y divide-zinc-200 text-sm">
          <thead class="bg-zinc-50">
            <tr class="text-left text-zinc-500">
              <th class="px-4 py-3 font-medium">Nome</th>
              <th class="px-4 py-3 font-medium">Contato</th>
              <th class="px-4 py-3 font-medium">Status</th>
              <th class="px-4 py-3 font-medium">Projetos</th>
              <th class="px-4 py-3 font-medium">Ações</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-zinc-200">
            <?php if ($clients === []): ?>
              <tr>
                <td colspan="5" class="px-4 py-6 text-center text-zinc-500">Nenhum cliente criado ainda.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($clients as $client): ?>
                <tr class="bg-white">
                  <td class="px-4 py-3 font-medium text-zinc-900"><?= htmlspecialchars((string)$client['name'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="px-4 py-3 text-zinc-500"><?= htmlspecialchars((string)($client['email'] ?: $client['phone'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="px-4 py-3">
                    <span class="rounded-full border px-2 py-1 text-xs <?= $client['status'] === 'active' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-zinc-200 bg-zinc-50 text-zinc-500' ?>">
                      <?= $client['status'] === 'active' ? 'Ativo' : 'Inativo' ?>
                    </span>
                  </td>
                  <td class="px-4 py-3 text-zinc-700"><?= (int)$client['projects_count'] ?></td>
                  <td class="px-4 py-3">
                    <div class="flex items-center justify-end gap-2">
                      <a
                        href="/clients/profile?id=<?= (int)$client['id'] ?>"
                        data-loading-link
                        title="Abrir perfil financeiro"
                        aria-label="Abrir perfil financeiro do cliente <?= htmlspecialchars((string)$client['name'], ENT_QUOTES, 'UTF-8') ?>"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white text-emerald-600 hover:border-emerald-300 hover:bg-emerald-50 active:scale-[0.98] transition"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                      </a>
                      <a
                        href="/clients?edit=<?= (int)$client['id'] ?>#new-client"
                        data-loading-link
                        title="Editar cliente"
                        aria-label="Editar cliente <?= htmlspecialchars((string)$client['name'], ENT_QUOTES, 'UTF-8') ?>"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white text-amber-600 hover:border-amber-300 hover:bg-amber-50 active:scale-[0.98] transition"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="m16.5 3.5 4 4L7 21l-4 1 1-4L16.5 3.5Z"></path></svg>
                      </a>
                      <button
                        type="button"
                        data-client-delete-open
                        data-client-id="<?= (int)$client['id'] ?>"
                        data-client-name="<?= htmlspecialchars((string)$client['name'], ENT_QUOTES, 'UTF-8') ?>"
                        title="Excluir cliente"
                        aria-label="Excluir cliente <?= htmlspecialchars((string)$client['name'], ENT_QUOTES, 'UTF-8') ?>"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white text-red-600 hover:border-red-300 hover:bg-red-50 active:scale-[0.98] transition"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="m19 6-1 14H6L5 6"></path></svg>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div
    data-client-delete-overlay
    aria-hidden="true"
    style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(24,24,27,.45); z-index:9998;"
  ></div>
  <div
    data-client-delete-modal
    role="dialog"
    aria-modal="true"
    aria-labelledby="client-delete-title"
    aria-hidden="true"
    class="w-full max-w-md rounded-2xl border border-zinc-200 bg-white shadow-2xl"
    style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); z-index:9999; max-width:min(92vw, 480px);"
  >
    <div class="p-5">
      <div id="client-delete-title" class="text-lg font-semibold text-zinc-900">Confirmar exclusão</div>
      <div class="mt-2 text-sm text-zinc-600">
        Deseja realmente excluir o cliente <span class="font-semibold text-zinc-900" data-client-delete-name></span>?
      </div>
      <div class="mt-2 text-xs text-zinc-500">A exclusão será bloqueada se houver projetos ativos vinculados.</div>
    </div>
    <div class="flex items-center justify-end gap-2 border-t border-zinc-200 px-5 py-4">
      <button type="button" data-client-delete-cancel class="rounded-xl border border-zinc-200 bg-white px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-50 active:scale-[0.99] transition">Cancelar</button>
      <form method="POST" data-client-delete-form data-loading-form>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Helpers\CSRF::token(), ENT_QUOTES, 'UTF-8') ?>" />
        <button type="submit" data-loading-button data-loading-text="Excluindo..." class="btn-danger rounded-xl px-4 py-2 text-sm font-semibold active:scale-[0.99] transition">Confirmar exclusão</button>
      </form>
    </div>
  </div>

  <script>
    (function () {
      const modal = document.querySelector('[data-client-delete-modal]');
      const overlay = document.querySelector('[data-client-delete-overlay]');
      const nameEl = document.querySelector('[data-client-delete-name]');
      const form = document.querySelector('[data-client-delete-form]');
      const cancel = document.querySelector('[data-client-delete-cancel]');
      const openers = document.querySelectorAll('[data-client-delete-open]');
      if (!modal || !overlay || !nameEl || !form || !cancel || openers.length === 0) return;

      const openModal = () => {
        overlay.style.display = 'block';
        modal.style.display = 'block';
        modal.setAttribute('aria-hidden', 'false');
        overlay.setAttribute('aria-hidden', 'false');
      };

      const closeModal = () => {
        overlay.style.display = 'none';
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        overlay.setAttribute('aria-hidden', 'true');
      };

      openers.forEach((btn) => {
        btn.addEventListener('click', () => {
          const id = btn.getAttribute('data-client-id') || '';
          const name = btn.getAttribute('data-client-name') || 'cliente';
          nameEl.textContent = name;
          form.setAttribute('action', '/clients/' + id + '/excluir');
          openModal();
        });
      });

      cancel.addEventListener('click', () => {
        closeModal();
      });

      overlay.addEventListener('click', closeModal);

      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') {
          closeModal();
        }
      });
    })();
  </script>
<?php
    return (string)ob_get_clean();
})();
require __DIR__ . '/../partials/app-shell.php';
$content = ob_get_clean();
require __DIR__ . '/../layouts/base.php';
