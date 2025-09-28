<div class="space-y-6">
    <div class="bg-white border border-slate-200 rounded-3xl shadow-sm p-6">
        <h2 class="text-xl font-semibold text-slate-900">用户管理</h2>
        <p class="text-sm text-slate-500 mt-2">管理员可创建内部账号或供应商账号，支持临时密码与启停用。</p>
        <form method="post" class="mt-6 grid gap-4 md:grid-cols-2" action="<?= htmlspecialchars(route('users.manage')) ?>">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="_action" value="user_create">
            <div>
                <label class="block text-sm font-medium text-slate-600" for="user-email">邮箱</label>
                <input type="email" id="user-email" name="email" required class="mt-1 block w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600" for="user-display">显示名称</label>
                <input type="text" id="user-display" name="display_name" required class="mt-1 block w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600" for="user-role">角色</label>
                <select id="user-role" name="role" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                    <option value="admin">管理员</option>
                    <option value="vendor" selected>供应商</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600" for="user-vendor">所属供应商</label>
                <select id="user-vendor" name="vendor_id" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                    <option value="">-- 内部账号无需选择 --</option>
                    <?php foreach ($vendors as $vendor): ?>
                        <option value="<?= (int)$vendor['id'] ?>"><?= htmlspecialchars($vendor['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-2 flex items-center justify-end">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-brand text-white font-medium hover:bg-brand/90">创建账号</button>
            </div>
        </form>
    </div>

    <div class="bg-white border border-slate-200 rounded-3xl shadow-sm">
        <div class="p-6 border-b border-slate-200">
            <h3 class="text-lg font-semibold text-slate-900">账号列表</h3>
        </div>
        <div class="p-6 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                    <tr>
                        <th class="px-4 py-3 text-left">邮箱</th>
                        <th class="px-4 py-3 text-left">名称</th>
                        <th class="px-4 py-3 text-left">角色</th>
                        <th class="px-4 py-3 text-left">供应商</th>
                        <th class="px-4 py-3 text-left">状态</th>
                        <th class="px-4 py-3 text-left">上次登录</th>
                        <th class="px-4 py-3 text-left">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($users as $item): ?>
                        <tr>
                            <td class="px-4 py-3 font-mono text-sm text-slate-900"><?= htmlspecialchars($item['email']) ?></td>
                            <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars($item['display_name']) ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($item['role']) ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($item['vendor_name'] ?? '--') ?></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full border <?= (int)$item['active'] === 1 ? 'border-emerald-200 text-emerald-600 bg-emerald-50' : 'border-slate-200 text-slate-500 bg-slate-50' ?>">
                                    <?= (int)$item['active'] === 1 ? '启用' : '停用' ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-500"><?= $item['last_login_at'] ? htmlspecialchars(format_datetime($item['last_login_at'])) : '未登录' ?></td>
                            <td class="px-4 py-3 space-x-2">
                                <form method="post" class="inline" action="<?= htmlspecialchars(route('users.manage')) ?>">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="_action" value="user_toggle">
                                    <input type="hidden" name="user_id" value="<?= (int)$item['id'] ?>">
                                    <input type="hidden" name="active" value="<?= (int)$item['active'] === 1 ? 0 : 1 ?>">
                                    <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-200 hover:border-brand/40">
                                        <?= (int)$item['active'] === 1 ? '停用' : '启用' ?>
                                    </button>
                                </form>
                                <form method="post" class="inline" action="<?= htmlspecialchars(route('users.manage')) ?>">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="_action" value="user_reset_password">
                                    <input type="hidden" name="user_id" value="<?= (int)$item['id'] ?>">
                                    <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-200 hover:border-brand/40">
                                        重置密码
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-slate-500">暂无账号</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
