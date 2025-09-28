<div class="space-y-6">
    <div class="bg-white border border-slate-200 rounded-3xl shadow-sm p-6">
        <h2 class="text-xl font-semibold text-slate-900">供应商管理</h2>
        <p class="text-sm text-slate-500 mt-2">创建、编辑与启停用供应商，停用前需处理其下账号。</p>
        <form method="post" class="mt-6 grid gap-4 md:grid-cols-2" action="<?= htmlspecialchars(route('vendors.manage')) ?>">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="_action" value="vendor_create">
            <div>
                <label class="block text-sm font-medium text-slate-600" for="vendor-name">供应商名称</label>
                <input type="text" id="vendor-name" name="name" required class="mt-1 block w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600" for="vendor-email">联系邮箱</label>
                <input type="email" id="vendor-email" name="contact_email" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600" for="vendor-phone">联系电话</label>
                <input type="text" id="vendor-phone" name="contact_phone" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
            </div>
            <div class="md:col-span-2 flex items-center justify-end">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-brand text-white font-medium hover:bg-brand/90">新建供应商</button>
            </div>
        </form>
    </div>

    <div class="bg-white border border-slate-200 rounded-3xl shadow-sm">
        <div class="p-6 border-b border-slate-200">
            <h3 class="text-lg font-semibold text-slate-900">供应商列表</h3>
        </div>
        <div class="p-6 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                    <tr>
                        <th class="px-4 py-3 text-left">名称</th>
                        <th class="px-4 py-3 text-left">邮箱</th>
                        <th class="px-4 py-3 text-left">电话</th>
                        <th class="px-4 py-3 text-left">状态</th>
                        <th class="px-4 py-3 text-left">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($vendors as $vendor): ?>
                        <tr>
                            <td class="px-4 py-3 font-semibold text-slate-900"><?= htmlspecialchars($vendor['name']) ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($vendor['contact_email'] ?? '--') ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($vendor['contact_phone'] ?? '--') ?></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full border <?= (int)$vendor['active'] === 1 ? 'border-emerald-200 text-emerald-600 bg-emerald-50' : 'border-slate-200 text-slate-500 bg-slate-50' ?>">
                                    <?= (int)$vendor['active'] === 1 ? '启用' : '停用' ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <form method="post" class="inline" action="<?= htmlspecialchars(route('vendors.manage')) ?>">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="_action" value="vendor_toggle">
                                    <input type="hidden" name="vendor_id" value="<?= (int)$vendor['id'] ?>">
                                    <input type="hidden" name="active" value="<?= (int)$vendor['active'] === 1 ? 0 : 1 ?>">
                                    <button type="submit" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 hover:border-brand/40">
                                        <?= (int)$vendor['active'] === 1 ? '停用' : '启用' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($vendors)): ?>
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-slate-500">暂无供应商</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
