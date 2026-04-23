<?php
$active = 'dashboard';
ob_start();
$slot = '';
?>
<?php
$slot = (function () use ($dashboard, $filters, $clients, $availableYears, $error): string {
    ob_start();
    $fmtMoney = static fn(float $v): string => 'R$ ' . number_format($v, 2, ',', '.');
?>
  <div class="mx-auto w-full" style="max-width:1200px">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <div class="text-sm font-medium text-zinc-500">Visão executiva</div>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-zinc-900">Dashboard Financeiro</h1>
      </div>
      <form method="GET" action="/dashboard" class="w-full rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm lg:w-auto" data-loading-form>
        <div class="grid gap-2 md:grid-cols-5">
          <select name="year" class="rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm">
            <?php foreach ($availableYears as $year): ?>
              <option value="<?= (int)$year ?>" <?= (int)$filters['year'] === (int)$year ? 'selected' : '' ?>><?= (int)$year ?></option>
            <?php endforeach; ?>
          </select>
          <select name="project_status" class="rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm">
            <option value="">Todos os status</option>
            <?php foreach (['active' => 'Ativo', 'completed' => 'Concluído', 'on_hold' => 'Em espera', 'cancelled' => 'Cancelado'] as $value => $label): ?>
              <option value="<?= $value ?>" <?= (string)$filters['project_status'] === $value ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
          <select name="client_id" class="rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm">
            <option value="0">Todos os clientes</option>
            <?php foreach ($clients as $client): ?>
              <option value="<?= (int)$client['id'] ?>" <?= (int)$filters['client_id'] === (int)$client['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars((string)$client['name'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="submit" data-loading-button data-loading-text="Atualizando..." class="rounded-xl bg-[#FE5516] px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90">Aplicar</button>
          <a href="/dashboard" class="inline-flex items-center justify-center rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50">Limpar</a>
        </div>
        <div class="mt-2 hidden rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs text-zinc-500" data-loading-indicator>Atualizando dados do dashboard...</div>
      </form>
    </div>

    <?php if (!empty($error)): ?>
      <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Faturado no ano</div>
        <div class="mt-2 text-3xl font-bold leading-tight text-zinc-900"><?= $fmtMoney((float)$dashboard['total_billed_year']) ?></div>
      </div>
      <div class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm">
        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Recebido no ano</div>
        <div class="mt-2 text-3xl font-bold leading-tight text-emerald-600"><?= $fmtMoney((float)$dashboard['total_received_year']) ?></div>
      </div>
      <div class="rounded-2xl border border-amber-100 bg-white p-5 shadow-sm">
        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Em aberto</div>
        <div class="mt-2 text-3xl font-bold leading-tight text-amber-600"><?= $fmtMoney((float)$dashboard['total_open_year']) ?></div>
      </div>
      <div class="rounded-2xl border border-blue-100 bg-white p-5 shadow-sm">
        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Saldo atual</div>
        <div class="mt-2 text-3xl font-bold leading-tight <?= (float)$dashboard['balance_year'] >= 0 ? 'text-blue-600' : 'text-red-600' ?>"><?= $fmtMoney((float)$dashboard['balance_year']) ?></div>
      </div>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-10 lg:items-stretch">
      <div data-dashboard-flow-card class="lg:col-span-7 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm lg:h-[420px]">
        <div class="flex items-center justify-between gap-3">
          <div class="text-base font-semibold text-zinc-900">Fluxo Financeiro Anual</div>
          <div class="flex items-center gap-4 text-xs text-zinc-600">
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-slate-400"></span>Previsto</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-blue-600"></span>Realizado</span>
          </div>
        </div>
        <div class="mt-4 h-[calc(100%-40px)]" data-dashboard-flow data-flow='<?= htmlspecialchars(json_encode($dashboard['flow_dataset'] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'>
          <div class="relative h-full rounded-xl border border-zinc-100 bg-zinc-50 p-3">
            <canvas data-flow-canvas class="h-full w-full"></canvas>
            <div data-flow-tooltip class="pointer-events-none absolute hidden rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs text-zinc-700 shadow"></div>
          </div>
        </div>
      </div>

      <aside data-dashboard-indicators-card class="lg:col-span-3 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm lg:h-[420px]">
        <div class="text-sm font-semibold text-zinc-900">Indicadores Financeiros</div>
        <div class="mt-4 space-y-2 text-sm">
          <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3">
            <div class="text-xs text-zinc-500">% de recebimento</div>
            <div class="mt-1 text-lg font-semibold text-zinc-900"><?= number_format((float)$dashboard['receive_rate'], 1, ',', '.') ?>%</div>
          </div>
          <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3">
            <div class="text-xs text-zinc-500">Média mensal de faturamento</div>
            <div class="mt-1 text-lg font-semibold text-zinc-900"><?= $fmtMoney((float)$dashboard['avg_monthly_billed']) ?></div>
          </div>
          <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3">
            <div class="text-xs text-zinc-500">Melhor mês</div>
            <div class="mt-1 text-base font-semibold text-zinc-900"><?= htmlspecialchars((string)$dashboard['best_month_label'], ENT_QUOTES, 'UTF-8') ?> · <?= $fmtMoney((float)$dashboard['best_month_value']) ?></div>
          </div>
          <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3">
            <div class="text-xs text-zinc-500">Pior mês</div>
            <div class="mt-1 text-base font-semibold text-zinc-900"><?= htmlspecialchars((string)$dashboard['worst_month_label'], ENT_QUOTES, 'UTF-8') ?> · <?= $fmtMoney((float)$dashboard['worst_month_value']) ?></div>
          </div>
        </div>
      </aside>
    </div>
  </div>

  <div class="mt-5 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
    <div class="flex items-center justify-between gap-3">
      <div>
        <div class="text-sm font-semibold text-zinc-900">Projetos</div>
        <div class="mt-1 text-xs text-zinc-500">Valores anuais e produtividade operacional por projeto.</div>
      </div>
      <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs text-zinc-600"><?= (int)$dashboard['projects_count'] ?> projetos</span>
    </div>
    <div class="mt-4 overflow-x-auto rounded-xl border border-zinc-200">
      <table class="min-w-full divide-y divide-zinc-200 text-sm">
        <thead class="bg-zinc-50">
          <tr class="text-left text-zinc-500">
            <th class="px-4 py-3 font-medium">Projeto</th>
            <th class="px-4 py-3 font-medium">Cliente</th>
            <th class="px-4 py-3 font-medium">Valor total</th>
            <th class="px-4 py-3 font-medium">Recebido</th>
            <th class="px-4 py-3 font-medium">Em aberto</th>
            <th class="px-4 py-3 font-medium">Status</th>
            <th class="px-4 py-3 font-medium">Tarefas</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-zinc-200 bg-white">
          <?php if (($dashboard['projects'] ?? []) === []): ?>
            <tr><td colspan="7" class="px-4 py-6 text-center text-zinc-500">Nenhum projeto encontrado para os filtros aplicados.</td></tr>
          <?php else: ?>
            <?php foreach (($dashboard['projects'] ?? []) as $project): ?>
              <?php
              $status = (string)($project['status'] ?? 'active');
              $statusClass = $status === 'active'
                  ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                  : ($status === 'completed' ? 'border-blue-200 bg-blue-50 text-blue-700' : ($status === 'on_hold' ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-red-200 bg-red-50 text-red-700'));
              ?>
              <tr>
                <td class="px-4 py-3 font-medium text-zinc-900"><?= htmlspecialchars((string)$project['name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="px-4 py-3 text-zinc-700"><?= htmlspecialchars((string)$project['client_name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="px-4 py-3 text-zinc-900"><?= $fmtMoney((float)$project['total_value']) ?></td>
                <td class="px-4 py-3 text-emerald-700"><?= $fmtMoney((float)$project['received_value']) ?></td>
                <td class="px-4 py-3 text-amber-700"><?= $fmtMoney((float)$project['open_value']) ?></td>
                <td class="px-4 py-3">
                  <span class="rounded-full border px-2 py-1 text-xs <?= $statusClass ?>"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></span>
                </td>
                <td class="px-4 py-3 text-zinc-700">
                  <span class="inline-flex items-center gap-2 text-xs">
                    <span>Total: <strong><?= (int)$project['tasks_total'] ?></strong></span>
                    <span>Concluídas: <strong><?= (int)$project['tasks_done'] ?></strong></span>
                    <span>Pendentes: <strong><?= (int)$project['tasks_pending'] ?></strong></span>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <script>
    (function () {
      const wrap = document.querySelector('[data-dashboard-flow]');
      const flowCard = document.querySelector('[data-dashboard-flow-card]');
      const indicatorsCard = document.querySelector('[data-dashboard-indicators-card]');
      if (!wrap) return;
      const canvas = wrap.querySelector('[data-flow-canvas]');
      const tooltip = wrap.querySelector('[data-flow-tooltip]');
      if (!canvas || !(canvas instanceof HTMLCanvasElement)) return;

      let dataset = [];
      try {
        dataset = JSON.parse(wrap.getAttribute('data-flow') || '[]');
      } catch (_) {
        dataset = [];
      }
      if (!Array.isArray(dataset)) dataset = [];
      if (dataset.length === 0) return;

      const ctx = canvas.getContext('2d');
      if (!ctx) return;
      const dpr = window.devicePixelRatio || 1;
      const syncCardsHeight = () => {
        if (!flowCard || !indicatorsCard) return;
        if (window.innerWidth < 1024) {
          flowCard.style.height = '';
          indicatorsCard.style.height = '';
          return;
        }
        const indicatorHeight = indicatorsCard.getBoundingClientRect().height;
        if (indicatorHeight > 0) {
          flowCard.style.height = `${Math.round(indicatorHeight)}px`;
          indicatorsCard.style.height = `${Math.round(indicatorHeight)}px`;
        }
      };

      const render = () => {
        syncCardsHeight();
        const rect = canvas.getBoundingClientRect();
        const width = Math.max(300, rect.width);
        const height = Math.max(220, rect.height);
        canvas.width = Math.round(width * dpr);
        canvas.height = Math.round(height * dpr);
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, width, height);

        const pad = { top: 14, right: 12, bottom: 26, left: 50 };
        const chartW = width - pad.left - pad.right;
        const chartH = height - pad.top - pad.bottom;
        const maxValue = Math.max(1, ...dataset.map(d => Math.max(Number(d.previsto || 0), Number(d.realizado || 0))));
        const groupW = chartW / dataset.length;
        const barW = Math.max(6, Math.min(14, groupW * 0.26));

        ctx.strokeStyle = '#e4e4e7';
        ctx.lineWidth = 1;
        for (let i = 0; i <= 4; i += 1) {
          const y = pad.top + (chartH / 4) * i;
          ctx.beginPath();
          ctx.moveTo(pad.left, y);
          ctx.lineTo(width - pad.right, y);
          ctx.stroke();

          const v = maxValue * (1 - i / 4);
          const label = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 }).format(v);
          ctx.fillStyle = '#71717a';
          ctx.font = '10px sans-serif';
          ctx.textAlign = 'right';
          ctx.fillText(label, pad.left - 6, y + 3);
        }

        const bars = [];
        dataset.forEach((item, i) => {
          const cx = pad.left + groupW * i + groupW / 2;
          const previsto = Number(item.previsto || 0);
          const realizado = Number(item.realizado || 0);
          const hPrev = (previsto / maxValue) * chartH;
          const hReal = (realizado / maxValue) * chartH;

          const prevX = cx - barW - 2;
          const realX = cx + 2;
          const yPrev = pad.top + chartH - hPrev;
          const yReal = pad.top + chartH - hReal;

          ctx.fillStyle = '#94a3b8';
          ctx.fillRect(prevX, yPrev, barW, hPrev);
          ctx.fillStyle = '#2563eb';
          ctx.fillRect(realX, yReal, barW, hReal);

          ctx.fillStyle = '#71717a';
          ctx.font = '10px sans-serif';
          ctx.textAlign = 'center';
          ctx.fillText(String(item.mes || ''), cx, height - 8);

          bars.push({
            x: prevX, y: yPrev, w: barW, h: hPrev, mes: String(item.mes || ''), tipo: 'Previsto', valor: previsto,
          });
          bars.push({
            x: realX, y: yReal, w: barW, h: hReal, mes: String(item.mes || ''), tipo: 'Realizado', valor: realizado,
          });
        });

        const money = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);
        const onMove = (ev) => {
          if (!tooltip) return;
          const r = canvas.getBoundingClientRect();
          const x = ev.clientX - r.left;
          const y = ev.clientY - r.top;
          const hit = bars.find(b => x >= b.x && x <= b.x + b.w && y >= b.y && y <= b.y + b.h);
          if (!hit) {
            tooltip.classList.add('hidden');
            return;
          }
          tooltip.textContent = `${hit.mes} · ${hit.tipo}: ${money(hit.valor)}`;
          tooltip.style.left = `${Math.min(r.width - 170, Math.max(8, x + 10))}px`;
          tooltip.style.top = `${Math.max(8, y - 26)}px`;
          tooltip.classList.remove('hidden');
        };

        canvas.onmousemove = onMove;
        canvas.onmouseleave = () => tooltip && tooltip.classList.add('hidden');
      };

      render();
      window.addEventListener('resize', render);
    })();
  </script>
<?php
    return (string)ob_get_clean();
})();
?>
<?php
require __DIR__ . '/../partials/app-shell.php';
$content = ob_get_clean();
require __DIR__ . '/../layouts/base.php';

