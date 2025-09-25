<?php

declare(strict_types=1);

require __DIR__ . '/../src/functions.php';
require __DIR__ . '/../src/data.php';

$page = $_GET['page'] ?? 'projects';

switch ($page) {
    case 'login':
        view('auth.login', ['title' => '登录', '_layout' => 'layouts.guest']);
        break;
    case 'mfa':
        view('auth.mfa', ['title' => 'MFA 验证', '_layout' => 'layouts.guest']);
        break;
    case 'first-setup':
        view('auth.first_setup', ['title' => '首次设置', '_layout' => 'layouts.guest']);
        break;
    case 'shipments':
        view('shipments.index', [
            'title' => '批次列表',
            'shipments' => $shipments,
            'selectedProject' => 'Albania',
        ]);
        break;
    case 'shipment-detail':
        view('shipments.show', [
            'title' => '批次详情',
            'shipment' => $shipmentDetail,
        ]);
        break;
    case 'tasks':
        view('tasks.index', [
            'title' => '我的待办',
            'tasks' => $tasks,
        ]);
        break;
    case 'templates':
        view('templates.index', [
            'title' => '模板管理',
            'templates' => $templateVersions,
        ]);
        break;
    case 'reports':
        view('reports.index', [
            'title' => '报表',
            'reports' => $reports,
        ]);
        break;
    case 'settings':
        view('settings.index', [
            'title' => '系统设置',
            'settings' => $settings,
        ]);
        break;
    case 'projects':
    default:
        view('projects.index', [
            'title' => '国家目录',
            'projects' => $projects,
        ]);
        break;
}
