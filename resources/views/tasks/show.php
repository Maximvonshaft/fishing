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
                                <?php if (!empty($signature['image_url'])): ?>
                                    <div class="mt-3">
                                        <img src="<?= htmlspecialchars($signature['image_url']) ?>" alt="签名图像" class="h-24 w-auto max-w-full rounded-lg border border-slate-200 bg-white object-contain">
                                        <?php if (!empty($signature['image_sha256_prefix'])): ?>
                                            <div class="text-xs text-slate-400 mt-1">指纹 <?= htmlspecialchars($signature['image_sha256_prefix']) ?></div>
                                        <?php endif; ?>
                                    </div>
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
                <label class="block text-sm font-medium text-slate-600">手写签名<?= $node['signature_required'] ? ' (必填)' : '' ?></label>
                <div class="mt-2 rounded-xl border border-slate-300 bg-white p-3">
                    <canvas id="signature_canvas"
                            class="block w-full touch-manipulation"
                            style="aspect-ratio: 5 / 2; touch-action: none;"
                            width="900"
                            height="360"></canvas>
                    <div class="mt-2 flex items-center justify-between text-xs text-slate-400">
                        <span>使用鼠标或触控板手写签名，提交时会保存为图像并记录审计信息。</span>
                        <button type="button" id="signature_clear" class="text-brand hover:underline">清除重写</button>
                    </div>
                </div>
                <input type="hidden" name="signature_draw_data" id="signature_draw_data" value="">
                <input type="hidden" name="signature_method" value="draw">
                <p class="text-xs text-slate-400 mt-1">系统会记录账号、IP 与时间，便于审计。</p>
                <?php if ($node['signature_required'] && empty($node['signatures'])): ?>
                    <p class="text-xs text-red-500 mt-1">该节点要求完成手写签名。</p>
                <?php endif; ?>
            </div>
            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-brand text-white font-medium hover:bg-brand/90">
                标记为完成
            </button>
        </form>
    </div>
</div>

<script>
(function () {
    const canvas = document.getElementById('signature_canvas');
    const clearBtn = document.getElementById('signature_clear');
    const hiddenInput = document.getElementById('signature_draw_data');
    const form = document.querySelector('form[action*="node_id=<?= (int) $node['id'] ?>"]') || document.querySelector('form');
    if (!canvas || !clearBtn || !hiddenInput || !form) {
        return;
    }

    const ctx = canvas.getContext('2d');
    const baseWidth = canvas.width;
    const baseHeight = canvas.height;
    const ratio = window.devicePixelRatio || 1;
    if (ratio !== 1) {
        canvas.width = baseWidth * ratio;
        canvas.height = baseHeight * ratio;
        ctx.scale(ratio, ratio);
    }
    canvas.style.width = '100%';
    canvas.style.height = '';
    ctx.lineWidth = 2;
    ctx.lineJoin = 'round';
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#1f2937';
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, baseWidth, baseHeight);
    ctx.fillStyle = '#1f2937';

    let drawing = false;
    let hasStroke = false;
    let lastPoint = null;

    function pointerPosition(event) {
        const rect = canvas.getBoundingClientRect();
        const scaleX = baseWidth / rect.width;
        const scaleY = baseHeight / rect.height;
        return {
            x: (event.clientX - rect.left) * scaleX,
            y: (event.clientY - rect.top) * scaleY,
        };
    }

    function startDrawing(event) {
        event.preventDefault();
        drawing = true;
        const {x, y} = pointerPosition(event);
        lastPoint = {x, y};
        if (typeof canvas.setPointerCapture === 'function') {
            canvas.setPointerCapture(event.pointerId);
        }
        ctx.beginPath();
        ctx.moveTo(x, y);
    }

    function draw(event) {
        if (!drawing) {
            return;
        }
        event.preventDefault();
        const {x, y} = pointerPosition(event);
        ctx.lineTo(x, y);
        ctx.stroke();
        hasStroke = true;
        lastPoint = {x, y};
    }

    function endDrawing(event) {
        if (!drawing) {
            return;
        }
        event.preventDefault();
        drawing = false;
        if (!hasStroke && lastPoint) {
            ctx.beginPath();
            ctx.arc(lastPoint.x, lastPoint.y, 1.5, 0, Math.PI * 2);
            ctx.fill();
            ctx.beginPath();
            hasStroke = true;
        }
        lastPoint = null;
        if (event && typeof canvas.releasePointerCapture === 'function') {
            try {
                canvas.releasePointerCapture(event.pointerId);
            } catch (e) {
                // ignore
            }
        }
        ctx.closePath();
    }

    canvas.addEventListener('pointerdown', startDrawing);
    canvas.addEventListener('pointermove', draw);
    canvas.addEventListener('pointerup', endDrawing);
    canvas.addEventListener('pointerleave', endDrawing);
    canvas.addEventListener('pointercancel', endDrawing);

    clearBtn.addEventListener('click', function (event) {
        event.preventDefault();
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, baseWidth, baseHeight);
        ctx.fillStyle = '#1f2937';
        hasStroke = false;
        hiddenInput.value = '';
    });

    form.addEventListener('submit', function () {
        if (hasStroke) {
            hiddenInput.value = canvas.toDataURL('image/png');
        } else {
            hiddenInput.value = '';
        }
    });
})();
</script>
