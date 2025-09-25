# SQLite 前后端一体开发清单 v2

本文档在“国家=项目（模板）、批次=执行、节点=可配置规则/证据/签名/SLA、供应商账号制”基础上，给出以 SQLite 为持久层的端到端实施清单。所有时间统一存储为 UTC ISO8601 字符串，界面呈现使用 Europe/Tirane 时区。

---

## 0. 范围与核心规则（锚点）

- **国家=项目模板**：每个国家维护一套节点配置，字段包含：节点名称、负责供应商（可细化到账号）、起算点（`previous|eta|creation`）、时效（小时）、是否必须上传附件、是否必须签名。
- **批次=执行实体**：管理员手工新建货物批次并指定国家，系统依据模板实例化节点。
- **供应商视角**：供应商账号仅能看到和操作分派给自己的节点；管理员拥有全局视角。

**交付**：需求澄清文档与配置示例。  
**验收**：同一国家模板可覆盖多个供应商节点；管理员与供应商权限隔离。

---

## 1. 环境与配置（SQLite）

- 使用 SQLite 数据库文件（放置于 web 根目录之外），应用启动时执行以下 PRAGMA：
  - `PRAGMA foreign_keys = ON;`
  - `PRAGMA journal_mode = WAL;`
  - `PRAGMA synchronous = NORMAL;`
  - `PRAGMA busy_timeout = 5000;`
- PHP 层统一使用 UTC 存储时间；前端展示使用 Europe/Tirane。

**交付**：环境配置说明、`.env.example`、启动脚本与健康检查清单。  
**验收**：
1. 数据库连接可达；
2. 并发写入不会因锁冲突失败；
3. 页面展示时间与数据库存储一致（UTC ↔ Europe/Tirane）。

---

## 2. 数据库结构

> 字段以 SQLite 兼容类型描述；如需 JSON 存储，使用 `TEXT` 并序列化为 JSON 字符串。

| 表 | 关键字段 |
| --- | --- |
| `countries` | `id INTEGER PK`, `code TEXT UNIQUE`, `name TEXT`
| `vendors` | `id INTEGER PK`, `name TEXT`, `contact_email TEXT`, `active INTEGER`
| `users` | `id INTEGER PK`, `vendor_id INTEGER?`, `email TEXT UNIQUE`, `password_hash TEXT`, `role TEXT`, `active INTEGER`
| `country_nodes` | `id INTEGER PK`, `country_id INTEGER`, `sort_order INTEGER`, `name TEXT`, `vendor_id INTEGER`, `default_assignee_user_id INTEGER?`, `base_type TEXT`, `sla_hours REAL`, `evidence_required INTEGER`, `signature_required INTEGER`
| `shipments` | `id INTEGER PK`, `country_id INTEGER`, `code TEXT`, `origin TEXT`, `eta_dest_airport TEXT`, `meta_json TEXT`, `created_at TEXT`
| `shipment_nodes` | `id INTEGER PK`, `shipment_id INTEGER`, `country_node_id INTEGER`, `sort_order INTEGER`, `name TEXT`, `vendor_id INTEGER`, `assignee_user_id INTEGER?`, `base_type TEXT`, `sla_hours REAL`, `evidence_required INTEGER`, `signature_required INTEGER`, `actual_time TEXT?`, `deadline_utc TEXT?`, `status TEXT`, `sla_status TEXT`, `remaining_minutes INTEGER?`
| `files` *(可选)* | `id INTEGER PK`, `path TEXT`, `mime TEXT`, `size INTEGER`, `sha256 TEXT`, `uploaded_by INTEGER`
| `evidences` *(可选)* | `id INTEGER PK`, `shipment_node_id INTEGER`, `file_id INTEGER`, `label TEXT`
| `signatures` *(可选)* | `id INTEGER PK`, `shipment_node_id INTEGER`, `user_id INTEGER`, `method TEXT`, `ip TEXT`, `device TEXT`, `geo_lat REAL?`, `geo_lng REAL?`, `created_at TEXT`
| `events` *(可选)* | `id INTEGER PK`, `shipment_id INTEGER?`, `shipment_node_id INTEGER?`, `actor_user_id INTEGER?`, `action TEXT`, `payload_json TEXT`, `created_at TEXT`

**索引**：
- `shipments(country_id, code)` 唯一；
- `shipment_nodes(shipment_id, sort_order)`；
- `shipment_nodes(vendor_id, status)` 与 `shipment_nodes(assignee_user_id, status)`；
- `users(email)`；`files(sha256)`。

**交付**：迁移脚本、索引定义、样例数据。  
**验收**：迁移一次成功；外键生效；唯一约束与索引命中。

---

## 3. 账号与权限（RBAC）

- 角色：`internal_admin`、`pm`、`vendor`、`approver`、`auditor`。
- 登录会话：基于 PHP Session 或 Laravel Sanctum，确保 CSRF token 验证；失败登录带节流策略（例如 5 分钟内最多 5 次）。
- 写权限规则：
  - 供应商账号只可操作 `assignee_user_id = self` 或 `assignee_user_id IS NULL AND vendor_id = self.vendor_id` 的节点。
  - 禁止在前序节点未完成时提交当前节点。

**交付**：权限矩阵文档、路由中间件、策略单元测试。  
**验收**：越权访问返回 403；越序提交返回 409；被禁用账号立即失去写权限。

---

## 4. 国家模板管理

- 页面提供可编辑表格：`顺序`、`节点名称`、`默认供应商`、`默认账号`、`起算点`、`时效(小时)`、`附件必传`、`签名必需`。
- 校验：首节点禁止选择 `previous` 起算；`sort_order` 唯一；默认账号必须属于默认供应商；布尔值以 0/1 存储。
- 保存后仅影响新建批次；旧批次维持原快照。

**交付**：模板列表、编辑 UI、读取/保存 API、表单验证。  
**验收**：模板调整后新建批次使用新配置；旧批次节点不受影响。

---

## 5. 供应商与账号管理

- 供应商 CRUD：名称、联系方式、启停用。
- 用户 CRUD：关联供应商、角色、重置密码、启停用。

**交付**：管理页面、API、审核日志。  
**验收**：新增供应商/账号能在模板和改派下拉中显示；停用即刻阻断登录和写操作。

---

## 6. 批次创建与列表/详情

- 表单字段：国家、批次编码（国家内唯一）、ETA、起运地、备注（JSON）。
- 创建流程：
  1. 校验编码唯一；
  2. 从 `country_nodes` 复制节点快照到 `shipment_nodes`；
  3. 计算可立即确定的 SLA（`eta`/`creation` 起算节点）。
- 列表筛选：国家、日期范围、状态、供应商等；详情展示时间轴。

**交付**：批次 CRUD API、时间轴页面、服务测试。  
**验收**：创建成功后时间轴完整；首个节点立即显示截止时间与倒计时。

---

## 7. 供应商执行（待办与节点提交）

- 我的待办：优先按 `assignee_user_id` 匹配，否则按 `vendor_id`；仅列出 `status = 'PENDING'` 节点，并按最近截止时间排序。
- 节点提交：
  - 时间字段可简化为“完成时间”；
  - 若 `evidence_required = 1`，必须上传附件；
  - 若 `signature_required = 1`，必须完成签名（手写或 PIN）。
- 提交后设置 `status = 'DONE'` 并立即推进下一个 `base_type = 'previous'` 节点的 SLA。

**交付**：待办页面、节点提交 API、附件/签名整合。  
**验收**：必填项缺失时阻止提交；提交成功后时间轴刷新、待办移除、SLA 前推。

---

## 8. SLA 引擎

- 起算点：
  - `previous` ⇒ 上一节点 `actual_time`；
  - `eta` ⇒ `shipments.eta_dest_airport`；
  - `creation` ⇒ `shipments.created_at`。
- 截止时间：`deadline = start + sla_hours`。
- 状态判定：使用全局 `warn_minutes`（默认 120，可配置）。
- 触发场景：
  1. 批次创建时为可计算节点赋值；
  2. 节点完成后推进下一节点；
  3. Cron（5 分钟）刷新所有未完成节点的 `remaining_minutes` 与 `sla_status`。
- 解释接口（可选）：返回起算依据、截止、阈值与当前状态原因。

**交付**：SLA 服务类、重算脚本、配置项、测试用例。  
**验收**：三种起算点与推进逻辑正确；时间流逝触发状态变更符合期望。

---

## 9. 分派与改派

- 模板层：定义默认供应商与账号。
- 实例层：支持在批次详情中对未完成节点进行单个或批量改派；提供跨批次批量改派并支持 dry-run。
- 改派后立即更新权限与待办；记录审计事件，并可选择通知相关方。

**交付**：改派 UI、API（预览/执行）、事件日志。  
**验收**：改派后旧责任方失去操作权，新责任方待办出现；已完成节点改派返回 409。

---

## 10. 附件与签名

- 附件：限制扩展名 `pdf/jpg/jpeg/png`、大小 ≤ 20MB；上传后计算 `sha256`；下载需鉴权与权限校验。
- 签名：支持手写画布与 PIN；记录 `user`、`role`、`ip`、`device`、可选 `geo`。
- 模板控制是否必填，提交节点时强制验证。

**交付**：上传/下载接口、签字接口、前端组件、哈希展示。  
**验收**：必传项未满足禁止提交；证据哈希前 8 位可见；非授权用户无法下载。

---

## 11. 视图与可见性

- 管理员视图：国家页、批次列表、批次详情展示全部节点与指标。
- 供应商视图：待办列表仅含自身节点；批次详情仅显示授权节点（其余可隐藏或显示只读占位）。
- 既要在前端隐藏按钮，也要在后端再次校验。

**交付**：基于角色的菜单与组件显示规则、后端校验。  
**验收**：供应商无法通过直接访问 URL 操作非己节点；管理员可查看完整数据。

---

## 12. 审计与报表

- 事件流水记录以下动作：`SHIPMENT_CREATED`、`NODE_INSTANTIATED`、`NODE_DONE`、`REASSIGNED`、`EVIDENCE_UPLOADED`、`SIGNED`、`SLA_RECALCULATED` 等。
- 报表：按国家与供应商统计 SLA 达成率、平均逾时、进行中节点数、逾时节点数，支持 CSV 导出。

**交付**：事件侧栏、报表接口、导出功能。  
**验收**：事件可回溯；报表数据与节点明细对齐。

---

## 13. 安全、备份与运维

- 安全：CSRF 防护、防止直接访问附件、严格输入校验、统一错误响应；登录与上传接口带节流策略。
- 监控：健康检查涵盖数据库、磁盘剩余、cron 心跳。
- 备份：每日执行 `VACUUM INTO backup/app-YYYYMMDD.sqlite`，保留 7–14 天；提供恢复流程。

**交付**：安全策略文档、备份脚本、运维手册。  
**验收**：可从备份恢复；健康检查通过；越权或非法上传被拒绝并记录。

---

## 验收用例（重点覆盖三条核心需求）

1. **模板多供应商**：在 ALB 模板中配置 3 个节点，分别指向不同供应商/账号，并设置不同 `sla_hours` 与名称；保存与读取无误。
2. **权限与待办**：供应商 A 登录只看到自身节点及倒计时；访问其他节点被拒绝（403）。
3. **批次执行**：管理员新建批次（国家 = ALB），节点实例化；供应商按顺序提交时间、上传附件、签名，系统计算 SLA 并标记 OK/WARN/BREACH。
4. **改派**：管理员将第 2 节点改派给供应商 B，供应商 A 立即失去操作按钮，供应商 B 在待办中看到任务。
5. **可见性**：管理员可查看全部节点；供应商只见本节点；直连接口访问非授权节点返回 403。
6. **审计与报表**：事件流记录创建/实例化/改派/完成/上传/签名/重算；报表导出与节点数据一致。

---

## 实施节奏（建议 4 周）

- **W1**：基础设施、数据模型、模板管理、权限框架。
- **W2**：批次实例化、待办、节点提交、附件/签名基础。
- **W3**：SLA 引擎、改派、审计、通知雏形、前端角色视图完善。
- **W4**：报表、备份/监控、UAT、灰度上线。

## 成功标准（SLO）

- 功能：≥95% 节点在 3 步内完成时间+附件+签名操作。
- 性能：100 批次/日、10–20 节点/批次；P95 列表 ≤ 300ms，详情 ≤ 600ms。
- 稳定性：0 证据丢失、0 审计缺失；可用性 ≥ 99.5%。

