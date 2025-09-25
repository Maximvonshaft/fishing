<div class="max-w-2xl mx-auto bg-white border border-slate-200 rounded-2xl shadow-sm p-8">
    <h2 class="text-xl font-semibold text-slate-900">首次设置</h2>
    <p class="text-sm text-slate-500 mt-2">选择界面语言并确认显示时区，后续可在系统设置中修改。</p>
    <form class="mt-6 space-y-5">
        <div>
            <label class="block text-sm font-medium text-slate-600">界面语言</label>
            <select class="mt-1 block w-full rounded-lg border-slate-300 focus:border-brand focus:ring-brand">
                <option value="zh-CN">简体中文</option>
                <option value="en">English</option>
                <option value="ar">العربية</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-600">时间显示</label>
            <div class="mt-1 flex items-center gap-3 p-4 border border-slate-200 rounded-xl bg-slate-50">
                <div class="font-semibold text-slate-900">Europe/Tirane</div>
                <div class="text-sm text-slate-500">所有时间均以 Europe/Tirane 显示，后端统一 UTC。</div>
            </div>
        </div>
        <button class="inline-flex items-center justify-center gap-2 bg-brand text-white px-6 py-2.5 rounded-lg font-medium hover:bg-brand/90">
            完成设置
        </button>
    </form>
</div>
