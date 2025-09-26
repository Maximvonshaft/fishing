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

    <div class="bg-white border border-slate-200 rounded-3xl shadow-sm p-6 space-y-6">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">提交节点完成</h3>
            <p class="text-sm text-slate-500 mt-1">填写完成时间并按要求上传附件、签名。系统会自动校验是否满足模板约束。</p>
        </div>

        <div class="space-y-4">
            <div>
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-slate-700">已上传附件</h4>
                    <?php if ($node['evidence_required']): ?>
                        <span class="text-xs <?= empty($node['files']) ? 'text-red-600' : 'text-emerald-600' ?>">
                            <?= empty($node['files']) ? '至少需上传 1 个附件' : '已满足附件要求' ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($node['files'])): ?>
                    <ul class="mt-2 space-y-2">
                        <?php foreach ($node['files'] as $file): ?>
                            <li class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-2 text-sm">
                                <div>
                                    <div class="font-medium text-slate-800"><?= htmlspecialchars($file['name']) ?></div>
                                    <div class="text-xs text-slate-400">尺寸 <?= htmlspecialchars($file['size_label']) ?> · 指纹 <?= htmlspecialchars($file['sha256_prefix']) ?></div>
                                </div>
                                <a class="inline-flex items-center gap-1 text-brand hover:underline" href="<?= htmlspecialchars($file['download_url']) ?>">
                                    下载
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="mt-2 text-sm text-slate-500">尚未上传附件。</p>
                <?php endif; ?>
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-slate-700">签名记录</h4>
                    <?php if ($node['signature_required']): ?>
                        <span class="text-xs <?= empty($node['signatures']) ? 'text-red-600' : 'text-emerald-600' ?>">
                            <?= empty($node['signatures']) ? '需完成签名' : '已签署' ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($node['signatures'])): ?>
                    <ul class="mt-2 space-y-2 text-sm">
                        <?php foreach ($node['signatures'] as $signature): ?>
                            <li class="rounded-xl border border-slate-200 px-4 py-2">
                                <div class="font-medium text-slate-800"><?= htmlspecialchars($signature['signer_name']) ?> <span class="text-xs text-slate-400">(<?= htmlspecialchars(strtoupper($signature['method'])) ?>)</span></div>
                                <div class="text-xs text-slate-400 mt-1">签署人：<?= htmlspecialchars($signature['display_name']) ?> · 时间：<?= htmlspecialchars(format_datetime($signature['created_at'])) ?></div>
                                <?php if (!empty($signature['ip'])): ?>
                                    <div class="text-xs text-slate-400">IP：<?= htmlspecialchars($signature['ip']) ?></div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="mt-2 text-sm text-slate-500">尚未签名。</p>
                <?php endif; ?>
            </div>
        </div>

        <form method="post" enctype="multipart/form-data" class="space-y-5" action="<?= htmlspecialchars(route('tasks.show', ['node_id' => $node['id']])) ?>">
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
            <div>
                <label class="block text-sm font-medium text-slate-600" for="evidences">上传附件<?= $node['evidence_required'] ? ' (至少 1 个)' : '' ?></label>
                <input type="file" id="evidences" name="evidences[]" multiple accept=".pdf,.jpg,.jpeg,.png"
                       class="mt-1 block w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                <p class="text-xs text-slate-400 mt-1">支持 pdf/jpg/jpeg/png，每个文件不超过 20MB。</p>
                <?php if ($node['evidence_required'] && empty($node['files'])): ?>
                    <p class="text-xs text-red-500 mt-1">完成前需至少上传一个附件。</p>
                <?php endif; ?>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600" for="signature_value">签名<?= $node['signature_required'] ? ' (必填)' : '' ?></label>
                <input type="text" id="signature_value" name="signature_value" placeholder="请输入签名姓名或 PIN"
                       value="<?= htmlspecialchars($user['display_name'] ?? '') ?>"
                       class="mt-1 block w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                <input type="hidden" name="signature_method" value="pin">
                <p class="text-xs text-slate-400 mt-1">系统会记录账号、IP 与时间，便于审计。</p>
                <?php if ($node['signature_required'] && empty($node['signatures'])): ?>
                    <p class="text-xs text-red-500 mt-1">该节点要求签名确认。</p>
                <?php endif; ?>
            </div>
            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-brand text-white font-medium hover:bg-brand/90">
                标记为完成
            </button>
        </form>
    </div>
</div>
