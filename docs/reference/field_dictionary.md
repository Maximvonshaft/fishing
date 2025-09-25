# 字段字典（ALB 模板包 v1）

> 统一字段命名遵循 snake_case，时间字段以 `*_time` 结尾（UTC 存储，前端 Europe/Tirane 渲染）。

| 字段 | 适用节点 | 类型 | 来源/录入方 | 校验规则 | 备注 |
| --- | --- | --- | --- | --- | --- |
| `observed_time` | 全部节点 | `timestamp` | 供应商 operator | 必填，<= 当前时间 + 15 分钟 | 关键事件实际发生时间；触发 SLA 起算 |
| `asserted_time` | NOA_ISSUED | `timestamp` | 供应商 operator | 可选，<= `observed_time` | 系统默认等于 `observed_time`，用于 SLA 回退 |
| `document_number` | NOA_ISSUED | `string(20)` | 供应商 operator | 正则 `^[A-Z0-9-]{6,20}$` | NOA 编号 |
| `warehouse_code` | NOA_ISSUED | `enum` | 供应商 operator | 必填，枚举：`TIA/PRN/POG` | 仓库代码（可按项目扩展） |
| `slot_start` | DELIVERY_WINDOW_CONFIRMED | `timestamp` | 供应商 operator | 必填，>= NOA_ISSUED.observed_time | 提货窗开始 |
| `slot_end` | DELIVERY_WINDOW_CONFIRMED | `timestamp` | 供应商 operator | 必填，> `slot_start` | 提货窗结束 |
| `contact_person` | DELIVERY_WINDOW_CONFIRMED | `string(80)` | 供应商 operator | 必填 | 仓库确认联系人 |
| `contact_phone` | DELIVERY_WINDOW_CONFIRMED | `string(16)` | 供应商 operator | 正则 `^\+355[0-9]{8,9}$` | 仅允许阿尔巴尼亚手机号 |
| `handover_location` | BBL_HANDOVER_DONE | `string(120)` | 供应商 operator | 必填 | 交接地点描述 |
| `handover_person` | BBL_HANDOVER_DONE | `string(80)` | 供应商 operator | 必填 | 交接负责人 |
| `vehicle_plate` | BBL_HANDOVER_DONE | `string(12)` | 供应商 operator | 正则 `^[A-Z]{2}-[0-9]{3,4}-[A-Z]{1,2}$` | 交接车辆牌照 |
| `gate_reference` | VORE_INBOUND_REGULATED | `string(12)` | 供应商 operator | 正则 `^VORE-[0-9]{5}$` | Vore 监管门禁编号 |
| `customs_officer` | VORE_INBOUND_REGULATED | `string(80)` | 供应商 operator | 必填 | 监管官员姓名 |
| `declaration_number` | CUSTOMS_CLEARANCE_STARTED | `string(9)` | 报关行 approver | 正则 `^[0-9]{6}/[0-9]{2}$` | 报关单号 |
| `customs_channel` | CUSTOMS_CLEARANCE_STARTED | `enum` | 报关行 approver | 枚举：`green/yellow/red` | 海关放行通道 |
| `sla_override_reason` | 全部节点 | `string(200)` | 内部用户 | 可选，填写时强制记录 event | 仅内控使用 |
| `geo_lat` / `geo_lng` | 带地理证据节点 | `decimal(10,7)` | 系统采集 | 校验与站点中心点距离 ≤ 200m | 用于地理围栏 |
| `device_fingerprint` | 签字 | `string(128)` | 系统采集 | 必填 | 防抵赖 |

## 附：证据类型约束

| 节点 | 证据类型 | 说明 | 校验 |
| --- | --- | --- | --- |
| NOA_ISSUED | PDF | 必填原件 | MIME = `application/pdf`，大小 ≤ 10MB |
| DELIVERY_WINDOW_CONFIRMED | PDF | 可选附件 | MIME = `application/pdf`，大小 ≤ 5MB |
| BBL_HANDOVER_DONE | PDF | 必填交接单 | MIME = `application/pdf`，大小 ≤ 10MB |
| BBL_HANDOVER_DONE | JPG | 必填现场照 | MIME = `image/jpeg`，大小 ≤ 5MB；需有 EXIF 时间；地理偏差 >200m 提示 |
| VORE_INBOUND_REGULATED | PDF | 必填监管入场单 | MIME = `application/pdf`，大小 ≤ 10MB |
| CUSTOMS_CLEARANCE_STARTED | PDF | 必填报关受理凭证 | MIME = `application/pdf`，大小 ≤ 10MB |

## 签字规则

| 节点 | 角色 | 方法 | 备注 |
| --- | --- | --- | --- |
| NOA_ISSUED | `transit_agent` | 手写板 (`draw`) | 要求强制 MFA |
| BBL_HANDOVER_DONE | `BBL_staff` | PIN | 需采集 geolocation + 设备指纹 |
| VORE_INBOUND_REGULATED | `customs_broker` | 手写板 (`draw`) | 支持远程签署，但需 IP 白名单 |
| CUSTOMS_CLEARANCE_STARTED | `customs_broker` | PIN | 需双因素确认（短信/OTP） |

