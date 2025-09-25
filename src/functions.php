<?php

declare(strict_types=1);

function view(string $view, array $data = []): void
{
    $layout = $data['_layout'] ?? 'layouts.app';
    unset($data['_layout']);

    $content = renderView($view, $data);
    echo renderView($layout, array_merge($data, ['content' => $content]));
}

function renderView(string $view, array $data = []): string
{
    $path = __DIR__ . '/../resources/views/' . str_replace('.', '/', $view) . '.php';

    if (!file_exists($path)) {
        throw new RuntimeException("View {$view} not found at {$path}");
    }

    extract($data, EXTR_SKIP);

    ob_start();
    include $path;

    return ob_get_clean();
}

function asset(string $path): string
{
    return $path;
}

function route(string $name, array $params = []): string
{
    $routes = [
        'login' => 'index.php?page=login',
        'mfa' => 'index.php?page=mfa',
        'first-setup' => 'index.php?page=first-setup',
        'projects.index' => 'index.php?page=projects',
        'shipments.index' => 'index.php?page=shipments',
        'shipments.show' => 'index.php?page=shipment-detail',
        'tasks.index' => 'index.php?page=tasks',
        'templates.index' => 'index.php?page=templates',
        'reports.index' => 'index.php?page=reports',
        'settings.index' => 'index.php?page=settings',
    ];

    $route = $routes[$name] ?? '#';

    if ($params) {
        $query = http_build_query($params);
        $route .= (str_contains($route, '?') ? '&' : '?') . $query;
    }

    return $route;
}

function formatUtcToLocal(string $timestamp, string $timezone = 'Europe/Tirane'): array
{
    $dt = new DateTimeImmutable($timestamp, new DateTimeZone('UTC'));
    $local = $dt->setTimezone(new DateTimeZone($timezone));

    return [
        'local' => $local->format('Y-m-d H:i'),
        'utc' => $dt->format('Y-m-d H:i'),
    ];
}

function statusColor(string $status): string
{
    return match ($status) {
        'OK' => 'text-emerald-600 bg-emerald-50 border-emerald-200',
        'WARN' => 'text-amber-600 bg-amber-50 border-amber-200',
        'BREACH' => 'text-rose-600 bg-rose-50 border-rose-200',
        'PAUSED' => 'text-gray-500 bg-gray-50 border-gray-200',
        default => 'text-slate-600 bg-slate-50 border-slate-200',
    };
}

function statusDotColor(string $status): string
{
    return match ($status) {
        'OK' => 'bg-[#18A058]',
        'WARN' => 'bg-[#FAAD14]',
        'BREACH' => 'bg-[#F5222D]',
        'PAUSED' => 'bg-[#BFBFBF]',
        default => 'bg-gray-400',
    };
}
