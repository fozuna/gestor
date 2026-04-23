<?php
$active = 'budgets';
ob_start();
?>
<?php
$slot = (function () use ($preview, $presentation): string {
    $budget = $preview['budget'] ?? [];
    $sections = $preview['sections'] ?? [];
    $text = (string)($preview['text'] ?? '');
    $budgetId = (int)($budget['id'] ?? 0);
    ob_start();
?>
  <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
    <div>
      <div class="text-sm text-zinc-500">Orçamento #<?= $budgetId ?></div>
      <div class="mt-1 text-2xl font-semibold text-zinc-900">Proposta comercial</div>
      <div class="mt-1 text-sm text-zinc-500">Visualize, copie e exporte a proposta em texto ou PDF.</div>
    </div>
    <div class="flex gap-2">
      <a href="/orcamentos" class="icon-btn icon-btn--md" title="Voltar para orçamentos" aria-label="Voltar para orçamentos">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m15 18-6-6 6-6"/></svg>
        <span class="sr-only">Voltar</span>
      </a>
      <a href="/orcamentos/<?= $budgetId ?>/proposta/pdf?apresentacao=<?= urlencode((string)$presentation) ?>" class="icon-btn icon-btn--md icon-btn--primary" title="Exportar proposta em PDF" aria-label="Exportar proposta em PDF">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M5 13V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-1"/><path d="M9 17h6M9 13h3"/></svg>
        <span class="sr-only">Exportar PDF</span>
      </a>
    </div>
  </div>

  <div class="mt-6 grid gap-4 xl:grid-cols-[380px_minmax(0,1fr)]">
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
      <div class="text-sm font-semibold text-zinc-900">Apresentação editável</div>
      <form method="GET" action="/orcamentos/<?= $budgetId ?>/proposta" class="mt-3 space-y-3">
        <textarea name="apresentacao" rows="8" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm"><?= htmlspecialchars((string)$presentation, ENT_QUOTES, 'UTF-8') ?></textarea>
        <button type="submit" class="icon-btn icon-btn--md icon-btn--primary" title="Atualizar proposta" aria-label="Atualizar proposta">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
          <span class="sr-only">Atualizar proposta</span>
        </button>
      </form>
      <a href="/orcamentos/<?= $budgetId ?>/proposta/txt?apresentacao=<?= urlencode((string)$presentation) ?>" class="mt-2 icon-btn icon-btn--md" title="Exportar proposta em texto" aria-label="Exportar proposta em texto">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 7h16M4 12h10M4 17h7"/></svg>
        <span class="sr-only">Exportar texto</span>
      </a>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
      <div class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars((string)($sections['header']['proposta'] ?? 'Proposta'), ENT_QUOTES, 'UTF-8') ?></div>
      <div class="mt-1 text-xs text-zinc-500">Cliente: <?= htmlspecialchars((string)($sections['header']['cliente'] ?? '-'), ENT_QUOTES, 'UTF-8') ?> · Gerado em <?= htmlspecialchars((string)($sections['header']['gerado_em'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>

      <div class="mt-4 space-y-3 text-sm">
        <div><span class="font-semibold">Apresentação:</span> <?= nl2br(htmlspecialchars((string)($sections['apresentacao'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
        <div><span class="font-semibold">Escopo:</span> <?= nl2br(htmlspecialchars((string)($sections['escopo'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
        <div><span class="font-semibold">Investimento:</span> <?= htmlspecialchars((string)($sections['investimento'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
        <div>
          <span class="font-semibold">Condições de pagamento:</span>
          <ul class="mt-1 list-disc pl-5 text-zinc-700">
            <?php foreach (($sections['condicoes'] ?? []) as $cond): ?>
              <li><?= htmlspecialchars((string)$cond, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div><span class="font-semibold">Prazo:</span> <?= htmlspecialchars((string)($sections['prazo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
        <div><span class="font-semibold">Validade:</span> <?= htmlspecialchars((string)($sections['validade'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
      </div>

      <div class="mt-5 border-t border-zinc-200 pt-4">
        <div class="flex items-center justify-between">
          <div class="text-sm font-semibold text-zinc-900">Versão em texto</div>
          <button type="button" data-copy-proposal class="icon-btn icon-btn--sm" title="Copiar proposta" aria-label="Copiar proposta">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
            <span class="sr-only">Copiar</span>
          </button>
        </div>
        <textarea readonly data-proposal-text rows="12" class="mt-2 w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs text-zinc-700"><?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?></textarea>
      </div>
    </div>
  </div>

  <script>
    (function () {
      const btn = document.querySelector('[data-copy-proposal]');
      const field = document.querySelector('[data-proposal-text]');
      if (!btn || !field) return;
      btn.addEventListener('click', async () => {
        try {
          await navigator.clipboard.writeText(field.value || '');
          btn.textContent = 'Copiado';
          setTimeout(() => { btn.textContent = 'Copiar'; }, 1400);
        } catch (e) {
          btn.textContent = 'Falha ao copiar';
          setTimeout(() => { btn.textContent = 'Copiar'; }, 1400);
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
