<nav class="text-xs text-slate-400 flex items-center gap-1 mb-4">
    <a href="<?= route('projects.index') ?>" class="hover:text-brand">国家</a>
    <span>/</span>
    <a href="<?= route('shipments.index') ?>" class="hover:text-brand">批次列表</a>
    <span>/</span>
    <span class="text-slate-500">批次详情</span>
</nav>

<div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
    <div class="space-y-4">
        <?php foreach ($shipment['milestones'] as $milestone): ?>
            <?php
            $deadline = formatUtcToLocal($milestone['deadline']);
            $statusBadge = statusColor($milestone['status']);
            $statusDot = statusDotColor($milestone['status']);
            $remainingMinutes = $milestone['remaining_minutes'];
            $remainingText = $remainingMinutes >= 0 ? sprintf('%02d:%02d', floor($remainingMinutes / 60), $remainingMinutes % 60) : sprintf('- %d 分钟', abs($remainingMinutes));
            ?>
            <section class="bg-white border border-slate-200 rounded-3xl shadow-sm overflow-hidden" x-data="{openExplain: false, openUpload: false}">
                <header class="px-6 py-4 border-b border-slate-200 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="flex items-center gap-3">
                            <span class="w-2 h-2 rounded-full <?= $statusDot ?>"></span>
                            <h2 class="text-lg font-semibold text-slate-900"><?= htmlspecialchars($milestone['name']) ?></h2>
                            <span class="text-xs px-2 py-1 rounded-full border <?= $statusBadge ?>">
                                <?= htmlspecialchars($milestone['status']) ?>
                            </span>
                        </div>
                        <?php if (!empty($milestone['requires'])): ?>
                            <div class="text-xs text-amber-600 mt-2">待完成：<?= implode('、', $milestone['requires']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-col items-start md:items-end gap-1 text-sm text-slate-500">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-slate-900">倒计时 <?= $remainingText ?></span>
                            <span class="text-xs text-slate-400">每 60 秒刷新</span>
                        </div>
                        <div>截止 <?= $deadline['local'] ?> <span class="text-xs text-slate-400">UTC <?= $deadline['utc'] ?></span></div>
                        <button @click="openExplain = true" class="text-brand text-xs">查看解释</button>
                    </div>
                </header>

                <div class="px-6 py-4 grid gap-6 lg:grid-cols-2">
                    <div class="space-y-4">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-700">时间记录</h3>
                            <dl class="mt-2 grid grid-cols-2 gap-3 text-sm text-slate-600">
                                <div>
                                    <dt class="text-xs text-slate-400">申报时间 (asserted)</dt>
                                    <dd><?= $milestone['asserted_time'] ? formatUtcToLocal($milestone['asserted_time'])['local'] : '—' ?></dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-slate-400">观察时间 (observed)</dt>
                                    <dd><?= $milestone['observed_time'] ? formatUtcToLocal($milestone['observed_time'])['local'] : '—' ?></dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-slate-400">核验时间 (verified)</dt>
                                    <dd><?= $milestone['verified_time'] ? formatUtcToLocal($milestone['verified_time'])['local'] : '—' ?></dd>
                                </div>
                            </dl>
                        </div>

                        <div>
                            <h3 class="text-sm font-semibold text-slate-700">字段数据</h3>
                            <div class="mt-2 space-y-2 text-sm text-slate-600">
                                <?php if ($milestone['data']): ?>
                                    <?php foreach ($milestone['data'] as $item): ?>
                                        <div class="flex items-center justify-between gap-4">
                                            <span class="text-slate-500"><?= htmlspecialchars($item['label']) ?></span>
                                            <span class="font-medium text-slate-900"><?= htmlspecialchars($item['value']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-slate-400">尚未填写字段，点击填写时间自动展开。</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-sm font-semibold text-slate-700">证据</h3>
                            <div class="mt-3 space-y-3">
                                <?php if ($milestone['evidences']): ?>
                                    <?php foreach ($milestone['evidences'] as $evidence): ?>
                                        <div class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 px-4 py-3">
                                            <div>
                                                <div class="font-medium text-slate-900"><?= htmlspecialchars($evidence['name']) ?></div>
                                                <div class="text-xs text-slate-500">大小 <?= htmlspecialchars($evidence['size']) ?> · 哈希 <?= htmlspecialchars($evidence['sha256']) ?></div>
                                            </div>
                                            <div class="flex items-center gap-3 text-xs">
                                                <button class="px-3 py-1 rounded-lg border border-slate-200 hover:border-brand/40">预览</button>
                                                <button class="px-3 py-1 rounded-lg border border-slate-200 <?php if (!$evidence['downloadable']): ?>opacity-50 cursor-not-allowed<?php endif; ?>">下载</button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="rounded-xl border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-500">
                                        尚无证据，点击上传添加文件。
                                    </div>
                                <?php endif; ?>
                                <?php if ($milestone['permissions']['can_upload']): ?>
                                    <button @click="openUpload = true" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-brand text-brand hover:bg-brand/5">
                                        上传证据
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="border border-slate-200 rounded-2xl p-4">
                            <h3 class="text-sm font-semibold text-slate-700 mb-3">填写时间</h3>
                            <form class="space-y-3">
                                <div class="flex flex-col gap-1 text-sm">
                                    <label class="text-slate-500">观察时间</label>
                                    <input type="datetime-local" class="rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                                    <span class="text-xs text-slate-400">显示 Europe/Tirane，提交转换为 UTC</span>
                                </div>
                                <div class="flex flex-col gap-1 text-sm">
                                    <label class="text-slate-500">备注</label>
                                    <textarea rows="3" class="rounded-lg border-slate-300 focus:border-brand focus:ring-brand" placeholder="补充说明 / 退回原因"></textarea>
                                </div>
                                <?php if ($milestone['signature_required']): ?>
                                    <div class="border border-slate-200 rounded-xl p-3 bg-slate-50">
                                        <div class="flex items-center justify-between text-sm text-slate-600">
                                            <span>签字</span>
                                            <button class="px-3 py-1 text-xs border border-brand text-brand rounded-lg">手写签名</button>
                                        </div>
                                        <div class="mt-3 h-24 border border-dashed border-slate-300 rounded-lg flex items-center justify-center text-xs text-slate-400">
                                            手写板 / PIN 输入区域
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="flex flex-wrap gap-2 text-sm">
                                    <?php if ($milestone['permissions']['can_edit']): ?>
                                        <button class="inline-flex items-center gap-2 bg-brand text-white px-4 py-2 rounded-lg">提交</button>
                                        <button class="inline-flex items-center gap-2 border border-slate-200 px-4 py-2 rounded-lg">撤回</button>
                                    <?php endif; ?>
                                    <?php if ($milestone['permissions']['can_verify']): ?>
                                        <button class="inline-flex items-center gap-2 border border-emerald-500 text-emerald-600 px-4 py-2 rounded-lg">审核通过</button>
                                        <button class="inline-flex items-center gap-2 border border-rose-500 text-rose-600 px-4 py-2 rounded-lg">退回补件</button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                        <div class="border border-slate-200 rounded-2xl p-4">
                            <h3 class="text-sm font-semibold text-slate-700 mb-3">签字记录</h3>
                            <div class="space-y-3 text-sm">
                                <?php if ($milestone['signatures']): ?>
                                    <?php foreach ($milestone['signatures'] as $signature): ?>
                                        <div class="p-3 rounded-xl border border-slate-200 bg-slate-50">
                                            <div class="font-semibold text-slate-900"><?= htmlspecialchars($signature['user_name']) ?> · <?= htmlspecialchars($signature['method']) ?></div>
                                            <div class="text-xs text-slate-500 mt-1">时间 <?= formatUtcToLocal($signature['time'])['local'] ?> · IP <?= htmlspecialchars($signature['ip']) ?></div>
                                            <div class="text-xs text-slate-400">设备 <?= htmlspecialchars($signature['device']) ?> · 地理 <?= htmlspecialchars($signature['geo'] ?? '—') ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-slate-400">尚无签字。</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <footer class="px-6 py-4 border-t border-slate-100 bg-slate-50 text-xs text-slate-500 flex flex-wrap gap-3">
                    <div>责任供应商：<?= htmlspecialchars($milestone['assignment']['vendor']) ?> · <?= htmlspecialchars($milestone['assignment']['contact_name']) ?> <?= htmlspecialchars($milestone['assignment']['contact_phone']) ?></div>
                    <div>联系人：<a href="mailto:<?= htmlspecialchars($milestone['assignment']['contact_email']) ?>" class="text-brand"><?= htmlspecialchars($milestone['assignment']['contact_email']) ?></a></div>
                    <div>要求字段：<?= implode('、', $milestone['required_fields']) ?></div>
                </footer>

                <div x-show="openExplain" style="display: none" class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/50">
                    <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl p-6 space-y-4" @click.away="openExplain = false">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-slate-900">SLA 解释</h3>
                            <button class="text-slate-400" @click="openExplain = false">✕</button>
                        </div>
                        <div class="text-sm text-slate-600 space-y-2">
                            <div><span class="font-semibold text-slate-700">起算</span>：<?= htmlspecialchars($milestone['explain']) ?></div>
                            <div><span class="font-semibold text-slate-700">日历</span>：24×7 · Europe/Tirane</div>
                            <div><span class="font-semibold text-slate-700">宽限</span>：15 分钟 · 黄灯阈值 12 小时</div>
                            <div><span class="font-semibold text-slate-700">当前状态</span>：<?= htmlspecialchars($milestone['status']) ?> · 剩余 <?= $remainingText ?></div>
                        </div>
                        <button class="w-full inline-flex justify-center items-center gap-2 border border-slate-200 rounded-lg py-2 hover:border-brand/40" @click="openExplain = false">关闭</button>
                    </div>
                </div>

                <div x-show="openUpload" style="display: none" class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/50">
                    <div class="bg-white rounded-2xl shadow-xl w-full max-w-xl p-6 space-y-4" @click.away="openUpload = false">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-slate-900">上传证据</h3>
                            <button class="text-slate-400" @click="openUpload = false">✕</button>
                        </div>
                        <div class="border border-dashed border-slate-200 rounded-2xl p-6 text-center text-sm text-slate-500">
                            拖拽或点击上传 PDF/JPG/PNG（≤20MB），自动校验哈希与 MIME。
                        </div>
                        <div class="text-xs text-slate-400">与模板要求对比：需要上传 NOA 文件，签字角色 transit_agent。</div>
                        <div class="flex items-center justify-end gap-2">
                            <button class="px-4 py-2 border border-slate-200 rounded-lg" @click="openUpload = false">取消</button>
                            <button class="px-4 py-2 bg-brand text-white rounded-lg">完成上传</button>
                        </div>
                    </div>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <aside class="space-y-4">
        <div class="bg-white border border-slate-200 rounded-3xl shadow-sm p-6 space-y-3">
            <h3 class="text-sm font-semibold text-slate-700">批次信息</h3>
            <div class="text-sm text-slate-600 space-y-2">
                <div class="flex justify-between"><span class="text-slate-500">批次编码</span><span class="font-semibold text-slate-900"><?= htmlspecialchars($shipment['code']) ?></span></div>
                <div class="flex justify-between"><span class="text-slate-500">承运</span><span class="font-semibold text-slate-900"><?= htmlspecialchars($shipment['carrier']) ?></span></div>
                <div class="flex justify-between"><span class="text-slate-500">件数 / 重量</span><span class="font-semibold text-slate-900"><?= htmlspecialchars($shipment['pieces']) ?> · <?= htmlspecialchars($shipment['weight']) ?></span></div>
                <div class="flex justify-between"><span class="text-slate-500">ETD</span><span><?= formatUtcToLocal($shipment['etd'])['local'] ?></span></div>
                <div class="flex justify-between"><span class="text-slate-500">ETA</span><span><?= formatUtcToLocal($shipment['eta'])['local'] ?></span></div>
            </div>
        </div>
        <div class="bg-white border border-slate-200 rounded-3xl shadow-sm p-6 space-y-3">
            <h3 class="text-sm font-semibold text-slate-700">分派面板</h3>
            <div class="space-y-3 text-sm text-slate-600">
                <?php foreach ($shipment['milestones'] as $milestone): ?>
                    <div class="border border-slate-200 rounded-2xl p-3">
                        <div class="flex items-center justify-between">
                            <div class="font-semibold text-slate-900 text-sm"><?= htmlspecialchars($milestone['name']) ?></div>
                            <button class="text-xs text-brand">改派</button>
                        </div>
                        <div class="text-xs text-slate-500 mt-1">供应商：<?= htmlspecialchars($milestone['assignment']['vendor']) ?></div>
                        <div class="text-xs text-slate-500">联系人：<?= htmlspecialchars($milestone['assignment']['contact_name']) ?> · <?= htmlspecialchars($milestone['assignment']['contact_phone']) ?></div>
                        <button class="mt-2 text-xs px-3 py-1 rounded-lg border border-slate-200 hover:border-brand/40">应用到后续节点</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="bg-white border border-slate-200 rounded-3xl shadow-sm p-6 space-y-3">
            <h3 class="text-sm font-semibold text-slate-700">通知与提醒</h3>
            <div class="space-y-2 text-sm text-slate-600">
                <div class="flex items-center gap-2">
                    <select class="flex-1 rounded-lg border-slate-300 focus:border-brand focus:ring-brand text-sm">
                        <option>黄灯提醒</option>
                        <option>红灯告警</option>
                        <option>退回补件</option>
                    </select>
                    <button class="px-4 py-2 rounded-lg border border-brand text-brand hover:bg-brand/5">立即提醒</button>
                </div>
                <div class="text-xs text-slate-400">邮件 + IM 双通道，记录在事件日志。</div>
            </div>
        </div>
        <div class="bg-white border border-slate-200 rounded-3xl shadow-sm p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-700">事件日志</h3>
                <button class="text-xs text-brand">查看更多</button>
            </div>
            <ol class="space-y-3 text-sm text-slate-600">
                <li class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-full bg-brand/10 text-brand flex items-center justify-center text-xs font-semibold">DH</div>
                    <div>
                        <div class="font-semibold text-slate-900">审核通过</div>
                        <div class="text-xs text-slate-400">2025-01-16 11:15</div>
                        <div class="text-xs text-slate-500">节点 NOA_ISSUED 核验并写入 verified_time。</div>
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center text-xs font-semibold">系统</div>
                    <div>
                        <div class="font-semibold text-slate-900">SLA 黄灯</div>
                        <div class="text-xs text-slate-400">2025-01-16 09:30</div>
                        <div class="text-xs text-slate-500">节点 BBL_HANDOVER_DONE 剩余 2 小时触发通知。</div>
                    </div>
                </li>
            </ol>
        </div>
    </aside>
</div>
