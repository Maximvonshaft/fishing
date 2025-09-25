<div class="max-w-lg mx-auto bg-white border border-slate-200 rounded-2xl shadow-sm p-8">
    <div class="flex items-start gap-4">
        <div class="flex-1">
            <h2 class="text-xl font-semibold text-slate-900">多因素认证</h2>
            <p class="text-sm text-slate-500 mt-2">使用身份验证器扫描二维码，输入 6 位验证码完成登录。</p>
        </div>
        <div class="w-32 h-32 bg-slate-100 rounded-xl flex items-center justify-center text-slate-400 text-xs">
            QR CODE
        </div>
    </div>
    <form class="mt-6 space-y-4">
        <div>
            <label class="block text-sm font-medium text-slate-600">验证码</label>
            <input type="text" maxlength="6" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand" placeholder="123456">
        </div>
        <div class="flex items-center justify-between text-xs text-slate-500">
            <span>如无法使用验证器，可输入恢复码。</span>
            <a href="#" class="text-brand">使用恢复码</a>
        </div>
        <button type="submit" class="w-full inline-flex justify-center items-center gap-2 bg-brand text-white py-2.5 rounded-lg font-medium hover:bg-brand/90">验证并登录</button>
    </form>
</div>
