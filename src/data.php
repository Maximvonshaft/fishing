<?php

declare(strict_types=1);

$projects = [
    [
        'code' => 'ALB',
        'name' => 'Albania',
        'shipment_count' => 18,
        'due_nodes' => 6,
        'overdue_nodes' => 3,
        'template_version' => 'v1.2',
    ],
    [
        'code' => 'ITA',
        'name' => 'Italy',
        'shipment_count' => 24,
        'due_nodes' => 4,
        'overdue_nodes' => 1,
        'template_version' => 'v1.0',
    ],
    [
        'code' => 'GRC',
        'name' => 'Greece',
        'shipment_count' => 9,
        'due_nodes' => 2,
        'overdue_nodes' => 0,
        'template_version' => 'v0.9-beta',
    ],
];

$shipments = [
    [
        'code' => 'ALB-2403-001',
        'origin' => 'PVG',
        'destination' => 'TIA',
        'etd' => '2025-01-16 08:00:00',
        'eta' => '2025-01-17 14:30:00',
        'progress' => 6,
        'total_nodes' => 8,
        'warn_nodes' => 2,
        'breach_nodes' => 1,
        'owner' => 'Transit Albania',
        'updated_at' => '2025-01-16 19:45:00',
        'status' => 'WARN',
    ],
    [
        'code' => 'ALB-2403-002',
        'origin' => 'FRA',
        'destination' => 'TIA',
        'etd' => '2025-01-14 10:00:00',
        'eta' => '2025-01-16 09:20:00',
        'progress' => 7,
        'total_nodes' => 9,
        'warn_nodes' => 0,
        'breach_nodes' => 0,
        'owner' => 'Global Freight',
        'updated_at' => '2025-01-16 15:20:00',
        'status' => 'OK',
    ],
];

$shipmentDetail = [
    'code' => 'ALB-2403-001',
    'carrier' => 'Albania Transit Cargo',
    'pieces' => 48,
    'weight' => '5,250 kg',
    'origin' => 'PVG',
    'destination' => 'TIA',
    'etd' => '2025-01-16 08:00:00',
    'eta' => '2025-01-17 14:30:00',
    'owner' => 'Transit Albania',
    'contact' => 'Noela Kodra',
    'contact_email' => 'noela@transit-al.com',
    'contact_phone' => '+355 67 123 4567',
    'milestones' => [
        [
            'id' => 1,
            'code' => 'NOA_ISSUED',
            'name' => '海关货仓下发 NOA',
            'status' => 'OK',
            'deadline' => '2025-01-17 04:30:00',
            'remaining_minutes' => 360,
            'asserted_time' => '2025-01-16 09:45:00',
            'observed_time' => '2025-01-16 10:03:00',
            'verified_time' => '2025-01-16 11:12:00',
            'data' => [
                ['label' => 'NOA 编号', 'value' => 'NOA-ALB-0987'],
            ],
            'evidences' => [
                [
                    'id' => 1,
                    'name' => 'NOA_0987.pdf',
                    'size' => '1.2 MB',
                    'sha256' => 'e19f92ab',
                    'preview_url' => '#',
                    'downloadable' => false,
                ],
            ],
            'signatures' => [
                [
                    'user_name' => 'Dritan Hoxha',
                    'method' => 'Handwrite',
                    'time' => '2025-01-16 11:12:00',
                    'ip' => '195.88.1.34',
                    'device' => 'iPad Pro',
                    'geo' => 'Tirane Customs Warehouse',
                ],
            ],
            'assignment' => [
                'vendor' => 'Transit Albania',
                'contact_name' => 'Dritan Hoxha',
                'contact_phone' => '+355 67 223 4455',
                'contact_email' => 'dritan@transit-al.com',
            ],
            'permissions' => [
                'can_edit' => true,
                'can_upload' => true,
                'can_sign' => true,
                'can_verify' => true,
            ],
            'requires' => [],
            'explain' => 'NOA 下发后 24 小时内确认。起算：observed_time 2025-01-16 10:03。',
            'required_fields' => ['NOA 编号', '到货航班'],
            'signature_required' => true,
        ],
        [
            'id' => 2,
            'code' => 'BBL_HANDOVER_DONE',
            'name' => 'BBL 交接完成提货',
            'status' => 'WARN',
            'deadline' => '2025-01-17 22:03:00',
            'remaining_minutes' => 105,
            'asserted_time' => '2025-01-16 12:30:00',
            'observed_time' => null,
            'verified_time' => null,
            'data' => [],
            'evidences' => [],
            'signatures' => [],
            'assignment' => [
                'vendor' => 'BBL Logistics',
                'contact_name' => 'Ilir Dashi',
                'contact_phone' => '+355 68 445 7788',
                'contact_email' => 'ilir@bbl.com',
            ],
            'permissions' => [
                'can_edit' => true,
                'can_upload' => true,
                'can_sign' => true,
                'can_verify' => false,
            ],
            'requires' => ['NOA_ISSUED'],
            'explain' => 'NOA 后 12 小时完成提货交接。起算：NOA observed_time。',
            'required_fields' => ['交接仓位', '司机姓名'],
            'signature_required' => true,
        ],
        [
            'id' => 3,
            'code' => 'CUSTOMS_CLEARANCE_START',
            'name' => '清关开始',
            'status' => 'BREACH',
            'deadline' => '2025-01-17 18:00:00',
            'remaining_minutes' => -240,
            'asserted_time' => null,
            'observed_time' => null,
            'verified_time' => null,
            'data' => [],
            'evidences' => [],
            'signatures' => [],
            'assignment' => [
                'vendor' => 'Customs Broker Tirane',
                'contact_name' => 'Ana Vasa',
                'contact_phone' => '+355 69 778 9911',
                'contact_email' => 'ana@cbt.al',
            ],
            'permissions' => [
                'can_edit' => false,
                'can_upload' => false,
                'can_sign' => false,
                'can_verify' => false,
            ],
            'requires' => ['BBL_HANDOVER_DONE'],
            'explain' => '预计抵达后 6 小时内启动清关。依赖 BBL 交接完成。',
            'required_fields' => ['清关单号'],
            'signature_required' => false,
        ],
    ],
];

$tasks = [
    [
        'milestone_id' => 2,
        'country' => 'Albania',
        'shipment_code' => 'ALB-2403-001',
        'name' => 'BBL 交接完成提货',
        'deadline' => '2025-01-17 22:03:00',
        'remaining_minutes' => 105,
        'status' => 'WARN',
        'actions' => ['上传交接单', '签字'],
    ],
    [
        'milestone_id' => 5,
        'country' => 'Albania',
        'shipment_code' => 'ALB-2403-003',
        'name' => '目的港仓入库',
        'deadline' => '2025-01-18 09:00:00',
        'remaining_minutes' => 540,
        'status' => 'OK',
        'actions' => ['填写观察时间'],
    ],
];

$templateVersions = [
    'ALB' => [
        'country' => 'Albania',
        'versions' => [
            [
                'version' => 'v1.2',
                'published_at' => '2025-01-12',
                'author' => 'Lira Hoxha',
            ],
            [
                'version' => 'v1.1',
                'published_at' => '2024-12-18',
                'author' => 'Lira Hoxha',
            ],
        ],
        'milestones' => $shipmentDetail['milestones'],
    ],
];

$reports = [
    'kpis' => [
        ['label' => 'SLA 达成率', 'value' => '92%', 'trend' => '+3%'],
        ['label' => '平均逾时 (分钟)', 'value' => '42', 'trend' => '-11'],
        ['label' => '退回率', 'value' => '8%', 'trend' => '+1%'],
        ['label' => '证据完整率', 'value' => '96%', 'trend' => '+2%'],
    ],
    'trend' => [
        ['date' => '2025-01-10', 'ok' => 28, 'warn' => 4, 'breach' => 2],
        ['date' => '2025-01-11', 'ok' => 31, 'warn' => 3, 'breach' => 1],
        ['date' => '2025-01-12', 'ok' => 29, 'warn' => 5, 'breach' => 1],
        ['date' => '2025-01-13', 'ok' => 32, 'warn' => 2, 'breach' => 0],
    ],
    'top_vendors' => [
        ['name' => 'Transit Albania', 'sla' => '95%', 'breach' => 1],
        ['name' => 'BBL Logistics', 'sla' => '88%', 'breach' => 3],
        ['name' => 'Customs Broker Tirane', 'sla' => '90%', 'breach' => 2],
    ],
];

$settings = [
    'user' => [
        'name' => 'Dritan Hoxha',
        'email' => 'dritan@transit-al.com',
        'language' => 'zh-CN',
        'timezone' => 'Europe/Tirane',
        'mfa_enabled' => true,
    ],
    'vendors' => [
        [
            'name' => 'Transit Albania',
            'countries' => ['ALB'],
            'nodes' => ['NOA_ISSUED', 'BBL_HANDOVER_DONE'],
            'mfa' => '强制',
            'download_policy' => '仅预览',
        ],
        [
            'name' => 'BBL Logistics',
            'countries' => ['ALB'],
            'nodes' => ['BBL_HANDOVER_DONE'],
            'mfa' => '可选',
            'download_policy' => '需审批',
        ],
    ],
    'notifications' => [
        ['name' => '黄灯提醒', 'channel' => 'Email + IM', 'updated_at' => '2025-01-15'],
        ['name' => '逾时告警', 'channel' => 'Email', 'updated_at' => '2025-01-14'],
    ],
];
