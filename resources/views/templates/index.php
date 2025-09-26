<?php
$displayVendors = [];
foreach ($vendors as $vendor) {
    $displayVendors[(int) $vendor['id']] = $vendor['name'];
}

$vendorUsersByVendor = [];
foreach ($vendorUsers as $u) {
    $vendorUsersByVendor[(int) $u['vendor_id']][] = $u;
}

$selectedId = $selectedCountry['id'] ?? null;
?>
<div class="grid gap-6 lg:grid-cols-[320px_minmax(0,1fr)]">
    <aside class="bg-white border border-slate-200 rounded-3xl shadow-sm p-6 space-y-4">
        <div>
            <h3 class="text-sm font-semibold text-slate-700">国家选择</h3>
            <select class="mt-2 w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand text-sm" onchange="if (this.value) { window.location = '<?= htmlspecialchars(route('templates.index')) ?>' + '&country_id=' + this.value; }">
                <?php foreach ($countries as $country): ?>
                    <option value="<?= (int) $country['id'] ?>" <?= $selectedId === (int) $country['id'] ? 'selected' : '' ?>><?= htmlspecialchars($country['code'] . ' · ' . $country['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="text-xs text-slate-500 space-y-2">
            <p>模板仅影响新建批次，保存后不会修改已实例化的节点。</p>
            <p>首节点起算点不允许选择 <code class="px-1 bg-slate-100 rounded">previous</code>。</p>
        </div>
    </aside>

    <section class="bg-white border border-slate-200 rounded-3xl shadow-sm">
        <header class="p-6 border-b border-slate-200 flex flex-col gap-2">
            <div class="text-sm text-slate-500">节点模板</div>
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-900">
                    <?= $selectedCountry ? htmlspecialchars($selectedCountry['name']) . ' 模板' : '请选择国家' ?>
                </h2>
                <button type="button" id="add-node" class="px-4 py-2 rounded-lg border border-brand text-brand hover:bg-brand/5 text-sm">新增节点</button>
            </div>
        </header>

        <?php if (!$selectedCountry): ?>
            <div class="p-6 text-slate-500">请先在左侧选择国家。</div>
        <?php else: ?>
            <form method="post" action="<?= htmlspecialchars(route('templates.index', ['country_id' => $selectedCountry['id']])) ?>" class="p-6 space-y-4" id="template-form">
                <input type="hidden" name="_action" value="country_nodes_save">
                <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="country_id" value="<?= (int) $selectedCountry['id'] ?>">

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm" id="nodes-table">
                        <thead class="bg-slate-50 text-slate-500 uppercase text-xs tracking-wider">
                            <tr>
                                <th class="px-3 py-2 text-left">顺序</th>
                                <th class="px-3 py-2 text-left">节点名称</th>
                                <th class="px-3 py-2 text-left">默认供应商</th>
                                <th class="px-3 py-2 text-left">默认账号</th>
                                <th class="px-3 py-2 text-left">起算点</th>
                                <th class="px-3 py-2 text-left">时效 (小时)</th>
                                <th class="px-3 py-2 text-left">附件必传</th>
                                <th class="px-3 py-2 text-left">签名必需</th>
                                <th class="px-3 py-2 text-left">操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100" id="nodes-body">
                        <?php foreach ($nodes as $index => $node): ?>
                            <tr class="bg-white" data-index="<?= (int) $index ?>">
                                <td class="px-3 py-2 align-top">
                                    <input type="hidden" name="nodes[<?= (int) $index ?>][id]" value="<?= (int) $node['id'] ?>">
                                    <input name="nodes[<?= (int) $index ?>][sort_order]" type="number" min="1" value="<?= (int) $node['sort_order'] ?>" class="w-16 rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <input name="nodes[<?= (int) $index ?>][name]" value="<?= htmlspecialchars($node['name']) ?>" class="w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <select name="nodes[<?= (int) $index ?>][vendor_id]" class="w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                                        <?php foreach ($vendors as $vendor): ?>
                                            <option value="<?= (int) $vendor['id'] ?>" <?= (int) $vendor['id'] === (int) $node['vendor_id'] ? 'selected' : '' ?>><?= htmlspecialchars($vendor['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <select name="nodes[<?= (int) $index ?>][default_assignee_user_id]" class="w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                                        <option value="">-- 可选 --</option>
                                        <?php foreach ($vendorUsers as $userOption): ?>
                                            <option value="<?= (int) $userOption['id'] ?>" <?= (int) $userOption['id'] === (int) $node['default_assignee_user_id'] ? 'selected' : '' ?>><?= htmlspecialchars(($displayVendors[(int) $userOption['vendor_id']] ?? '供应商') . ' · ' . $userOption['display_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <select name="nodes[<?= (int) $index ?>][base_type]" class="w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                                        <?php foreach (['eta' => 'ETA', 'creation' => 'Creation', 'previous' => 'Previous'] as $value => $label): ?>
                                            <option value="<?= $value ?>" <?= $node['base_type'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <input name="nodes[<?= (int) $index ?>][sla_hours]" type="number" min="0" step="0.5" value="<?= htmlspecialchars((string) $node['sla_hours']) ?>" class="w-24 rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <input type="checkbox" value="1" name="nodes[<?= (int) $index ?>][evidence_required]" <?= (int) $node['evidence_required'] === 1 ? 'checked' : '' ?> class="rounded border-slate-300">
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <input type="checkbox" value="1" name="nodes[<?= (int) $index ?>][signature_required]" <?= (int) $node['signature_required'] === 1 ? 'checked' : '' ?> class="rounded border-slate-300">
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <button type="button" class="text-xs text-rose-600" onclick="removeNodeRow(this)">删除</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <a href="<?= htmlspecialchars(route('templates.index')) ?>" class="px-4 py-2 rounded-lg border border-slate-200 hover:border-brand/40 text-sm">取消</a>
                    <button type="submit" class="px-4 py-2 bg-brand text-white rounded-lg text-sm">保存模板</button>
                </div>
            </form>
        <?php endif; ?>
    </section>
</div>

<template id="node-row-template">
    <tr class="bg-white" data-index="__INDEX__">
        <td class="px-3 py-2 align-top">
            <input type="hidden" name="nodes[__INDEX__][id]" value="">
            <input name="nodes[__INDEX__][sort_order]" type="number" min="1" value="__ORDER__" class="w-16 rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
        </td>
        <td class="px-3 py-2 align-top">
            <input name="nodes[__INDEX__][name]" class="w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand" placeholder="节点名称">
        </td>
        <td class="px-3 py-2 align-top">
            <select name="nodes[__INDEX__][vendor_id]" class="w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                <?php foreach ($vendors as $vendor): ?>
                    <option value="<?= (int) $vendor['id'] ?>"><?= htmlspecialchars($vendor['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td class="px-3 py-2 align-top">
            <select name="nodes[__INDEX__][default_assignee_user_id]" class="w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                <option value="">-- 可选 --</option>
                <?php foreach ($vendorUsers as $userOption): ?>
                    <option value="<?= (int) $userOption['id'] ?>"><?= htmlspecialchars(($displayVendors[(int) $userOption['vendor_id']] ?? '供应商') . ' · ' . $userOption['display_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td class="px-3 py-2 align-top">
            <select name="nodes[__INDEX__][base_type]" class="w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                <option value="eta">ETA</option>
                <option value="creation">Creation</option>
                <option value="previous">Previous</option>
            </select>
        </td>
        <td class="px-3 py-2 align-top">
            <input name="nodes[__INDEX__][sla_hours]" type="number" min="0" step="0.5" value="0" class="w-24 rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
        </td>
        <td class="px-3 py-2 align-top">
            <input type="checkbox" value="1" name="nodes[__INDEX__][evidence_required]" class="rounded border-slate-300">
        </td>
        <td class="px-3 py-2 align-top">
            <input type="checkbox" value="1" name="nodes[__INDEX__][signature_required]" class="rounded border-slate-300">
        </td>
        <td class="px-3 py-2 align-top">
            <button type="button" class="text-xs text-rose-600" onclick="removeNodeRow(this)">删除</button>
        </td>
    </tr>
</template>

<script>
    (function () {
        const addBtn = document.getElementById('add-node');
        const body = document.getElementById('nodes-body');
        if (!addBtn || !body) {
            return;
        }
        addBtn.addEventListener('click', () => {
            const template = document.getElementById('node-row-template');
            if (!template) {
                return;
            }
            const index = body.children.length;
            const nextOrder = index + 1;
            const html = template.innerHTML.replace(/__INDEX__/g, String(index)).replace(/__ORDER__/g, String(nextOrder));
            const temp = document.createElement('tbody');
            temp.innerHTML = html.trim();
            body.appendChild(temp.firstElementChild);
        });
    })();

    function removeNodeRow(button) {
        const row = button.closest('tr');
        if (row && row.parentElement) {
            row.parentElement.removeChild(row);
        }
    }
</script>
