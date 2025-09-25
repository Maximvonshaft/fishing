<div class="grid gap-6 lg:grid-cols-[320px_minmax(0,1fr)]">
    <aside class="bg-white border border-slate-200 rounded-3xl shadow-sm p-6 space-y-4">
        <div>
            <h3 class="text-sm font-semibold text-slate-700">国家选择</h3>
            <select class="mt-2 w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand text-sm">
                <?php foreach ($templates as $code => $template): ?>
                    <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($template['country']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <h3 class="text-sm font-semibold text-slate-700">版本列表</h3>
            <div class="mt-3 space-y-2 text-sm text-slate-600">
                <?php foreach ($templates['ALB']['versions'] as $version): ?>
                    <div class="border border-slate-200 rounded-2xl p-3 flex items-center justify-between">
                        <div>
                            <div class="font-semibold text-slate-900"><?= htmlspecialchars($version['version']) ?></div>
                            <div class="text-xs text-slate-400">发布 <?= htmlspecialchars($version['published_at']) ?></div>
                        </div>
                        <button class="text-xs text-brand">回滚</button>
                    </div>
                <?php endforeach; ?>
                <button class="w-full px-4 py-2 border border-dashed border-brand text-brand rounded-lg">发布新版本</button>
            </div>
        </div>
    </aside>

    <section class="bg-white border border-slate-200 rounded-3xl shadow-sm">
        <header class="p-6 border-b border-slate-200 flex flex-col gap-2">
            <div class="text-sm text-slate-500">节点清单 · 拖拽排序</div>
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-900">Albania 模板 v1.2</h2>
                <div class="flex items-center gap-2 text-sm">
                    <button class="px-4 py-2 rounded-lg border border-slate-200 hover:border-brand/40">导入 JSON</button>
                    <button class="px-4 py-2 rounded-lg border border-brand text-brand hover:bg-brand/5">导出配置</button>
                </div>
            </div>
        </header>
        <div class="p-6" x-data="{selected: null}">
            <div class="grid gap-3 md:grid-cols-2">
                <?php foreach ($templates['ALB']['milestones'] as $milestone): ?>
                    <div class="border border-slate-200 rounded-2xl p-4 hover:border-brand/40" @click="selected = <?= $milestone['id'] ?>">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-xs text-slate-400 uppercase">code: <?= htmlspecialchars($milestone['code']) ?></div>
                                <div class="text-sm font-semibold text-slate-900"><?= htmlspecialchars($milestone['name']) ?></div>
                            </div>
                            <button class="text-xs text-brand">编辑</button>
                        </div>
                        <div class="mt-2 text-xs text-slate-500">依赖：<?= $milestone['requires'] ? implode('、', $milestone['requires']) : '无' ?></div>
                        <div class="mt-2 flex flex-wrap gap-2 text-xs">
                            <?php foreach ($milestone['required_fields'] as $field): ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full bg-brand/10 text-brand font-medium"><?= htmlspecialchars($field) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-6" x-show="selected" style="display: none" x-transition>
                <div class="border border-brand rounded-3xl p-6 bg-brand/5">
                    <h3 class="text-lg font-semibold text-brand mb-4">节点配置 · <span x-text="selected"></span></h3>
                    <div class="grid gap-6 lg:grid-cols-2 text-sm">
                        <div class="space-y-4">
                            <div>
                                <h4 class="font-semibold text-slate-700">基本信息</h4>
                                <div class="mt-2 space-y-2">
                                    <label class="block">节点名称 <input class="mt-1 w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand" value="示例节点"></label>
                                    <label class="block">依赖节点 <input class="mt-1 w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand" placeholder="NOA_ISSUED"></label>
                                </div>
                            </div>
                            <div>
                                <h4 class="font-semibold text-slate-700">表单字段</h4>
                                <div class="mt-2 space-y-2">
                                    <div class="border border-slate-200 rounded-2xl p-3">
                                        <div class="font-semibold text-slate-900">NOA 编号</div>
                                        <div class="text-xs text-slate-500">必填 · 文本 · 正则 /^[A-Z0-9-]+$/</div>
                                    </div>
                                    <button class="px-3 py-1.5 text-xs rounded-lg border border-brand text-brand">新增字段</button>
                                </div>
                            </div>
                            <div>
                                <h4 class="font-semibold text-slate-700">证据要求</h4>
                                <div class="mt-2 space-y-2">
                                    <div class="border border-slate-200 rounded-2xl p-3">
                                        <div class="font-semibold text-slate-900">NOA 文件</div>
                                        <div class="text-xs text-slate-500">PDF · 必传 · 哈希入库</div>
                                    </div>
                                    <button class="px-3 py-1.5 text-xs rounded-lg border border-brand text-brand">新增证据</button>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <h4 class="font-semibold text-slate-700">签字与分派</h4>
                                <div class="mt-2 space-y-2">
                                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="rounded border-slate-300" checked> 签字必需</label>
                                    <label class="block">签署角色 <input class="mt-1 w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand" value="transit_agent"></label>
                                    <label class="block">分派建议 <input class="mt-1 w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand" value="Transit Albania"></label>
                                </div>
                            </div>
                            <div>
                                <h4 class="font-semibold text-slate-700">SLA 配置</h4>
                                <div class="mt-2 space-y-3">
                                    <div class="border border-slate-200 rounded-2xl p-3">
                                        <div class="text-xs text-slate-500 uppercase">起算</div>
                                        <div class="font-semibold text-slate-900">prefer: observed_time → fallback: shipment.eta</div>
                                    </div>
                                    <div class="border border-slate-200 rounded-2xl p-3">
                                        <div class="text-xs text-slate-500 uppercase">截止</div>
                                        <div class="font-semibold text-slate-900">start + 24h</div>
                                    </div>
                                    <div class="border border-slate-200 rounded-2xl p-3">
                                        <div class="text-xs text-slate-500 uppercase">日历</div>
                                        <div class="font-semibold text-slate-900">24×7 · Europe/Tirane</div>
                                    </div>
                                    <div class="border border-slate-200 rounded-2xl p-3">
                                        <div class="text-xs text-slate-500 uppercase">宽限 & 黄灯</div>
                                        <div class="font-semibold text-slate-900">15 分钟宽限 · 黄灯阈值 12 小时</div>
                                    </div>
                                    <div class="border border-dashed border-brand rounded-2xl p-4 bg-white">
                                        <div class="font-semibold text-slate-900">沙盘预览</div>
                                        <div class="text-xs text-slate-500">输入样例时间以预览状态色与截止</div>
                                        <div class="mt-2 grid gap-2">
                                            <input type="datetime-local" class="rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                                            <button class="px-3 py-1.5 rounded-lg border border-brand text-brand text-xs">计算</button>
                                            <div class="text-xs text-emerald-600">结果：截止 2025-01-17 10:03 · 状态 OK</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 flex items-center justify-end gap-2 text-sm">
                        <button class="px-4 py-2 rounded-lg border border-slate-200 hover:border-brand/40">取消</button>
                        <button class="px-4 py-2 bg-brand text-white rounded-lg">保存为新版本</button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
