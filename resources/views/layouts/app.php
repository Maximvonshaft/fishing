<!DOCTYPE html>
<html lang="zh-CN" x-data="{sidebarOpen: false}">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($title ?? '控制台') ?> · Logistics SLA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: '#0047AB',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
<div class="flex min-h-screen">
    <div class="hidden lg:flex lg:flex-col lg:w-64 bg-white border-r border-slate-200">
        <div class="h-16 flex items-center px-6 border-b border-slate-200">
            <span class="text-lg font-semibold text-brand">Logistics SLA</span>
        </div>
        <nav class="flex-1 overflow-y-auto py-6 space-y-1 px-4 text-sm">
            <?php
            $navItems = [
                ['label' => '国家', 'route' => 'projects.index', 'icon' => '🌍'],
                ['label' => '批次', 'route' => 'shipments.index', 'icon' => '📦'],
                ['label' => '待办', 'route' => 'tasks.index', 'icon' => '✅'],
                ['label' => '模板', 'route' => 'templates.index', 'icon' => '🧩'],
                ['label' => '报表', 'route' => 'reports.index', 'icon' => '📊'],
                ['label' => '系统设置', 'route' => 'settings.index', 'icon' => '⚙️'],
            ];
            $current = $_GET['page'] ?? 'projects';
            $map = [
                'projects' => 'projects.index',
                'shipments' => 'shipments.index',
                'shipment-detail' => 'shipments.index',
                'tasks' => 'tasks.index',
                'templates' => 'templates.index',
                'reports' => 'reports.index',
                'settings' => 'settings.index',
            ];
            $currentRoute = $map[$current] ?? 'projects.index';
            foreach ($navItems as $item):
                $active = $item['route'] === $currentRoute;
            ?>
            <a href="<?= route($item['route']) ?>"
               class="flex items-center gap-3 px-3 py-2 rounded-lg border <?php if ($active): ?>bg-brand/10 border-brand text-brand font-semibold<?php else: ?>border-transparent hover:border-brand/40 hover:bg-brand/5<?php endif; ?>">
                <span><?= $item['icon'] ?></span>
                <span><?= htmlspecialchars($item['label']) ?></span>
            </a>
            <?php endforeach; ?>
        </nav>
        <div class="p-4 border-t border-slate-200 text-xs text-slate-500">
            <div>Europe/Tirane</div>
            <div><?= date('Y-m-d H:i') ?></div>
        </div>
    </div>

    <div class="flex-1 flex flex-col">
        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-6">
            <div class="flex items-center gap-3">
                <button class="lg:hidden inline-flex items-center justify-center w-10 h-10 rounded-lg border border-slate-200"
                        @click="sidebarOpen = !sidebarOpen">
                    ☰
                </button>
                <div class="text-sm text-slate-500">
                    Europe/Tirane · 本地显示
                </div>
            </div>
            <div class="flex items-center gap-4">
                <button class="text-sm text-slate-500 hover:text-brand">通知中心</button>
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-brand/20 flex items-center justify-center text-brand font-semibold">DH</div>
                    <div class="text-sm">
                        <div class="font-semibold">Dritan Hoxha</div>
                        <div class="text-slate-500">Transit Albania</div>
                    </div>
                </div>
            </div>
        </header>
        <main class="flex-1 overflow-y-auto p-6">
            <div class="max-w-[1440px] mx-auto">
                <h1 class="text-2xl font-semibold text-slate-900 mb-6 flex items-center gap-3">
                    <?= htmlspecialchars($title ?? '控制台') ?>
                </h1>
                <?= $content ?? '' ?>
            </div>
        </main>
    </div>
</div>

</body>
</html>
