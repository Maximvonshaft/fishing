<?php
$shipment = $detail['shipment'];
$nodes = $detail['nodes'];
?>
<div class="space-y-6">
    <div class="bg-white border border-slate-200 rounded-3xl shadow-sm p-6">
        <nav class="text-xs text-slate-400 flex items-center gap-1">
            <a href="<?= route('projects.index') ?>" class="hover:text-brand">国家</a>
            <span>/</span>
            <a href="<?= route('shipments.index', ['country' => $shipment['country_code']]) ?>" class="hover:text-brand">批次</a>
            <span>/</span>
            <span class="text-slate-500"><?= htmlspecialchars($shipment['code']) ?></span>
        </nav>
        <div class="mt-4 flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="text-sm text-slate-500">国家 <?= htmlspecialchars($shipment['country_name'] ?? $shipment['country_code']) ?></div>
                <h1 class="text-2xl font-semibold text-slate-900 mt-1">批次 <?= htmlspecialchars($shipment['code']) ?></h1>
            </div>
            <div class="text-sm text-slate-500">
                ETA：<?= htmlspecialchars(format_datetime($shipment['eta_dest_airport'])) ?>
            </div>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-3xl shadow-sm">
        <div class="p-6 border-b border-slate-200">
            <h2 class="text-lg font-semibold text-slate-900">节点时间轴</h2>
            <p class="text-sm text-slate-500 mt-1">按照排序展示该批次的节点状态、截止时间与负责方。</p>
        </div>
        <div class="p-6 space-y-4">
            <?php foreach ($nodes as $node): ?>
                <div id="node-<?= (int) $node['id'] ?>" class="border border-slate-200 rounded-2xl p-5 flex flex-col gap-3">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <div class="text-xs text-slate-500">节点</div>
                            <div class="text-lg font-semibold text-slate-900">
                                <?= htmlspecialchars($node['name']) ?>
                                <?php if (!empty($node['restricted'])): ?>
                                    <span class="ml-2 text-xs text-slate-400">权限受限</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full border border-slate-200 text-slate-600">
                                状态：<?= htmlspecialchars($node['status']) ?>
                            </span>
                            <?php if (isset($node['vendor_name'])): ?>
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full border border-slate-200 text-slate-600">
                                    供应商：<?= htmlspecialchars($node['vendor_name']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if (isset($node['assignee_name']) && $node['assignee_name']): ?>
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full border border-slate-200 text-slate-600">
                                    账号：<?= htmlspecialchars($node['assignee_name']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if (isset($node['base_type'])): ?>
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full border border-slate-200 text-slate-600">
                                    起算：<?= htmlspecialchars(strtoupper($node['base_type'])) ?>
                                </span>
                            <?php endif; ?>
                            <?php if (isset($node['sla_hours'])): ?>
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full border border-slate-200 text-slate-600">
                                    时效：<?= htmlspecialchars((string) $node['sla_hours']) ?>h
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($node['evidence_required'])): ?>
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full border border-amber-200 text-amber-600">证据必传</span>
                            <?php endif; ?>
                            <?php if (!empty($node['signature_required'])): ?>
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full border border-emerald-200 text-emerald-600">签名必需</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (empty($node['restricted'])): ?>
                        <div class="grid gap-4 text-sm md:grid-cols-3">
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <div class="text-slate-500">SLA 截止</div>
                                <div class="text-lg font-semibold text-slate-900 mt-1">
                                    <?= $node['deadline'] ? htmlspecialchars(format_datetime($node['deadline'])) : '待起算' ?>
                                </div>
                                <?php if ($node['deadline']): ?>
                                    <div class="text-xs text-slate-400 mt-1">UTC <?= htmlspecialchars($node['deadline']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <div class="text-slate-500">剩余时间</div>
                                <div class="text-lg font-semibold mt-1">
                                    <?php if ($node['remaining_minutes'] === null): ?>
                                        <span class="text-slate-400">计算中</span>
                                    <?php elseif ($node['remaining_minutes'] >= 0): ?>
                                        <?= sprintf('%02d:%02d', floor($node['remaining_minutes'] / 60), $node['remaining_minutes'] % 60) ?>
                                    <?php else: ?>
                                        <span class="text-red-600">超时 <?= abs($node['remaining_minutes']) ?> 分钟</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <div class="text-slate-500">执行情况</div>
                                <div class="text-lg font-semibold mt-1">
                                    <?= $node['actual_time'] ? htmlspecialchars(format_datetime($node['actual_time'])) : '未提交' ?>
                                </div>
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="rounded-2xl border border-slate-200 p-4">
                                <div class="flex items-center justify-between text-sm font-semibold text-slate-700">
                                    <span>附件</span>
                                    <?php if (!empty($node['evidence_required'])): ?>
                                        <span class="text-xs <?= empty($node['files']) ? 'text-red-600' : 'text-emerald-600' ?>">
                                            <?= empty($node['files']) ? '缺少必传附件' : '附件齐全' ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($node['files'])): ?>
                                    <ul class="mt-3 space-y-2 text-sm">
                                        <?php foreach ($node['files'] as $file): ?>
                                            <li class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2">
                                                <div>
                                                    <div class="font-medium text-slate-800"><?= htmlspecialchars($file['name']) ?></div>
                                                    <div class="text-xs text-slate-400">尺寸 <?= htmlspecialchars($file['size_label']) ?> · 指纹 <?= htmlspecialchars($file['sha256_prefix']) ?></div>
                                                </div>
                                                <a class="text-brand text-xs hover:underline" href="<?= htmlspecialchars($file['download_url']) ?>">下载</a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="mt-3 text-sm text-slate-500">暂无附件。</p>
                                <?php endif; ?>
                            </div>
                            <div class="rounded-2xl border border-slate-200 p-4">
                                <div class="flex items-center justify-between text-sm font-semibold text-slate-700">
                                    <span>签名</span>
                                    <?php if (!empty($node['signature_required'])): ?>
                                        <span class="text-xs <?= empty($node['signatures']) ? 'text-red-600' : 'text-emerald-600' ?>">
                                            <?= empty($node['signatures']) ? '等待签名' : '已签署' ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($node['signatures'])): ?>
                                    <ul class="mt-3 space-y-2 text-sm">
                                        <?php foreach ($node['signatures'] as $signature): ?>
                                            <li class="rounded-xl bg-slate-50 px-3 py-2">
                                                <div class="font-medium text-slate-800"><?= htmlspecialchars($signature['signer_name']) ?> <span class="text-xs text-slate-400">(<?= htmlspecialchars(strtoupper($signature['method'])) ?>)</span></div>
                                                <div class="text-xs text-slate-400 mt-1">账号：<?= htmlspecialchars($signature['display_name']) ?> · 时间：<?= htmlspecialchars(format_datetime($signature['created_at'])) ?></div>
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
                                    <p class="mt-3 text-sm text-slate-500">暂无签名记录。</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-slate-500">该节点对当前账号只读，无法查看详情。</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
