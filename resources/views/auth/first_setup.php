<?php $error = flash('error'); $success = flash('success'); ?>
<div class="max-w-2xl mx-auto bg-white border border-slate-200 rounded-2xl shadow-sm p-8">
    <h2 class="text-xl font-semibold text-slate-900">首次登录 · 重置密码</h2>
    <p class="text-sm text-slate-500 mt-2">为保证账号安全，请设置新密码（至少 8 位，建议 12 位以上，包含大小写与数字）。</p>
    <?php if ($error): ?>
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 text-red-600 px-4 py-3 text-sm">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-600 px-4 py-3 text-sm">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>
    <form class="mt-6 space-y-5" method="post" action="<?= htmlspecialchars(route('auth.first_setup')) ?>">
        <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="_action" value="complete_first_setup">
        <div>
            <label class="block text-sm font-medium text-slate-600" for="new_password">新密码</label>
            <input type="password" id="new_password" name="new_password" required minlength="8"
                   class="mt-1 block w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-600" for="new_password_confirmation">确认新密码</label>
            <input type="password" id="new_password_confirmation" name="new_password_confirmation" required minlength="8"
                   class="mt-1 block w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
        </div>
        <button type="submit" class="inline-flex items-center justify-center gap-2 bg-brand text-white px-6 py-2.5 rounded-lg font-medium hover:bg-brand/90">
            保存新密码
        </button>
    </form>
</div>
