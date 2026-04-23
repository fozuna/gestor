<?php
$active = 'finance';
ob_start();
?>
<?php
$slot = (function () use ($summary, $entries, $projects, $error, $ok, $filter, $anticipation, $anticipationSimulation, $anticipationForm): string {
    ob_start();
    $anticipation = is_array($anticipation ?? null) ? $anticipation : [];
    $anticipationSimulation = is_array($anticipationSimulation ?? null) ? $anticipationSimulation : null;
    $anticipationForm = is_array($anticipationForm ?? null) ? $anticipationForm : [];
    $anticipationClients = is_array($anticipation['clients'] ?? null) ? $anticipation['clients'] : [];
    $anticipationProjects = is_array($anticipation['projects'] ?? null) ? $anticipation['projects'] : [];
    $anticipationCandidates = is_array($anticipation['candidates'] ?? null) ? $anticipation['candidates'] : [];
    $anticipationHistory = is_array($anticipation['history'] ?? null) ? $anticipation['history'] : [];
    $selectedAnticipationInvoiceIds = array_map('intval', is_array($anticipationForm['invoice_ids'] ?? null) ? $anticipationForm['invoice_ids'] : []);
?>
  <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
    <div>
      <div class="text-sm text-zinc-500">Financeiro</div>
      <div class="mt-1 text-2xl font-semibold text-zinc-900">Fluxo de caixa por parcelas</div>
    </div>
    <form method="GET" action="/finance" class="grid gap-2 sm:grid-cols-3">
      <label class="text-xs text-zinc-500">
        Mês
        <input type="number" min="1" max="12" required name="month" value="<?= htmlspecialchars((string)$filter['month'], ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30" />
      </label>
      <label class="text-xs text-zinc-500">
        Ano
        <input type="number" min="2000" max="2100" required name="year" value="<?= htmlspecialchars((string)$filter['year'], ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30" />
      </label>
      <div class="flex items-end">
        <button type="submit" class="icon-btn icon-btn--md icon-btn--primary" title="Filtrar período" aria-label="Filtrar período financeiro">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 6h18"/><path d="M6 12h12"/><path d="M10 18h4"/></svg>
          <span class="sr-only">Filtrar</span>
        </button>
      </div>
    </form>
  </div>

  <?php if ((float)$summary['overdue'] > 0): ?>
    <div class="mt-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      <span class="font-semibold">⚠ Atenção:</span>
      Existem parcelas vencidas somando R$ <?= number_format((float)$summary['overdue'], 2, ',', '.') ?> no período filtrado.
    </div>
  <?php endif; ?>

  <?php if (!empty($ok)): ?>
    <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= htmlspecialchars((string)$ok, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <div class="mt-6 grid gap-4 md:grid-cols-5">
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
      <div class="text-xs uppercase tracking-wide text-zinc-500">Previsto</div>
      <div class="mt-3 text-2xl font-semibold text-zinc-900">R$ <?= number_format((float)$summary['income'], 2, ',', '.') ?></div>
    </div>
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
      <div class="text-xs uppercase tracking-wide text-zinc-500">Recebido</div>
      <div class="mt-3 text-2xl font-semibold text-emerald-600">R$ <?= number_format((float)$summary['paid'], 2, ',', '.') ?></div>
    </div>
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
      <div class="text-xs uppercase tracking-wide text-zinc-500">Pendente</div>
      <div class="mt-3 text-2xl font-semibold text-amber-600">R$ <?= number_format((float)$summary['pending'], 2, ',', '.') ?></div>
    </div>
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
      <div class="text-xs uppercase tracking-wide text-zinc-500">Vencido</div>
      <div class="mt-3 text-2xl font-semibold text-red-600">R$ <?= number_format((float)$summary['overdue'], 2, ',', '.') ?></div>
    </div>
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
      <div class="text-xs uppercase tracking-wide text-zinc-500">Saldo</div>
      <div class="mt-3 text-2xl font-semibold text-[#FE5516]">R$ <?= number_format((float)$summary['balance'], 2, ',', '.') ?></div>
    </div>
  </div>

  <div class="mt-6 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <div class="text-sm font-medium text-zinc-900">Entrada e parcelamento (projeto)</div>
        <div class="mt-1 text-xs text-zinc-500">Gera automaticamente a entrada (opcional) e as parcelas no financeiro do projeto.</div>
      </div>
      <span class="text-xs text-zinc-500">Validação em tempo real</span>
    </div>

    <form method="POST" action="/finance/installments/plan" class="mt-4 grid gap-3 md:grid-cols-3" data-finance-plan-form data-loading-form>
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Helpers\CSRF::token(), ENT_QUOTES, 'UTF-8') ?>" />
      <input type="hidden" name="redirect_month" value="<?= (int)$filter['month'] ?>" />
      <input type="hidden" name="redirect_year" value="<?= (int)$filter['year'] ?>" />
      <input type="hidden" name="client_id" value="" data-finance-client-id />

      <label class="block md:col-span-3">
        <div class="text-xs text-zinc-500">Projeto</div>
        <select name="project_id" required class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" data-finance-project>
          <option value="">Selecione</option>
          <?php foreach ($projects as $p): ?>
            <option value="<?= (int)$p['id'] ?>" data-client-id="<?= (int)$p['client_id'] ?>" data-total="<?= htmlspecialchars(number_format((float)$p['contract_value'], 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars((string)$p['name'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string)$p['client_name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="block">
        <div class="text-xs text-zinc-500">Valor total</div>
        <input name="valor_total" required data-money-mask placeholder="0,00" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" data-finance-total />
      </label>
      <label class="block">
        <div class="text-xs text-zinc-500">Valor de entrada (opcional)</div>
        <input name="valor_entrada" data-money-mask placeholder="0,00" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" data-finance-entry />
      </label>
      <label class="block">
        <div class="text-xs text-zinc-500">Data da entrada</div>
        <input name="data_entrada" data-date-mask placeholder="dd/mm/aaaa" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" data-finance-entry-date />
      </label>

      <label class="block">
        <div class="text-xs text-zinc-500">Quantidade de parcelas</div>
        <input name="quantidade_parcelas" type="number" min="0" value="1" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" data-finance-count />
      </label>
      <label class="block">
        <div class="text-xs text-zinc-500">Data da primeira parcela</div>
        <input name="data_primeira_parcela" data-date-mask placeholder="dd/mm/aaaa" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" data-finance-first-date />
      </label>
      <label class="block">
        <div class="text-xs text-zinc-500">Saldo restante</div>
        <input readonly class="mt-1 w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm" value="R$ 0,00" data-finance-remaining />
      </label>

      <label class="block md:col-span-2">
        <div class="text-xs text-zinc-500">Valor da parcela</div>
        <input readonly class="mt-1 w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm" value="R$ 0,00" data-finance-installment />
      </label>
      <label class="block">
        <div class="text-xs text-zinc-500">Condições / formas de pagamento</div>
        <input name="formas_pagamento" placeholder="Ex.: PIX, boleto, transferência..." class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
      </label>

      <div class="md:col-span-3">
        <div class="hidden rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" data-finance-plan-error></div>
      </div>

      <div class="md:col-span-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <button type="submit" data-loading-button data-loading-text="Gerando..." class="icon-btn icon-btn--md icon-btn--primary" title="Gerar parcelas no financeiro" aria-label="Gerar parcelas no financeiro">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 5v14M5 12h14"/></svg>
          <span class="sr-only">Gerar parcelas no financeiro</span>
        </button>
        <div class="hidden text-xs text-zinc-500" data-loading-indicator>Validando e gerando parcelas...</div>
      </div>
    </form>
  </div>

  <div class="mt-6 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
    <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
      <div>
        <div class="text-sm font-medium text-zinc-900">Antecipação de parcelas</div>
        <div class="mt-1 text-xs text-zinc-500">Seleciona parcelas futuras por cliente ou projeto, preserva o vencimento original e registra a data efetiva do pagamento antecipado.</div>
      </div>
      <span class="rounded-full border border-violet-200 bg-violet-50 px-3 py-1 text-xs font-medium text-violet-700">Histórico e recibo automático</span>
    </div>

    <form method="GET" action="/finance" class="mt-4 grid gap-3 md:grid-cols-4">
      <input type="hidden" name="month" value="<?= (int)$filter['month'] ?>" />
      <input type="hidden" name="year" value="<?= (int)$filter['year'] ?>" />

      <label class="block md:col-span-2">
        <div class="text-xs text-zinc-500">Cliente</div>
        <select name="anticipation_client_id" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm">
          <option value="">Selecione</option>
          <?php foreach ($anticipationClients as $client): ?>
            <option value="<?= (int)$client['id'] ?>" <?= (int)($anticipationForm['client_id'] ?? 0) === (int)$client['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars((string)$client['name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="block md:col-span-2">
        <div class="text-xs text-zinc-500">Projeto</div>
        <select name="anticipation_project_id" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm">
          <option value="">Todos os projetos</option>
          <?php foreach ($anticipationProjects as $project): ?>
            <option value="<?= (int)$project['id'] ?>" <?= (int)($anticipationForm['project_id'] ?? 0) === (int)$project['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars((string)$project['name'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string)$project['client_name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <div class="md:col-span-4 flex flex-col gap-2 sm:flex-row">
        <button type="submit" class="icon-btn icon-btn--md icon-btn--primary" title="Listar parcelas elegíveis" aria-label="Listar parcelas elegíveis">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/></svg>
          <span class="sr-only">Listar parcelas elegíveis</span>
        </button>
        <a href="/finance?month=<?= (int)$filter['month'] ?>&year=<?= (int)$filter['year'] ?>" class="icon-btn icon-btn--md" title="Limpar filtros da antecipação" aria-label="Limpar filtros da antecipação">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 6l12 12"/><path d="M18 6 6 18"/></svg>
          <span class="sr-only">Limpar filtros da antecipação</span>
        </a>
      </div>
    </form>

    <?php if ((int)($anticipationForm['client_id'] ?? 0) > 0 || (int)($anticipationForm['project_id'] ?? 0) > 0): ?>
      <?php if ($anticipationCandidates === []): ?>
        <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600">
          Nenhuma parcela futura elegível foi encontrada para os filtros informados.
        </div>
      <?php else: ?>
        <form method="POST" action="/finance/installments/anticipation/simulate" class="mt-4 space-y-4">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Helpers\CSRF::token(), ENT_QUOTES, 'UTF-8') ?>" />
          <input type="hidden" name="redirect_month" value="<?= (int)$filter['month'] ?>" />
          <input type="hidden" name="redirect_year" value="<?= (int)$filter['year'] ?>" />
          <input type="hidden" name="client_id" value="<?= (int)($anticipationForm['client_id'] ?? 0) ?>" />
          <input type="hidden" name="project_id" value="<?= (int)($anticipationForm['project_id'] ?? 0) ?>" />

          <div class="overflow-hidden rounded-2xl border border-zinc-200">
            <table class="min-w-full divide-y divide-zinc-200 text-sm">
              <thead class="bg-zinc-50">
                <tr class="text-left text-zinc-500">
                  <th class="px-4 py-3 font-medium">Selecionar</th>
                  <th class="px-4 py-3 font-medium">Parcela</th>
                  <th class="px-4 py-3 font-medium">Cliente / Projeto</th>
                  <th class="px-4 py-3 font-medium">Vencimento original</th>
                  <th class="px-4 py-3 font-medium">Saldo atual</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-zinc-200">
                <?php foreach ($anticipationCandidates as $candidate): ?>
                  <?php $candidateRemaining = max(0, round((float)$candidate['amount_total'] - (float)$candidate['amount_paid'], 2)); ?>
                  <tr>
                    <td class="px-4 py-4">
                      <input
                        type="checkbox"
                        name="invoice_ids[]"
                        value="<?= (int)$candidate['id'] ?>"
                        class="h-4 w-4 rounded border-zinc-300 text-[#FE5516] focus:ring-[#FE5516]/30"
                        <?= in_array((int)$candidate['id'], $selectedAnticipationInvoiceIds, true) ? 'checked' : '' ?>
                      />
                    </td>
                    <td class="px-4 py-4">
                      <div class="font-medium text-zinc-900"><?= htmlspecialchars((string)$candidate['description'], ENT_QUOTES, 'UTF-8') ?></div>
                      <div class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars((string)$candidate['code'], ENT_QUOTES, 'UTF-8') ?></div>
                    </td>
                    <td class="px-4 py-4 text-zinc-600">
                      <div><?= htmlspecialchars((string)$candidate['client_name'], ENT_QUOTES, 'UTF-8') ?></div>
                      <div class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars((string)($candidate['project_name'] ?: 'Sem projeto'), ENT_QUOTES, 'UTF-8') ?></div>
                    </td>
                    <td class="px-4 py-4 text-zinc-600"><?= htmlspecialchars(\App\Helpers\Security::formatDate((string)$candidate['due_date']), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-4 font-semibold text-zinc-900">R$ <?= number_format($candidateRemaining, 2, ',', '.') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <label class="block">
              <div class="text-xs text-zinc-500">Data efetiva do pagamento</div>
              <input type="date" name="payment_date" value="<?= htmlspecialchars((string)($anticipationForm['payment_date'] ?? date('Y-m-d')), ENT_QUOTES, 'UTF-8') ?>" required class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
            </label>
            <label class="block">
              <div class="text-xs text-zinc-500">Método de pagamento</div>
              <select name="payment_method" required class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm">
                <?php foreach (['pix' => 'PIX', 'boleto' => 'Boleto', 'transferencia' => 'Transferência', 'cartao' => 'Cartão', 'dinheiro' => 'Dinheiro', 'outro' => 'Outro'] as $value => $label): ?>
                  <option value="<?= $value ?>" <?= (string)($anticipationForm['payment_method'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="block">
              <div class="text-xs text-zinc-500">Regra financeira</div>
              <select name="adjustment_mode" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm">
                <option value="none" <?= (string)($anticipationForm['adjustment_mode'] ?? 'none') === 'none' ? 'selected' : '' ?>>Sem ajuste</option>
                <option value="discount" <?= (string)($anticipationForm['adjustment_mode'] ?? '') === 'discount' ? 'selected' : '' ?>>Desconto</option>
                <option value="interest" <?= (string)($anticipationForm['adjustment_mode'] ?? '') === 'interest' ? 'selected' : '' ?>>Acréscimo</option>
              </select>
            </label>
            <label class="block">
              <div class="text-xs text-zinc-500">Taxa (%)</div>
              <input name="adjustment_rate" value="<?= htmlspecialchars((string)($anticipationForm['adjustment_rate'] ?? '0,00'), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
            </label>
            <label class="block md:col-span-2 xl:col-span-1">
              <div class="text-xs text-zinc-500">Observação</div>
              <input name="note" maxlength="255" value="<?= htmlspecialchars((string)($anticipationForm['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
            </label>
          </div>

          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-xs text-zinc-500">A simulação calcula o total a pagar sem alterar os vencimentos originais das parcelas.</div>
            <button type="submit" class="icon-btn icon-btn--md icon-btn--primary" title="Simular antecipação" aria-label="Simular antecipação">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 2v20"/><path d="M2 12h20"/></svg>
              <span class="sr-only">Simular antecipação</span>
            </button>
          </div>
        </form>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($anticipationSimulation !== null): ?>
      <div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <div class="text-sm font-semibold text-emerald-800">Simulação confirmada para revisão</div>
            <div class="mt-1 text-xs text-emerald-700">Confira valores, parcelas e data efetiva antes de processar a quitação antecipada.</div>
          </div>
          <div class="grid gap-2 sm:grid-cols-3">
            <div class="rounded-xl bg-white px-4 py-3">
              <div class="text-[11px] uppercase tracking-wide text-zinc-500">Base selecionada</div>
              <div class="mt-1 text-lg font-semibold text-zinc-900">R$ <?= number_format((float)$anticipationSimulation['base_amount'], 2, ',', '.') ?></div>
            </div>
            <div class="rounded-xl bg-white px-4 py-3">
              <div class="text-[11px] uppercase tracking-wide text-zinc-500">Ajuste</div>
              <div class="mt-1 text-lg font-semibold <?= (float)$anticipationSimulation['adjustment_amount'] < 0 ? 'text-emerald-700' : ((float)$anticipationSimulation['adjustment_amount'] > 0 ? 'text-amber-700' : 'text-zinc-900') ?>">
                R$ <?= number_format((float)$anticipationSimulation['adjustment_amount'], 2, ',', '.') ?>
              </div>
            </div>
            <div class="rounded-xl bg-white px-4 py-3">
              <div class="text-[11px] uppercase tracking-wide text-zinc-500">Total a pagar</div>
              <div class="mt-1 text-lg font-semibold text-zinc-900">R$ <?= number_format((float)$anticipationSimulation['total_amount'], 2, ',', '.') ?></div>
            </div>
          </div>
        </div>

        <div class="mt-4 overflow-hidden rounded-2xl border border-emerald-200 bg-white">
          <table class="min-w-full divide-y divide-zinc-200 text-sm">
            <thead class="bg-zinc-50">
              <tr class="text-left text-zinc-500">
                <th class="px-4 py-3 font-medium">Parcela</th>
                <th class="px-4 py-3 font-medium">Vencimento original</th>
                <th class="px-4 py-3 font-medium">Valor liquidado</th>
                <th class="px-4 py-3 font-medium">Ajuste</th>
                <th class="px-4 py-3 font-medium">Pagamento efetivo</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200">
              <?php foreach (($anticipationSimulation['items'] ?? []) as $item): ?>
                <tr>
                  <td class="px-4 py-4">
                    <div class="font-medium text-zinc-900"><?= htmlspecialchars((string)$item['description'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars((string)$item['code'], ENT_QUOTES, 'UTF-8') ?></div>
                  </td>
                  <td class="px-4 py-4 text-zinc-600"><?= htmlspecialchars(\App\Helpers\Security::formatDate((string)$item['due_date']), ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="px-4 py-4 font-semibold text-zinc-900">R$ <?= number_format((float)$item['settled_amount'], 2, ',', '.') ?></td>
                  <td class="px-4 py-4 <?= (float)$item['adjustment_amount'] < 0 ? 'text-emerald-700' : ((float)$item['adjustment_amount'] > 0 ? 'text-amber-700' : 'text-zinc-600') ?>">
                    R$ <?= number_format((float)$item['adjustment_amount'], 2, ',', '.') ?>
                  </td>
                  <td class="px-4 py-4 font-semibold text-zinc-900">R$ <?= number_format((float)$item['payment_amount'], 2, ',', '.') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <form method="POST" action="/finance/installments/anticipation/process" class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Helpers\CSRF::token(), ENT_QUOTES, 'UTF-8') ?>" />
          <input type="hidden" name="redirect_month" value="<?= (int)$filter['month'] ?>" />
          <input type="hidden" name="redirect_year" value="<?= (int)$filter['year'] ?>" />
          <input type="hidden" name="client_id" value="<?= (int)($anticipationForm['client_id'] ?? 0) ?>" />
          <input type="hidden" name="project_id" value="<?= (int)($anticipationForm['project_id'] ?? 0) ?>" />
          <input type="hidden" name="payment_date" value="<?= htmlspecialchars((string)($anticipationForm['payment_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
          <input type="hidden" name="payment_method" value="<?= htmlspecialchars((string)($anticipationForm['payment_method'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
          <input type="hidden" name="adjustment_mode" value="<?= htmlspecialchars((string)($anticipationForm['adjustment_mode'] ?? 'none'), ENT_QUOTES, 'UTF-8') ?>" />
          <input type="hidden" name="adjustment_rate" value="<?= htmlspecialchars((string)($anticipationForm['adjustment_rate'] ?? '0,00'), ENT_QUOTES, 'UTF-8') ?>" />
          <input type="hidden" name="note" value="<?= htmlspecialchars((string)($anticipationForm['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
          <input type="hidden" name="confirm_anticipation" value="yes" />
          <?php foreach ($selectedAnticipationInvoiceIds as $invoiceId): ?>
            <input type="hidden" name="invoice_ids[]" value="<?= (int)$invoiceId ?>" />
          <?php endforeach; ?>

          <div class="text-xs text-zinc-600">
            Confirmação obrigatória: o sistema irá registrar os pagamentos, baixar as parcelas, atualizar o caixa e gerar os recibos automaticamente.
          </div>
          <button
            type="submit"
            class="icon-btn icon-btn--md icon-btn--success"
            title="Confirmar antecipação"
            aria-label="Confirmar antecipação"
            onclick="return window.confirm('Confirmar a antecipação das parcelas selecionadas?');"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m5 12 5 5 9-9"/></svg>
            <span class="sr-only">Confirmar antecipação</span>
          </button>
        </form>
      </div>
    <?php endif; ?>

    <div class="mt-5">
      <div class="text-sm font-medium text-zinc-900">Histórico recente de antecipações</div>
      <?php if ($anticipationHistory === []): ?>
        <div class="mt-3 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600">Nenhuma antecipação registrada até o momento.</div>
      <?php else: ?>
        <div class="mt-3 overflow-hidden rounded-2xl border border-zinc-200">
          <table class="min-w-full divide-y divide-zinc-200 text-sm">
            <thead class="bg-zinc-50">
              <tr class="text-left text-zinc-500">
                <th class="px-4 py-3 font-medium">Cliente / Projeto</th>
                <th class="px-4 py-3 font-medium">Parcelas</th>
                <th class="px-4 py-3 font-medium">Regra</th>
                <th class="px-4 py-3 font-medium">Total pago</th>
                <th class="px-4 py-3 font-medium">Data efetiva</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200">
              <?php foreach ($anticipationHistory as $history): ?>
                <tr>
                  <td class="px-4 py-4">
                    <div class="font-medium text-zinc-900"><?= htmlspecialchars((string)$history['client_name'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars((string)($history['project_name'] ?: 'Múltiplos projetos / cliente'), ENT_QUOTES, 'UTF-8') ?></div>
                  </td>
                  <td class="px-4 py-4 text-zinc-600"><?= (int)$history['installments_count'] ?></td>
                  <td class="px-4 py-4 text-zinc-600">
                    <?php
                    $ruleLabel = match ((string)$history['adjustment_mode']) {
                        'discount' => 'Desconto',
                        'interest' => 'Acréscimo',
                        default => 'Sem ajuste',
                    };
                    ?>
                    <?= htmlspecialchars($ruleLabel, ENT_QUOTES, 'UTF-8') ?>
                    <?php if ((float)$history['adjustment_rate'] > 0): ?>
                      · <?= number_format((float)$history['adjustment_rate'], 2, ',', '.') ?>%
                    <?php endif; ?>
                  </td>
                  <td class="px-4 py-4 font-semibold text-zinc-900">R$ <?= number_format((float)$history['total_amount'], 2, ',', '.') ?></td>
                  <td class="px-4 py-4 text-zinc-600"><?= htmlspecialchars(\App\Helpers\Security::formatDate((string)$history['payment_date']), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="mt-6 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div class="text-sm font-medium text-zinc-900">Parcelas do período</div>
      <div class="text-xs text-zinc-500"><?= count($entries) ?> registros</div>
    </div>
    <div class="mt-4 overflow-hidden rounded-2xl border border-zinc-200">
      <table class="min-w-full divide-y divide-zinc-200 text-sm">
        <thead class="bg-zinc-50">
          <tr class="text-left text-zinc-500">
            <th class="px-4 py-3 font-medium">Parcela</th>
            <th class="px-4 py-3 font-medium">Cliente / Projeto</th>
            <th class="px-4 py-3 font-medium">Vencimento</th>
            <th class="px-4 py-3 font-medium">Valor</th>
            <th class="px-4 py-3 font-medium">Status</th>
            <th class="px-4 py-3 font-medium">Baixa</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-zinc-200">
          <?php if ($entries === []): ?>
            <tr>
              <td colspan="6" class="px-4 py-6 text-center text-zinc-500">Nenhuma parcela encontrada.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($entries as $entry): ?>
              <?php
              $status = (string)$entry['status'];
              $remaining = max(0, round((float)$entry['amount_total'] - (float)$entry['amount_paid'], 2));
              $badge = $status === 'paid'
                  ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                  : ($status === 'overdue' ? 'border-red-200 bg-red-50 text-red-700' : 'border-amber-200 bg-amber-50 text-amber-700');
              $icon = $status === 'paid' ? '✓' : ($status === 'overdue' ? '⚠' : '⏳');
              ?>
              <tr class="align-top">
                <td class="px-4 py-4">
                  <div class="font-medium text-zinc-900"><?= htmlspecialchars((string)$entry['description'], ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars((string)$entry['code'], ENT_QUOTES, 'UTF-8') ?></div>
                </td>
                <td class="px-4 py-4 text-zinc-600">
                  <div><?= htmlspecialchars((string)$entry['client_name'], ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars((string)($entry['project_name'] ?: $entry['task_title'] ?: 'Sem origem'), ENT_QUOTES, 'UTF-8') ?></div>
                </td>
                <td class="px-4 py-4 text-zinc-600"><?= htmlspecialchars(\App\Helpers\Security::formatDate((string)$entry['due_date']), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="px-4 py-4">
                  <div class="font-semibold text-zinc-900">R$ <?= number_format((float)$entry['amount_total'], 2, ',', '.') ?></div>
                  <div class="mt-1 text-xs text-zinc-500">Pago: R$ <?= number_format((float)$entry['amount_paid'], 2, ',', '.') ?></div>
                  <div class="mt-1 text-xs text-zinc-500">Saldo: R$ <?= number_format((float)$remaining, 2, ',', '.') ?></div>
                </td>
                <td class="px-4 py-4">
                  <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium <?= $badge ?>">
                    <span><?= $icon ?></span>
                    <span><?= $status === 'paid' ? 'Paga' : ($status === 'overdue' ? 'Vencida' : 'Pendente') ?></span>
                  </span>
                </td>
                <td class="px-4 py-4">
                  <?php if ($status === 'paid'): ?>
                    <div class="space-y-1 text-xs text-emerald-600">
                      <div>Baixada em <?= htmlspecialchars(\App\Helpers\Security::formatDate(substr((string)$entry['paid_at'], 0, 10)), ENT_QUOTES, 'UTF-8') ?></div>
                      <div>Último pagamento: R$ <?= number_format((float)($entry['last_payment_amount'] ?? $entry['amount_paid']), 2, ',', '.') ?></div>
                      <?php if ((int)($entry['last_payment_id'] ?? 0) > 0 && trim((string)($entry['last_receipt_path'] ?? '')) !== ''): ?>
                        <a
                          href="/finance/payments/<?= (int)$entry['last_payment_id'] ?>/receipt?month=<?= (int)$filter['month'] ?>&year=<?= (int)$filter['year'] ?>&return_to=<?= urlencode('/finance?month=' . (int)$filter['month'] . '&year=' . (int)$filter['year']) ?>"
                          class="icon-btn icon-btn--sm"
                          title="Baixar recibo de pagamento"
                          aria-label="Baixar recibo de pagamento"
                          data-loading-link
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                          <span class="sr-only">Baixar recibo de pagamento</span>
                        </a>
                      <?php endif; ?>
                    </div>
                  <?php else: ?>
                    <form method="POST" action="/finance/installments/pay" class="space-y-2">
                      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Helpers\CSRF::token(), ENT_QUOTES, 'UTF-8') ?>" />
                      <input type="hidden" name="invoice_id" value="<?= (int)$entry['id'] ?>" />
                      <input type="hidden" name="redirect_month" value="<?= (int)$filter['month'] ?>" />
                      <input type="hidden" name="redirect_year" value="<?= (int)$filter['year'] ?>" />
                      <input name="amount_paid" required data-money-mask value="<?= htmlspecialchars(number_format($remaining, 2, ',', ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-xs text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30" />
                      <input name="payment_date" required data-date-mask placeholder="dd/mm/aaaa" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-xs text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30" />
                      <select name="payment_method" required class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-xs text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30">
                        <option value="">Método de pagamento</option>
                        <option value="pix">PIX</option>
                        <option value="boleto">Boleto</option>
                        <option value="transferencia">Transferência</option>
                        <option value="cartao">Cartão</option>
                        <option value="dinheiro">Dinheiro</option>
                        <option value="outro">Outro</option>
                      </select>
                      <input name="note" maxlength="255" placeholder="Observação" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-xs text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30" />
                      <button type="submit" class="icon-btn icon-btn--md icon-btn--success w-full" title="Registrar pagamento" aria-label="Registrar pagamento da parcela">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m5 12 5 5 9-9"/></svg>
                        <span class="sr-only">Registrar pagamento</span>
                      </button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php
    return (string)ob_get_clean();
})();
require __DIR__ . '/../partials/app-shell.php';
$content = ob_get_clean();
require __DIR__ . '/../layouts/base.php';
