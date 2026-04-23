<?php
$active = 'budgets';
ob_start();
?>
<?php
$slot = (function () use ($budgets, $pagination, $filters, $clients, $editBudget, $ok, $error): string {
    $isEditing = is_array($editBudget);
    $formAction = $isEditing ? '/orcamentos/' . (int)$editBudget['id'] . '/atualizar' : '/orcamentos';
    $title = $isEditing ? 'Editar orçamento' : 'Novo orçamento';
    $statusLabels = [
        'rascunho' => 'Rascunho',
        'enviado' => 'Enviado',
        'aprovado' => 'Aprovado',
        'recusado' => 'Recusado',
    ];
    ob_start();
?>
  <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
    <div>
      <div class="flex items-center gap-2 text-sm text-violet-600">
        <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-violet-100">O</span>
        <span>Módulo de Orçamentos</span>
      </div>
      <div class="mt-2 text-2xl font-semibold text-zinc-900">Orçamentos</div>
      <div class="mt-1 text-sm text-zinc-500">Crie propostas comerciais independentes e aprove para virar projeto automaticamente.</div>
    </div>
    <div class="flex gap-2">
      <a href="/projetos" class="icon-btn icon-btn--md" title="Ver projetos" aria-label="Ver projetos">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 7h6l2 2h10v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z"/></svg>
        <span class="sr-only">Ver projetos</span>
      </a>
      <a href="/finance" class="icon-btn icon-btn--md icon-btn--primary" title="Abrir financeiro" aria-label="Abrir módulo financeiro">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        <span class="sr-only">Abrir financeiro</span>
      </a>
    </div>
  </div>

  <?php if (!empty($ok)): ?>
    <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= htmlspecialchars((string)$ok, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <div class="mt-6 grid gap-4 xl:grid-cols-[390px_minmax(0,1fr)]">
    <div class="rounded-2xl border border-violet-100 bg-white p-5 shadow-sm">
      <div class="text-sm font-semibold text-zinc-900"><?= $title ?></div>
      <form method="POST" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>" class="mt-4 space-y-4" data-loading-form>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Helpers\CSRF::token(), ENT_QUOTES, 'UTF-8') ?>" />
        <label class="block">
          <div class="text-xs text-zinc-500">Cliente</div>
          <select name="client_id" required class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm">
            <option value="">Selecione</option>
            <?php foreach ($clients as $client): ?>
              <option value="<?= (int)$client['id'] ?>" <?= $isEditing && (int)$editBudget['client_id'] === (int)$client['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars((string)$client['name'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="block">
          <div class="text-xs text-zinc-500">Nome da proposta</div>
          <input name="nome_proposta" required value="<?= htmlspecialchars((string)($editBudget['nome_proposta'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
        </label>
        <label class="block">
          <div class="text-xs text-zinc-500">Descrição</div>
          <textarea name="descricao" rows="4" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm"><?= htmlspecialchars((string)($editBudget['descricao'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </label>

        <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4 space-y-3">
          <div class="text-sm font-semibold text-zinc-900">Bloco financeiro</div>
          <label class="block">
            <div class="text-xs text-zinc-500">Valor total</div>
            <input name="valor_total" required value="<?= htmlspecialchars(number_format((float)($editBudget['valor_total'] ?? 0), 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?>" data-money-mask placeholder="0,00" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
          </label>
          <div class="grid gap-3 sm:grid-cols-2">
            <label class="block">
              <div class="text-xs text-zinc-500">Entrada (opcional)</div>
              <input name="valor_entrada" value="<?= htmlspecialchars(number_format((float)($editBudget['valor_entrada'] ?? 0), 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?>" data-money-mask placeholder="0,00" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
            </label>
            <label class="block">
              <div class="text-xs text-zinc-500">Quantidade de parcelas</div>
              <input name="quantidade_parcelas" type="number" min="0" value="<?= htmlspecialchars((string)($editBudget['quantidade_parcelas'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
            </label>
          </div>
          <div class="grid gap-3 sm:grid-cols-2">
            <label class="block">
              <div class="text-xs text-zinc-500">Valor da parcela</div>
              <input name="valor_parcela" value="<?= htmlspecialchars(number_format((float)($editBudget['valor_parcela'] ?? 0), 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?>" data-money-mask placeholder="0,00" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
            </label>
            <label class="block">
              <div class="text-xs text-zinc-500">Primeira parcela</div>
              <input name="data_primeira_parcela" value="<?= htmlspecialchars(\App\Helpers\Security::formatDate((string)($editBudget['data_primeira_parcela'] ?? '')), ENT_QUOTES, 'UTF-8') ?>" data-date-mask placeholder="dd/mm/aaaa" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
            </label>
          </div>
          <label class="block">
            <div class="text-xs text-zinc-500">Condições de pagamento</div>
            <textarea name="condicoes_pagamento" rows="2" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm"><?= htmlspecialchars((string)($editBudget['condicoes_pagamento'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
          </label>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
          <label class="block">
            <div class="text-xs text-zinc-500">Status</div>
            <select name="status" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm">
              <?php foreach ($statusLabels as $value => $label): ?>
                <option value="<?= $value ?>" <?= (($editBudget['status'] ?? 'rascunho') === $value) ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="block">
            <div class="text-xs text-zinc-500">Data de validade</div>
            <input name="data_validade" value="<?= htmlspecialchars(\App\Helpers\Security::formatDate((string)($editBudget['data_validade'] ?? '')), ENT_QUOTES, 'UTF-8') ?>" data-date-mask placeholder="dd/mm/aaaa" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
          </label>
        </div>

        <div class="flex gap-2">
          <button type="submit" data-loading-button data-loading-text="Salvando..." class="flex-1 rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-violet-700"><?= $isEditing ? 'Salvar alterações' : 'Criar orçamento' ?></button>
          <?php if ($isEditing): ?>
            <a href="/orcamentos" class="rounded-xl border border-zinc-200 bg-white px-4 py-2 text-sm text-zinc-700 transition hover:bg-zinc-50">Cancelar</a>
          <?php endif; ?>
        </div>
        <div class="hidden rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-500" data-loading-indicator>Processando orçamento...</div>
      </form>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <div class="text-sm font-semibold text-zinc-900">Listagem de orçamentos</div>
          <div class="mt-1 text-sm text-zinc-500"><?= (int)$pagination['total'] ?> registros encontrados</div>
        </div>
        <form method="GET" action="/orcamentos" class="grid gap-2 sm:grid-cols-3" data-loading-form>
          <input name="search" value="<?= htmlspecialchars((string)$filters['search'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Buscar proposta" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
          <select name="status" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm">
            <option value="">Todos os status</option>
            <?php foreach ($statusLabels as $value => $label): ?>
              <option value="<?= $value ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" data-loading-button data-loading-text="Filtrando..." class="icon-btn icon-btn--md icon-btn--primary" title="Aplicar filtros" aria-label="Aplicar filtros de orçamento">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 6h18"/><path d="M6 12h12"/><path d="M10 18h4"/></svg>
            <span class="sr-only">Filtrar</span>
          </button>
        </form>
      </div>

      <div class="mt-4 space-y-3">
        <?php if ($budgets === []): ?>
          <div class="rounded-xl border border-dashed border-zinc-200 px-4 py-8 text-center text-sm text-zinc-500">Nenhum orçamento encontrado para os filtros aplicados.</div>
        <?php else: ?>
          <?php foreach ($budgets as $budget): ?>
            <?php
            $status = (string)$budget['status'];
            $badge = $status === 'aprovado'
              ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
              : ($status === 'recusado'
                ? 'border-red-200 bg-red-50 text-red-700'
                : ($status === 'enviado' ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-zinc-200 bg-zinc-100 text-zinc-700'));
            ?>
            <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4">
              <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div class="min-w-0">
                  <div class="truncate text-sm font-semibold text-zinc-900"><?= htmlspecialchars((string)$budget['nome_proposta'], ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars((string)$budget['client_name'], ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="mt-3 flex flex-wrap gap-4 text-xs text-zinc-500">
                    <span>Total: R$ <?= number_format((float)$budget['valor_total'], 2, ',', '.') ?></span>
                    <span>Parcelas: <?= (int)$budget['quantidade_parcelas'] ?></span>
                    <?php if ((int)($budget['projeto_id'] ?? 0) > 0): ?>
                      <span>Projeto: #<?= (int)$budget['projeto_id'] ?></span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                  <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium <?= $badge ?>">
                    <?= htmlspecialchars((string)($statusLabels[$status] ?? $status), ENT_QUOTES, 'UTF-8') ?>
                  </span>
                  <a href="/orcamentos?edit=<?= (int)$budget['id'] ?>" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white text-violet-600 transition hover:bg-violet-50" title="Editar orçamento" aria-label="Editar orçamento">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="m16.5 3.5 4 4L7 21l-4 1 1-4L16.5 3.5Z"></path></svg>
                  </a>
                  <a href="/orcamentos/<?= (int)$budget['id'] ?>/proposta" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white text-sky-600 transition hover:bg-sky-50" title="Gerar proposta" aria-label="Gerar proposta">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 3v4a1 1 0 0 0 1 1h4"></path><path d="M5 13V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2h-5"></path><path d="M3 17h9"></path><path d="m8 13 4 4-4 4"></path></svg>
                  </a>
                  <form method="POST" action="/orcamentos/<?= (int)$budget['id'] ?>/status" class="flex items-center gap-2">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Helpers\CSRF::token(), ENT_QUOTES, 'UTF-8') ?>" />
                    <select name="status" class="rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs">
                      <?php foreach ($statusLabels as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= $label ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" class="icon-btn icon-btn--sm icon-btn--primary" title="Atualizar status" aria-label="Atualizar status do orçamento">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m5 12 5 5 9-9"/></svg>
                      <span class="sr-only">Atualizar</span>
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
            <a href="/orcamentos?<?= http_build_query(['search' => $filters['search'], 'status' => $filters['status'], 'page' => $page]) ?>" class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border px-3 text-sm <?= (int)$pagination['page'] === $page ? 'border-violet-600 bg-violet-600 text-white' : 'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50' ?>"><?= $page ?></a>
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
