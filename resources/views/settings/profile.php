<?php $displayTz = config('app.display_timezone', 'Europe/Tirane'); ?>
<div class="max-w-3xl space-y-6">
    <div class="bg-white border border-slate-200 rounded-3xl shadow-sm p-6">
        <h2 class="text-xl font-semibold text-slate-900">个人资料</h2>
        <p class="text-sm text-slate-500 mt-2">更新显示名称与密码，平台统一 UTC 存储，界面显示 <?= htmlspecialchars($displayTz) ?>。</p>
        <form method="post" class="mt-6 space-y-4" action="<?= htmlspecialchars(route('settings.index')) ?>">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="_action" value="profile_update">
            <div>
                <label class="block text-sm font-medium text-slate-600" for="display_name">显示名称</label>
                <input type="text" id="display_name" name="display_name" value="<?= htmlspecialchars($profile['display_name'] ?? '') ?>"
                       class="mt-1 block w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-600" for="current_password">当前密码</label>
                    <input type="password" id="current_password" name="current_password" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600" for="new_password">新密码</label>
                    <input type="password" id="new_password" name="new_password" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand" minlength="8">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600" for="new_password_confirmation">确认新密码</label>
                    <input type="password" id="new_password_confirmation" name="new_password_confirmation" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand" minlength="8">
                </div>
            </div>
            <div class="flex items-center justify-end gap-3">
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-brand text-white font-medium hover:bg-brand/90">保存修改</button>
            </div>
        </form>
    </div>
</div>
