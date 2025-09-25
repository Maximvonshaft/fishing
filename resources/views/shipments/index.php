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

    <div class="p-6">
        <div class="flex items-center justify-between mb-4 text-sm">
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
