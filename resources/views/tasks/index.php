<div class="bg-white border border-slate-200 rounded-3xl shadow-sm">
    <div class="p-6 border-b border-slate-200 space-y-4">
        <div>
            <div class="text-sm text-slate-500">供应商工作台</div>
            <div class="text-lg font-semibold text-slate-900">我的待办节点</div>
        </div>
        <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4 text-sm">
            <div>
                <label class="text-slate-600">国家</label>
                <select class="mt-1 w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                    <option>全部</option>
                    <option>Albania</option>
                </select>
            </div>
            <div>
                <label class="text-slate-600">批次</label>
                <input class="mt-1 w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand" placeholder="ALB-2403-001">
            </div>
            <div>
                <label class="text-slate-600">状态</label>
                <select class="mt-1 w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                    <option>全部</option>
                    <option>OK</option>
                    <option>WARN</option>
                    <option>BREACH</option>
                    <option>PAUSED</option>
                </select>
            </div>
            <div>
                <label class="text-slate-600">截止时间</label>
                <input type="date" class="mt-1 w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
            </div>
        </div>
    </div>
    <div class="p-6">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                    <tr>
                        <th class="px-4 py-3 text-left">节点</th>
                        <th class="px-4 py-3 text-left">批次 / 国家</th>
                        <th class="px-4 py-3 text-left">截止</th>
                        <th class="px-4 py-3 text-left">倒计时</th>
                        <th class="px-4 py-3 text-left">状态</th>
                        <th class="px-4 py-3 text-left">所需操作</th>
                        <th class="px-4 py-3 text-left">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($tasks as $task): ?>
                    <?php $deadline = formatUtcToLocal($task['deadline']); ?>
                    <tr class="hover:bg-brand/5">
                        <td class="px-4 py-4 font-semibold text-slate-900"><?= htmlspecialchars($task['name']) ?></td>
                        <td class="px-4 py-4 text-slate-600">
                            <div><?= htmlspecialchars($task['shipment_code']) ?></div>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars($task['country']) ?></div>
                        </td>
                        <td class="px-4 py-4">
                            <div><?= $deadline['local'] ?></div>
                            <div class="text-xs text-slate-400">UTC <?= $deadline['utc'] ?></div>
                        </td>
                        <td class="px-4 py-4 font-semibold <?= $task['remaining_minutes'] < 0 ? 'text-rose-600' : 'text-slate-900' ?>">
                            <?php $remaining = $task['remaining_minutes']; ?>
                            <?= $remaining >= 0 ? sprintf('%02d:%02d', floor($remaining / 60), $remaining % 60) : '超时 ' . abs($remaining) . ' 分钟' ?>
                        </td>
                        <td class="px-4 py-4">
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full border <?= statusColor($task['status']) ?>">
                                <?= htmlspecialchars($task['status']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-4 text-slate-600">
                            <?php foreach ($task['actions'] as $action): ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full bg-brand/10 text-brand text-xs font-medium mr-2 mb-1"><?= htmlspecialchars($action) ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td class="px-4 py-4">
                            <div class="flex items-center gap-2">
                                <a href="<?= route('shipments.show', ['code' => $task['shipment_code']]) ?>#node-<?= $task['milestone_id'] ?>" class="px-3 py-1.5 rounded-lg border border-brand text-brand hover:bg-brand/5">查看节点</a>
                                <button class="px-3 py-1.5 rounded-lg border border-slate-200 hover:border-brand/40">快速上传</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
