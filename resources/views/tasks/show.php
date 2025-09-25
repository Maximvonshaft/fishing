<?php
$displayTz = config('app.display_timezone', 'Europe/Tirane');
$deadlineLocal = $node['deadline_utc'] ? format_datetime($node['deadline_utc'], $displayTz) : null;
?>
<div class="max-w-3xl mx-auto space-y-6">
    <div class="bg-white border border-slate-200 rounded-3xl shadow-sm p-6">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-slate-500">批次 <?= htmlspecialchars($node['code']) ?> · <?= htmlspecialchars($node['country']) ?></div>
                <h2 class="text-xl font-semibold text-slate-900 mt-2"><?= htmlspecialchars($node['name']) ?></h2>
            </div>
            <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full border border-slate-200 text-slate-600 text-sm">
                责任方：<?= htmlspecialchars($node['vendor_name']) ?>
            </span>
        </div>
        <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6 text-sm">
            <div class="p-4 rounded-2xl bg-slate-50">
                <dt class="text-slate-500">SLA 截止 (<?= htmlspecialchars($displayTz) ?>)</dt>
                <dd class="text-lg font-semibold text-slate-900 mt-1">
                    <?= $deadlineLocal ? htmlspecialchars($deadlineLocal) : '待起算' ?>
                </dd>
                <?php if ($node['deadline_utc']): ?>
                <dd class="text-xs text-slate-400 mt-1">UTC <?= htmlspecialchars($node['deadline_utc']) ?></dd>
                <?php endif; ?>
            </div>
            <div class="p-4 rounded-2xl bg-slate-50">
                <dt class="text-slate-500">当前状态</dt>
                <dd class="text-lg font-semibold mt-1">
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-brand/40 text-brand">
                        <?= htmlspecialchars($node['status']) ?>
                    </span>
                </dd>
            </div>
            <div class="p-4 rounded-2xl bg-slate-50">
                <dt class="text-slate-500">剩余时间</dt>
                <dd class="text-lg font-semibold mt-1">
                    <?php if ($node['remaining_minutes'] === null): ?>
                        <span class="text-slate-400">计算中</span>
                    <?php elseif ($node['remaining_minutes'] >= 0): ?>
                        <?= sprintf('%02d:%02d', floor($node['remaining_minutes'] / 60), $node['remaining_minutes'] % 60) ?>
                    <?php else: ?>
                        <span class="text-red-600">超时 <?= abs($node['remaining_minutes']) ?> 分钟</span>
                    <?php endif; ?>
                </dd>
            </div>
        </dl>
    </div>

    <div class="bg-white border border-slate-200 rounded-3xl shadow-sm p-6">
        <h3 class="text-lg font-semibold text-slate-900">提交节点完成</h3>
        <p class="text-sm text-slate-500 mt-1">填写完成时间，必要时上传凭证或签名（后续版本支持）。</p>
        <form method="post" class="mt-6 space-y-5" action="<?= htmlspecialchars(route('tasks.show', ['node_id' => $node['id']])) ?>">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="_action" value="node_complete">
            <input type="hidden" name="node_id" value="<?= (int) $node['id'] ?>">
            <div>
                <label class="block text-sm font-medium text-slate-600" for="actual_time">实际完成时间 (UTC ISO8601)</label>
                <input type="datetime-local" id="actual_time" name="actual_time" required
                       value="<?= htmlspecialchars(str_replace(' ', 'T', $node['actual_time'] ?? '')) ?>"
                       class="mt-1 block w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                <p class="text-xs text-slate-400 mt-1">系统统一存储 UTC，可在字段旁粘贴 ISO8601。</p>
            </div>
            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-brand text-white font-medium hover:bg-brand/90">
                标记为完成
            </button>
        </form>
    </div>
</div>
