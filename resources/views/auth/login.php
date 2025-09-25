<?php $error = flash('error'); ?>
<div class="max-w-md mx-auto bg-white border border-slate-200 rounded-2xl shadow-sm p-8">
    <div class="text-center mb-6">
        <h2 class="text-xl font-semibold text-slate-900">登录账户</h2>
        <p class="text-sm text-slate-500 mt-2">使用工作邮箱登录，若忘记密码请联系管理员。</p>
    </div>
    <?php if ($error): ?>
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-600 px-4 py-3 text-sm">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    <form method="post" class="space-y-5" action="<?= htmlspecialchars(route('auth.login')) ?>">
        <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="_action" value="login">
        <div>
            <label class="block text-sm font-medium text-slate-600" for="email">邮箱</label>
            <input type="email" id="email" name="email" required autocomplete="username"
                   class="mt-1 block w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand"
                   placeholder="name@company.com">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-600 flex items-center justify-between" for="password">
                <span>密码</span>
            </label>
            <input type="password" id="password" name="password" required autocomplete="current-password"
                   class="mt-1 block w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
        </div>
        <button type="submit" class="w-full inline-flex justify-center items-center gap-2 bg-brand text-white py-2.5 rounded-lg font-medium hover:bg-brand/90">登录</button>
    </form>
    <div class="mt-6 text-center text-xs text-slate-400">
        登录即表示同意平台使用协议及隐私政策。
    </div>
</div>
