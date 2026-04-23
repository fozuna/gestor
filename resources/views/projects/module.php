<?php
$active = 'projects';
ob_start();
?>
<?php
$slot = (function () use ($projects, $pagination, $filters, $clients, $editProject, $editInstallments, $ok, $error): string {
    $isEditing = is_array($editProject);
    $formAction = $isEditing ? '/projetos/' . (int)$editProject['id'] . '/atualizar' : '/projetos';
    $title = $isEditing ? 'Editar projeto' : 'Novo projeto';
    $statusLabels = [
        'active' => 'Em andamento',
        'paused' => 'Pausado',
        'done' => 'Concluído',
        'cancelled' => 'Cancelado',
        'inactive' => 'Inativo',
    ];
    $existingMonthly = [];
    if ($isEditing && is_array($editInstallments)) {
        foreach ($editInstallments as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (($row['installment_type'] ?? '') !== 'monthly') {
                continue;
            }
            $existingMonthly[] = [
                'due_date' => \App\Helpers\Security::formatDate((string)($row['due_date'] ?? '')),
                'amount' => number_format((float)($row['amount_total'] ?? 0), 2, ',', '.'),
            ];
        }
    }
    ob_start();
?>
  <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
    <div>
      <div class="flex items-center gap-2 text-sm text-sky-600">
        <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-sky-100">P</span>
        <span>Módulo de Projetos</span>
      </div>
      <div class="mt-2 text-2xl font-semibold text-zinc-900">Projetos</div>
      <div class="mt-1 text-sm text-zinc-500">Gestão independente de projetos com filtros, paginação e ações rápidas.</div>
    </div>
    <div class="flex gap-2">
      <a href="/dashboard" class="rounded-xl border border-zinc-200 bg-white px-4 py-2 text-sm text-zinc-700 transition hover:bg-zinc-50">Painel</a>
      <a href="/tarefas" class="rounded-xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-700">Abrir tarefas</a>
    </div>
  </div>

  <?php if (!empty($ok)): ?>
    <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= htmlspecialchars((string)$ok, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <div class="mt-6 grid gap-4 xl:grid-cols-[360px_minmax(0,1fr)]">
    <div class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm">
      <div class="text-sm font-semibold text-zinc-900"><?= $title ?></div>
      <form method="POST" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>" class="mt-4 space-y-4" data-loading-form data-installment-form>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Helpers\CSRF::token(), ENT_QUOTES, 'UTF-8') ?>" />
        <label class="block">
          <div class="text-xs text-zinc-500">Cliente</div>
          <select name="client_id" required class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm">
            <option value="">Selecione</option>
            <?php foreach ($clients as $client): ?>
              <option value="<?= (int)$client['id'] ?>" <?= $isEditing && (int)$editProject['client_id'] === (int)$client['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars((string)$client['name'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="block">
          <div class="text-xs text-zinc-500">Nome</div>
          <input name="name" required value="<?= htmlspecialchars((string)($editProject['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
        </label>
        <label class="block">
          <div class="text-xs text-zinc-500">Descrição</div>
          <textarea name="description" rows="4" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm"><?= htmlspecialchars((string)($editProject['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </label>
        <div class="grid gap-3 sm:grid-cols-2">
          <label class="block">
            <div class="text-xs text-zinc-500">Status</div>
            <select name="status" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm">
              <?php foreach (['active' => 'Em andamento', 'paused' => 'Pausado', 'done' => 'Concluído'] as $value => $label): ?>
                <option value="<?= $value ?>" <?= (($editProject['status'] ?? 'active') === $value) ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="block">
            <div class="text-xs text-zinc-500">Prazo final</div>
            <input name="due_date" value="<?= htmlspecialchars(\App\Helpers\Security::formatDate((string)($editProject['due_date'] ?? '')), ENT_QUOTES, 'UTF-8') ?>" data-date-mask placeholder="dd/mm/aaaa" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
          </label>
        </div>
        <label class="block">
          <div class="text-xs text-zinc-500">Data de início</div>
          <input name="start_date" value="<?= htmlspecialchars(\App\Helpers\Security::formatDate((string)($editProject['start_date'] ?? '')), ENT_QUOTES, 'UTF-8') ?>" data-date-mask placeholder="dd/mm/aaaa" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
        </label>

        <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4 space-y-3">
          <div class="flex items-center justify-between gap-2">
            <div class="text-sm font-semibold text-zinc-900">Financeiro do projeto</div>
            <?php if ($isEditing): ?>
              <span class="rounded-full border border-zinc-200 bg-white px-3 py-1 text-xs text-zinc-600"><?= (int)($editProject['installments_total'] ?? 0) ?> parcelas geradas</span>
            <?php endif; ?>
          </div>

          <label class="block">
            <div class="text-xs text-zinc-500">Valor contratado</div>
            <input name="contract_value" required value="<?= htmlspecialchars(number_format((float)($editProject['contract_value'] ?? 0), 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?>" data-money-mask placeholder="0,00" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
          </label>

          <div class="grid gap-3 sm:grid-cols-2">
            <label class="block">
              <div class="text-xs text-zinc-500">Valor de entrada</div>
              <input name="entry_amount" value="<?= htmlspecialchars(number_format((float)($editProject['entry_amount'] ?? 0), 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?>" data-money-mask placeholder="0,00" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
            </label>
            <label class="block">
              <div class="text-xs text-zinc-500">Quantidade de parcelas</div>
              <input name="installments_count" type="number" min="0" value="<?= htmlspecialchars((string)($editProject['installments_count'] ?? 1), ENT_QUOTES, 'UTF-8') ?>" data-installment-count class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
            </label>
          </div>

          <label class="block">
            <div class="text-xs text-zinc-500">Primeira parcela</div>
            <input name="first_installment_date" value="<?= htmlspecialchars(\App\Helpers\Security::formatDate((string)($editProject['first_installment_date'] ?? '')), ENT_QUOTES, 'UTF-8') ?>" data-date-mask placeholder="dd/mm/aaaa" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
          </label>

          <label class="block">
            <div class="text-xs text-zinc-500">Condições / forma de pagamento</div>
            <textarea name="payment_terms" rows="2" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm"><?= htmlspecialchars((string)($editProject['payment_terms'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
          </label>

          <div>
            <div class="mb-2 flex items-center justify-between gap-2">
              <div class="text-sm font-medium text-zinc-900">Parcelas mensais</div>
              <button type="button" data-generate-installments class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-xs font-medium text-zinc-700 transition hover:border-[#FE5516] hover:text-[#FE5516]">Gerar parcelas</button>
            </div>
            <div class="space-y-3" data-installments-container>
              <?php foreach ($existingMonthly as $i => $row): ?>
                <div class="grid gap-3 rounded-2xl border border-zinc-200 bg-white p-3 md:grid-cols-[1fr_1fr_auto]">
                  <label class="block">
                    <div class="text-xs text-zinc-500">Vencimento da parcela <?= (int)($i + 1) ?></div>
                    <input name="installments[<?= (int)$i ?>][due_date]" value="<?= htmlspecialchars((string)$row['due_date'], ENT_QUOTES, 'UTF-8') ?>" data-date-mask placeholder="dd/mm/aaaa" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
                  </label>
                  <label class="block">
                    <div class="text-xs text-zinc-500">Valor</div>
                    <input name="installments[<?= (int)$i ?>][amount]" value="<?= htmlspecialchars((string)$row['amount'], ENT_QUOTES, 'UTF-8') ?>" data-money-mask placeholder="0,00" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
                  </label>
                  <button type="button" data-remove-installment class="mt-5 rounded-xl border border-zinc-200 px-3 py-2 text-xs font-medium text-zinc-600 transition hover:border-red-300 hover:text-red-600">Remover</button>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="flex gap-2">
          <button type="submit" data-loading-button data-loading-text="Salvando..." class="flex-1 rounded-xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-700"><?= $isEditing ? 'Salvar alterações' : 'Criar projeto' ?></button>
          <?php if ($isEditing): ?>
            <a href="/projetos" class="rounded-xl border border-zinc-200 bg-white px-4 py-2 text-sm text-zinc-700 transition hover:bg-zinc-50">Cancelar</a>
          <?php endif; ?>
        </div>
        <div class="hidden rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-500" data-loading-indicator>Processando projeto...</div>
      </form>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <div class="text-sm font-semibold text-zinc-900">Listagem de projetos</div>
          <div class="mt-1 text-sm text-zinc-500"><?= (int)$pagination['total'] ?> registros encontrados</div>
        </div>
        <form method="GET" action="/projetos" class="grid gap-2 sm:grid-cols-3" data-loading-form>
          <input name="search" value="<?= htmlspecialchars((string)$filters['search'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Buscar por nome" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
          <select name="status" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm">
            <option value="">Todos os status</option>
            <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Em andamento</option>
            <option value="paused" <?= $filters['status'] === 'paused' ? 'selected' : '' ?>>Pausado</option>
            <option value="done" <?= $filters['status'] === 'done' ? 'selected' : '' ?>>Concluído</option>
          </select>
          <button type="submit" data-loading-button data-loading-text="Filtrando..." class="rounded-xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-700">Filtrar</button>
        </form>
      </div>

      <div class="mt-4 space-y-3">
        <?php if ($projects === []): ?>
          <div class="rounded-xl border border-dashed border-zinc-200 px-4 py-8 text-center text-sm text-zinc-500">Nenhum projeto encontrado para os filtros aplicados.</div>
        <?php else: ?>
          <?php foreach ($projects as $project): ?>
            <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4">
              <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div class="min-w-0">
                  <div class="truncate text-sm font-semibold text-zinc-900"><?= htmlspecialchars((string)$project['name'], ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars((string)$project['client_name'], ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="mt-3 flex flex-wrap gap-4 text-xs text-zinc-500">
                    <span>Status: <?= htmlspecialchars((string)($statusLabels[(string)$project['status']] ?? $project['status']), ENT_QUOTES, 'UTF-8') ?></span>
                    <span>Tarefas: <?= (int)$project['tasks_count'] ?></span>
                    <span>Pendente: R$ <?= number_format((float)$project['pending_amount'], 2, ',', '.') ?></span>
                  </div>
                </div>
                <div class="flex items-center gap-2">
                  <a href="/projetos/<?= (int)$project['id'] ?>?tab=details" title="Visualizar detalhes" aria-label="Visualizar detalhes" data-loading-link class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white text-sky-600 transition hover:bg-sky-50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                  </a>
                  <a href="/projetos/<?= (int)$project['id'] ?>?tab=dashboard" title="Abrir painel do projeto" aria-label="Abrir painel do projeto" data-loading-link class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white text-indigo-600 transition hover:bg-indigo-50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 13h8V3H3v10Zm10 8h8V11h-8v10ZM3 21h8v-6H3v6Zm10-10h8V3h-8v8Z"></path></svg>
                  </a>
                  <a href="/projetos?edit=<?= (int)$project['id'] ?>" title="Editar projeto" aria-label="Editar projeto" data-loading-link class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white text-amber-600 transition hover:bg-amber-50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="m16.5 3.5 4 4L7 21l-4 1 1-4L16.5 3.5Z"></path></svg>
                  </a>
                  <form method="POST" action="/projetos/<?= (int)$project['id'] ?>/excluir" onsubmit="return confirm('Excluir este projeto?');">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Helpers\CSRF::token(), ENT_QUOTES, 'UTF-8') ?>" />
                    <button type="submit" title="Excluir projeto" aria-label="Excluir projeto" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white text-red-600 transition hover:bg-red-50">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="m19 6-1 14H6L5 6"></path></svg>
                    </button>
                  </form>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <?php if (($pagination['pages'] ?? 1) > 1): ?>
        <div class="mt-4 flex flex-wrap gap-2">
          <?php for ($page = 1; $page <= (int)$pagination['pages']; $page++): ?>
            <a
              href="/projetos?<?= http_build_query(['search' => $filters['search'], 'status' => $filters['status'], 'page' => $page]) ?>"
              class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border px-3 text-sm <?= (int)$pagination['page'] === $page ? 'border-sky-600 bg-sky-600 text-white' : 'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50' ?>"
            ><?= $page ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
<?php
    return (string)ob_get_clean();
})();
require __DIR__ . '/../partials/app-shell.php';
$content = ob_get_clean();
require __DIR__ . '/../layouts/base.php';
