# UAT 用例（ALB 模板包 v1）

| 编号 | 场景 | 前置条件 | 测试步骤 | 预期结果 | 备注 |
| --- | --- | --- | --- | --- | --- |
| UAT-ALB-001 | 正常闭环：NOA→清关 | 批次已实例化；节点默认分派 | 1. transit agent 填 NOA，上传 PDF 并签字<br>2. forwarder 确认提货窗<br>3. last mile 完成交接上传证据并 PIN 签署<br>4. customs broker 完成监管入场与清关 | 所有节点状态 `COMPLETED`，SLA 绿色；事件日志完整；证据包导出哈希与上传一致 | 演示标准流程 |
| UAT-ALB-002 | SLA 宽限内完成 | 同 001 | 1. NOA 在起算后 23h50m 上传<br>2. 系统计算剩余 10 分钟<br>3. 节点完成后 SLA 状态回绿 | 节点状态由黄转绿；`sla_basis` 记录宽限解释 | 验证 warn 阈值 |
| UAT-ALB-003 | SLA 逾时告警 | 同 001 | 1. 故意延迟 BBL 交接至起算后 13 小时提交<br>2. 观察工作台 & 通知 | 节点标记红灯；发送邮件/Webhook；审计记录 `SLA_BREACH` 事件 | 演练罚则触发 |
| UAT-ALB-004 | 起算回退 | 同 001 | 1. NOA operator 首先填错时间并提交<br>2. approver 退回<br>3. operator 修正 `observed_time`；触发 SLA 重算 | SLA 起算切换至最新 `observed_time`；事件保留退回轨迹 | 验证回退逻辑 |
| UAT-ALB-005 | 工作时段跨周末 | 节点分派给 forwarder | 1. 将 NOA 在周五 17:00 生效<br>2. forwarder 周一 09:00 确认提货窗 | 系统排除周末停表；deadline 为周一 12:00；状态保持黄灯 | 验证 business_hours |
| UAT-ALB-006 | 停表与恢复 | 海关通道设为 red | 1. customs broker 将 `customs_channel` 设为 red<br>2. 观察 SLA 暂停<br>3. 修改为 yellow | 状态从 `PAUSED` → 重新计时；`stop_clock` 原因记录 | 验证停表机制 |
| UAT-ALB-007 | EXIF/地理异常 | last mile 用户上传交接照 | 1. 上传 EXIF 时间与 `observed_time` 偏差 20 分钟的照片<br>2. 上传定位偏差 300m 照片 | 系统提示告警但允许提交；事件记录异常；审计可导出 | 验证证据校验提示 |
| UAT-ALB-008 | 权限拒绝 | 创建内部审核员帐号 | 1. auditor 尝试编辑未分派节点<br>2. operator 尝试查看其他国家批次 | API 返回 403；前端提示无权限；事件记录 `ACCESS_DENIED` | 验证 ABAC |
| UAT-ALB-009 | 模板版本切换 | 导入 ALB v1.1（新增节点） | 1. 已有批次保持 v1<br>2. 新建批次使用 v1.1<br>3. 对比节点清单 | 旧批次不变，新批次含新节点；`country_pack` 版本记录正确 | 验证版本隔离 |
| UAT-ALB-010 | 并发上传与签字 | BBL 节点分派给 2 人 | 1. A、B 同时上传证据 & 签字<br>2. 检查是否存在脏写 | 系统采用版本号防重；最终保留时间最晚的版本；事件保留两次尝试 | 验证乐观锁 |

