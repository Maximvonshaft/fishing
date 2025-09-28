<?php
$displayTz = config('app.display_timezone', 'Europe/Tirane');
?>
<div class="bg-white border border-slate-200 rounded-3xl shadow-sm">
    <div class="p-6 border-b border-slate-200 space-y-4">
        <div>
            <div class="text-sm text-slate-500">供应商工作台</div>
            <div class="text-lg font-semibold text-slate-900">我的待办节点</div>
        </div>
    </div>
    <div class="p-6">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                    <tr>
                        <th class="px-4 py-3 text-left">节点</th>
                        <th class="px-4 py-3 text-left">批次 / 国家</th>
                        <th class="px-4 py-3 text-left">截止时间 (<?= htmlspecialchars($displayTz) ?>)</th>
                        <th class="px-4 py-3 text-left">倒计时</th>
                        <th class="px-4 py-3 text-left">状态</th>
                        <th class="px-4 py-3 text-left">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($tasks as $task): ?>
                    <?php
                    $deadlineLocal = $task['deadline_utc'] ? format_datetime($task['deadline_utc']) : '待定';
                    $remaining = $task['remaining_minutes'];
                    $badgeClass = match ($task['sla_status']) {
                        'BREACH' => 'border-red-200 text-red-600 bg-red-50',
                        'WARN' => 'border-amber-200 text-amber-600 bg-amber-50',
                        'OK' => 'border-emerald-200 text-emerald-600 bg-emerald-50',
                        default => 'border-slate-200 text-slate-600 bg-slate-50'
                    };
                    ?>
                    <tr class="hover:bg-brand/5">
                        <td class="px-4 py-4 font-semibold text-slate-900">
                            <?= htmlspecialchars($task['node_name']) ?>
                            <?php if (!empty($task['required_actions'])): ?>
                                <div class="mt-1 flex flex-wrap gap-2 text-xs">
                                    <?php foreach ($task['required_actions'] as $action): ?>
                                        <?php if ($action === 'UPLOAD_EVIDENCE'): ?>
                                            <span class="inline-flex items-center gap-1 rounded-full border border-amber-200 px-2 py-0.5 text-amber-600">需上传附件</span>
                                        <?php elseif ($action === 'SIGN'): ?>
                                            <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 px-2 py-0.5 text-emerald-600">需签名</span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-4 text-slate-600">
                            <div><?= htmlspecialchars($task['shipment_code']) ?></div>
                            <div class="text-xs text-slate-400">国家：<?= htmlspecialchars($task['country']) ?></div>
                        </td>
                        <td class="px-4 py-4">
                            <?php if ($task['deadline_utc']): ?>
                                <div><?= htmlspecialchars($deadlineLocal) ?></div>
                                <div class="text-xs text-slate-400">UTC <?= htmlspecialchars($task['deadline_utc']) ?></div>
                            <?php else: ?>
                                <div class="text-slate-400">待起算</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-4 font-semibold <?= $remaining < 0 ? 'text-red-600' : 'text-slate-900' ?>">
                            <?php if ($remaining === null): ?>
                                <span class="text-slate-400">计算中</span>
                            <?php elseif ($remaining >= 0): ?>
                                <?= sprintf('%02d:%02d', floor($remaining / 60), $remaining % 60) ?>
                            <?php else: ?>
                                超时 <?= abs($remaining) ?> 分钟
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-4">
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full border <?= $badgeClass ?>">
                                <?= htmlspecialchars($task['sla_status']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-4">
                            <a href="<?= htmlspecialchars(route('tasks.show', ['node_id' => $task['node_id']])) ?>"
                               class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-brand text-brand hover:bg-brand/5">
                                处理
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($tasks)): ?>
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">当前没有待办节点。</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
