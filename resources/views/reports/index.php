<div class="space-y-6">
    <section class="bg-white border border-slate-200 rounded-3xl shadow-sm p-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="text-sm text-slate-500">整体绩效</div>
                <div class="text-lg font-semibold text-slate-900">SLA 指标看板</div>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <input type="date" class="rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                <span class="text-slate-500">至</span>
                <input type="date" class="rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                <select class="rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                    <option>国家</option>
                    <option>供应商</option>
                </select>
            </div>
        </div>
        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <?php foreach ($reports['kpis'] as $kpi): ?>
                <div class="border border-slate-200 rounded-2xl p-4">
                    <div class="text-xs text-slate-400 uppercase"><?= htmlspecialchars($kpi['label']) ?></div>
                    <div class="mt-2 text-2xl font-semibold text-slate-900"><?= htmlspecialchars($kpi['value']) ?></div>
                    <div class="text-xs text-emerald-600 mt-1">趋势 <?= htmlspecialchars($kpi['trend']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="bg-white border border-slate-200 rounded-3xl shadow-sm p-6">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900">趋势分析</h3>
            <button class="text-sm text-brand">导出 CSV</button>
        </div>
        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <div class="border border-slate-200 rounded-2xl p-4">
                <div class="text-sm font-semibold text-slate-700">SLA 状态趋势</div>
                <div class="mt-4 h-48 bg-gradient-to-br from-brand/10 to-transparent rounded-2xl flex items-center justify-center text-slate-400">
                    折线图占位
                </div>
            </div>
            <div class="border border-slate-200 rounded-2xl p-4">
                <div class="text-sm font-semibold text-slate-700">供应商 TopN</div>
                <ul class="mt-3 space-y-3 text-sm text-slate-600">
                    <?php foreach ($reports['top_vendors'] as $vendor): ?>
                        <li class="flex items-center justify-between">
                            <div>
                                <div class="font-semibold text-slate-900"><?= htmlspecialchars($vendor['name']) ?></div>
                                <div class="text-xs text-slate-400">逾时 <?= htmlspecialchars((string)$vendor['breach']) ?> 次</div>
                            </div>
                            <span class="inline-flex items-center px-2 py-1 rounded-full bg-brand/10 text-brand text-xs font-medium">SLA <?= htmlspecialchars($vendor['sla']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </section>
</div>
