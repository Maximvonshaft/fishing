<div class="space-y-6">
    <section class="bg-white border border-slate-200 rounded-3xl shadow-sm p-6">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-900">我的账号</h2>
            <button class="text-sm text-brand">编辑</button>
        </div>
        <dl class="mt-4 grid gap-4 sm:grid-cols-2 text-sm text-slate-600">
            <div><dt class="text-xs text-slate-400">姓名</dt><dd class="text-slate-900 font-semibold"><?= htmlspecialchars($settings['user']['name']) ?></dd></div>
            <div><dt class="text-xs text-slate-400">邮箱</dt><dd><?= htmlspecialchars($settings['user']['email']) ?></dd></div>
            <div><dt class="text-xs text-slate-400">界面语言</dt><dd><?= htmlspecialchars($settings['user']['language']) ?></dd></div>
            <div><dt class="text-xs text-slate-400">时间显示</dt><dd><?= htmlspecialchars($settings['user']['timezone']) ?> · Europe/Tirane</dd></div>
            <div><dt class="text-xs text-slate-400">MFA</dt><dd><?= $settings['user']['mfa_enabled'] ? '已启用' : '未启用' ?></dd></div>
        </dl>
    </section>

    <section class="bg-white border border-slate-200 rounded-3xl shadow-sm p-6">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-900">供应商管理</h2>
            <button class="text-sm text-brand">新增供应商</button>
        </div>
        <div class="mt-4 space-y-3">
            <?php foreach ($settings['vendors'] as $vendor): ?>
                <div class="border border-slate-200 rounded-2xl p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-semibold text-slate-900"><?= htmlspecialchars($vendor['name']) ?></div>
                            <div class="text-xs text-slate-400">国家 <?= implode(',', $vendor['countries']) ?></div>
                        </div>
                        <button class="text-xs text-brand">编辑</button>
                    </div>
                    <div class="mt-3 grid gap-3 md:grid-cols-3 text-xs text-slate-500">
                        <div>节点白名单：<?= implode('、', $vendor['nodes']) ?></div>
                        <div>MFA 策略：<?= htmlspecialchars($vendor['mfa']) ?></div>
                        <div>下载策略：<?= htmlspecialchars($vendor['download_policy']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="bg-white border border-slate-200 rounded-3xl shadow-sm p-6">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-900">通知模板</h2>
            <button class="text-sm text-brand">新增模板</button>
        </div>
        <div class="mt-4 space-y-3 text-sm text-slate-600">
            <?php foreach ($settings['notifications'] as $notification): ?>
                <div class="border border-slate-200 rounded-2xl p-4 flex items-center justify-between">
                    <div>
                        <div class="font-semibold text-slate-900"><?= htmlspecialchars($notification['name']) ?></div>
                        <div class="text-xs text-slate-400">渠道 <?= htmlspecialchars($notification['channel']) ?></div>
                    </div>
                    <div class="text-xs text-slate-400">更新 <?= htmlspecialchars($notification['updated_at']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
