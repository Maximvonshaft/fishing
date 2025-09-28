<?php $displayTz = config('app.display_timezone', 'Europe/Tirane'); ?>
<div class="bg-white border border-slate-200 rounded-3xl shadow-sm">
    <div class="p-6 border-b border-slate-200 space-y-4">
        <nav class="text-xs text-slate-400 flex items-center gap-1">
            <a href="<?= route('projects.index') ?>" class="hover:text-brand">国家</a>
            <span>/</span>
            <span class="text-slate-500">批次列表</span>
        </nav>
        <div class="flex flex-col gap-2">
            <div class="text-sm text-slate-500">国家：<?= htmlspecialchars($selectedProject ?? '全部') ?></div>
            <div class="text-lg font-semibold text-slate-900 flex items-center gap-3">
                批次调度视图
            </div>
        </div>
    </div>

    <div class="p-6 space-y-6">
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4 text-sm">
            <form method="get" class="flex flex-wrap items-end gap-3">
                <input type="hidden" name="page" value="shipments">
                <div>
                    <label class="block text-xs text-slate-500 mb-1">国家</label>
                    <select name="country" class="rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                        <option value="">全部</option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?= htmlspecialchars($country['code']) ?>" <?= ($selectedProject ?? '') === $country['code'] ? 'selected' : '' ?>><?= htmlspecialchars($country['code'] . ' · ' . $country['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">状态</label>
                    <select name="status" class="rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                        <option value="">全部</option>
                        <option value="WARN" <?= ($filters['status'] ?? '') === 'WARN' ? 'selected' : '' ?>>WARN</option>
                        <option value="BREACH" <?= ($filters['status'] ?? '') === 'BREACH' ? 'selected' : '' ?>>BREACH</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">批次编码</label>
                    <input type="text" name="q" value="<?= htmlspecialchars($filters['q'] ?? '') ?>" class="rounded-lg border-slate-300 focus:border-brand focus:ring-brand" placeholder="PVG-ALB-202401-01">
                </div>
                <button type="submit" class="px-4 py-2 rounded-lg border border-slate-200 hover:border-brand/40">筛选</button>
            </form>
            <form method="post" action="<?= htmlspecialchars(route('shipments.index')) ?>" class="bg-slate-50 border border-dashed border-slate-200 rounded-2xl p-4 flex flex-wrap items-end gap-3">
                <input type="hidden" name="_action" value="shipment_create">
                <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <div>
                    <label class="block text-xs text-slate-500 mb-1">目的国家</label>
                    <select name="country_id" required class="rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                        <option value="">选择国家</option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?= (int) $country['id'] ?>"><?= htmlspecialchars($country['code'] . ' · ' . $country['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">批次编码</label>
                    <input name="code" required class="rounded-lg border-slate-300 focus:border-brand focus:ring-brand" placeholder="PVG-ALB-20250925-01">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">ETA (UTC)</label>
                    <input name="eta_dest_airport" type="datetime-local" step="60" required class="rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">起运地</label>
                    <input name="origin" class="rounded-lg border-slate-300 focus:border-brand focus:ring-brand" placeholder="PVG">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">备注</label>
                    <input name="remarks" class="rounded-lg border-slate-300 focus:border-brand focus:ring-brand" placeholder="可选">
                </div>
                <label class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                    <input type="checkbox" name="open_detail" value="1" checked class="rounded border-slate-300">
                    创建后打开详情
                </label>
                <button type="submit" class="px-4 py-2 bg-brand text-white rounded-lg text-sm shadow-sm">新建批次</button>
            </form>
        </div>

        <div class="flex items-center justify-between text-sm">
            <div class="text-slate-500">共 <?= count($shipments) ?> 个批次</div>
            <div class="flex items-center gap-2">
                <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-200 hover:border-brand/40">批量通知供应商</button>
                <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-200 hover:border-brand/40">导出清单</button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="py-3 px-4 text-left">批次编码</th>
                        <th class="py-3 px-4 text-left">起运</th>
                        <th class="py-3 px-4 text-left">目的</th>
                        <th class="py-3 px-4 text-left">创建 / ETA</th>
                        <th class="py-3 px-4 text-left">节点完成</th>
                        <th class="py-3 px-4 text-left">将到期</th>
                        <th class="py-3 px-4 text-left">逾时</th>
                        <th class="py-3 px-4 text-left">最近更新时间</th>
                        <th class="py-3 px-4 text-left">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($shipments as $shipment): ?>
                        <?php $progressPercentage = $shipment['total_nodes'] > 0 ? ($shipment['progress'] / $shipment['total_nodes']) * 100 : 0; ?>
                        <tr class="hover:bg-brand/5">
                            <td class="py-4 px-4 font-semibold text-slate-900">
                                <div class="flex items-center gap-2">
                                    <span><?= htmlspecialchars($shipment['code']) ?></span>
                                </div>
                            </td>
                            <td class="py-4 px-4"><?= htmlspecialchars($shipment['origin'] ?? '--') ?></td>
                            <td class="py-4 px-4"><?= htmlspecialchars($shipment['destination'] ?? '--') ?></td>
                            <td class="py-4 px-4">
                                <div>创建 <?= htmlspecialchars(format_datetime($shipment['etd'])) ?></div>
                                <div>ETA <?= htmlspecialchars(format_datetime($shipment['eta'])) ?></div>
                            </td>
                            <td class="py-4 px-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-40 h-2 bg-slate-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-brand" style="width: <?= $progressPercentage ?>%"></div>
                                    </div>
                                    <div class="text-xs text-slate-500"><?= $shipment['progress'] ?>/<?= $shipment['total_nodes'] ?></div>
                                </div>
                            </td>
                            <td class="py-4 px-4 text-amber-600">⚠️ <?= $shipment['warn_nodes'] ?></td>
                            <td class="py-4 px-4 text-rose-600">⛔ <?= $shipment['breach_nodes'] ?></td>
                            <td class="py-4 px-4 text-slate-500"><?= htmlspecialchars(format_datetime($shipment['updated_at'])) ?></td>
                            <td class="py-4 px-4">
                                <a href="<?= htmlspecialchars(route('shipments.show', ['id' => $shipment['id']])) ?>" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-brand text-brand hover:bg-brand/5">进入详情</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($shipments)): ?>
                        <tr>
                            <td colspan="9" class="py-6 px-4 text-center text-slate-500">暂无批次数据</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
