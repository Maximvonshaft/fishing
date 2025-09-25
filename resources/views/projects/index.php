<div class="bg-white border border-slate-200 rounded-3xl shadow-sm">
    <div class="p-6 border-b border-slate-200 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex-1">
            <div class="text-sm text-slate-500">国家目录</div>
            <div class="text-lg font-semibold text-slate-900">按目的地国家管理批次与模板</div>
        </div>
        <div class="flex flex-col lg:flex-row lg:items-center gap-3 text-sm">
            <div class="flex items-center gap-2">
                <label class="text-slate-600">国家筛选</label>
                <select class="rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                    <option selected>全部</option>
                    <?php foreach ($projects as $project): ?>
                        <option><?= htmlspecialchars($project['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <label class="text-slate-600">搜索</label>
                <input type="text" placeholder="国家名/代码" class="rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
            </div>
            <a href="<?= route('templates.index') ?>" class="inline-flex items-center gap-2 px-4 py-2 border border-brand text-brand rounded-lg hover:bg-brand/5">
                管理模板
            </a>
        </div>
    </div>
    <div class="grid gap-4 p-6 md:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($projects as $project): ?>
            <a href="<?= route('shipments.index', ['project' => $project['code']]) ?>" class="p-5 border border-slate-200 rounded-2xl hover:border-brand/50 hover:shadow transition bg-white flex flex-col gap-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-widest text-slate-400">国家</div>
                        <div class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                            <?= htmlspecialchars($project['name']) ?>
                            <span class="text-xs text-slate-400">(<?= htmlspecialchars($project['code']) ?>)</span>
                        </div>
                    </div>
                    <div class="text-xs text-slate-500">模板版本 · <?= htmlspecialchars($project['template_version']) ?></div>
                </div>
                <div class="flex justify-between text-sm">
                    <div>
                        <div class="text-slate-500">近 30 天批次</div>
                        <div class="text-lg font-semibold text-slate-900"><?= $project['shipment_count'] ?></div>
                    </div>
                    <div>
                        <div class="text-slate-500">将到期</div>
                        <div class="text-lg font-semibold text-amber-600"><?= $project['due_nodes'] ?></div>
                    </div>
                    <div>
                        <div class="text-slate-500">已逾时</div>
                        <div class="text-lg font-semibold text-rose-600"><?= $project['overdue_nodes'] ?></div>
                    </div>
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-brand/10 text-brand font-medium">批次列表</span>
                    <span>进入查看时间轴、分派和证据链</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>
