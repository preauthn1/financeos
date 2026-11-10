<?php

declare(strict_types=1);

use Webman\Route;
use app\admin\controller\AuthController;
use app\admin\controller\AdminController;
use app\admin\controller\RoleController;
use app\admin\controller\PermissionController;
use app\admin\controller\ArticleController as AdminArticleController;
use app\admin\controller\BannerController;
use app\admin\controller\VideoController;
use app\admin\controller\NoticeController;
use app\admin\controller\CustomerServiceController;
use app\admin\controller\SettingController;
use app\admin\controller\LogController;
use app\admin\controller\DashboardController;
use app\admin\controller\UploadController;
use app\admin\controller\IpWhitelistController;
use app\admin\controller\LangController;
use app\admin\controller\LangTextController;
use app\admin\controller\DictController;
use app\admin\controller\NotificationController;
use app\admin\controller\MemberController;
use app\admin\controller\MemberCardController;
use app\admin\controller\MemberRealNameController;
use app\admin\controller\MemberMessageController;
use app\admin\controller\MemberWaterController;
use app\admin\controller\MemberIpController;
use app\admin\controller\MemberCreditScoreController;
use app\admin\controller\MemberCreditScoreLogController;
use app\admin\controller\MemberWoolPartyController;
use app\admin\controller\MemberBrokeragePoolController;
use app\admin\controller\MemberVirtualController;
use app\admin\controller\PayPlatformController;
use app\admin\controller\RechargeController;
use app\admin\controller\WithdrawController;
use app\admin\controller\AdProductOrderController;
use app\admin\controller\AgentController;
use app\admin\controller\ReportController;
use app\admin\controller\ReportRegisterController;
use app\admin\controller\ReportFinanceController;
use app\admin\controller\ReportComprehensiveController;
use app\admin\controller\InvestmentProductController;
use app\admin\controller\InvestmentProductOrderController;
use app\admin\controller\InvestmentSpeedCodeController;
use app\admin\controller\InvestmentSettlementTimeController;
use app\admin\controller\InvestmentProductSessionController;
use app\admin\controller\ActivityController;
use app\admin\controller\ActivityConfigController;
use app\admin\controller\ActivityRecordController;
use app\admin\controller\ServiceCenterController;
use app\admin\controller\TradeDividendConfigController;
use app\admin\controller\TeamOperationCenterController;
use app\admin\controller\TeamOperationCenterIssueController;
use app\admin\controller\MemberRwController;
use app\admin\controller\LevelTeamController;
use app\admin\controller\AdPlanController;
use app\admin\controller\AdPlanUserController;
use app\admin\controller\AdConfigController;
use app\middleware\AuthMiddleware;
use app\middleware\AdminLogMiddleware;
use app\middleware\IpWhitelistMiddleware;

// CORS 预检请求（OPTIONS 不走路由匹配时中间件不会执行，需显式注册）
Route::options('/admin/{path:.+}', fn () => response('', 204));

// 无需认证
Route::get('/admin/captcha', [AuthController::class, 'captcha'])->middleware([IpWhitelistMiddleware::class]);
Route::get('/admin/login-status', [AuthController::class, 'loginStatus'])->middleware([IpWhitelistMiddleware::class]);
Route::post('/admin/login', [AuthController::class, 'login'])->middleware([IpWhitelistMiddleware::class]);
Route::post('/admin/refresh', [AuthController::class, 'refresh'])->middleware([IpWhitelistMiddleware::class]);

// 需要认证
Route::group('/admin', function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/menus', [AuthController::class, 'menus']);
    Route::get('/server-time', [AuthController::class, 'serverTime']);
    Route::get('/me/google-auth/setup', [AuthController::class, 'googleAuthSetup']);
    Route::post('/me/google-auth/bind', [AuthController::class, 'googleAuthBind']);

    Route::get('/dashboard/total', [DashboardController::class, 'total']);
    Route::get('/dashboard/overview', [DashboardController::class, 'overview']);
    Route::get('/dashboard/trend', [DashboardController::class, 'trend']);
    Route::get('/dashboard/alerts', [DashboardController::class, 'alerts']);
    Route::get('/dashboard/userAccessSource', [DashboardController::class, 'userAccessSource']);
    Route::get('/dashboard/weeklyUserActivity', [DashboardController::class, 'weeklyUserActivity']);
    Route::get('/dashboard/monthlySales', [DashboardController::class, 'monthlySales']);

    Route::get('/admins', [AdminController::class, 'index']);
    Route::post('/admins', [AdminController::class, 'store']);
    Route::put('/admins/{id:\d+}', [AdminController::class, 'update']);
    Route::delete('/admins/{id:\d+}', [AdminController::class, 'destroy']);
    Route::put('/admins/{id:\d+}/password', [AdminController::class, 'updatePassword']);
    Route::delete('/admins/{id:\d+}/google-auth', [AdminController::class, 'clearGoogleAuth']);

    Route::get('/roles', [RoleController::class, 'index']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::put('/roles/{id:\d+}', [RoleController::class, 'update']);
    Route::delete('/roles/{id:\d+}', [RoleController::class, 'destroy']);
    Route::get('/roles/{id:\d+}/permissions', [RoleController::class, 'permissions']);
    Route::put('/roles/{id:\d+}/permissions', [RoleController::class, 'assignPermissions']);

    Route::get('/permissions', [PermissionController::class, 'index']);
    Route::post('/permissions', [PermissionController::class, 'store']);
    Route::put('/permissions/{id:\d+}', [PermissionController::class, 'update']);
    Route::delete('/permissions/{id:\d+}', [PermissionController::class, 'destroy']);

    Route::get('/articles', [AdminArticleController::class, 'index']);
    Route::get('/articles/meta', [AdminArticleController::class, 'meta']);
    Route::get('/articles/export', [AdminArticleController::class, 'export']);
    Route::post('/articles/import', [AdminArticleController::class, 'import']);
    Route::get('/articles/{id:\d+}', [AdminArticleController::class, 'show']);
    Route::post('/articles', [AdminArticleController::class, 'store']);
    Route::put('/articles/{id:\d+}', [AdminArticleController::class, 'update']);
    Route::delete('/articles/{id:\d+}', [AdminArticleController::class, 'destroy']);

    Route::get('/banners', [BannerController::class, 'index']);
    Route::get('/banners/{id:\d+}', [BannerController::class, 'show']);
    Route::post('/banners', [BannerController::class, 'store']);
    Route::put('/banners/{id:\d+}', [BannerController::class, 'update']);
    Route::delete('/banners/{id:\d+}', [BannerController::class, 'destroy']);

    Route::get('/videos/cates', [VideoController::class, 'cates']);
    Route::get('/videos', [VideoController::class, 'index']);
    Route::get('/videos/{id:\d+}', [VideoController::class, 'show']);
    Route::post('/videos', [VideoController::class, 'store']);
    Route::put('/videos/{id:\d+}', [VideoController::class, 'update']);
    Route::delete('/videos/{id:\d+}', [VideoController::class, 'destroy']);

    Route::get('/notices', [NoticeController::class, 'index']);
    Route::get('/notices/{id:\d+}', [NoticeController::class, 'show']);
    Route::post('/notices', [NoticeController::class, 'store']);
    Route::put('/notices/{id:\d+}', [NoticeController::class, 'update']);
    Route::delete('/notices/{id:\d+}', [NoticeController::class, 'destroy']);

    Route::get('/customer-services', [CustomerServiceController::class, 'index']);
    Route::get('/customer-services/{id:\d+}', [CustomerServiceController::class, 'show']);
    Route::post('/customer-services', [CustomerServiceController::class, 'store']);
    Route::put('/customer-services/{id:\d+}', [CustomerServiceController::class, 'update']);
    Route::delete('/customer-services/{id:\d+}', [CustomerServiceController::class, 'destroy']);

    Route::get('/system-config', [SettingController::class, 'configBundle']);
    Route::put('/system-config', [SettingController::class, 'saveConfigBundle']);

    Route::get('/logs', [LogController::class, 'index']);

    Route::post('/upload/presign', [UploadController::class, 'presign']);

    Route::get('/ip-whitelists', [IpWhitelistController::class, 'index']);
    Route::post('/ip-whitelists', [IpWhitelistController::class, 'store']);
    Route::put('/ip-whitelists/{id:\d+}', [IpWhitelistController::class, 'update']);
    Route::delete('/ip-whitelists/{id:\d+}', [IpWhitelistController::class, 'destroy']);

    Route::get('/dicts/code/{code}', [DictController::class, 'byCode']);

    Route::get('/langs/options', [LangController::class, 'options']);
    Route::get('/langs', [LangController::class, 'index']);
    Route::get('/langs/{id:\d+}', [LangController::class, 'show']);
    Route::post('/langs', [LangController::class, 'store']);
    Route::put('/langs/{id:\d+}', [LangController::class, 'update']);
    Route::delete('/langs/{id:\d+}', [LangController::class, 'destroy']);

    Route::post('/lang-texts/export', [LangTextController::class, 'export']);
    Route::get('/lang-texts', [LangTextController::class, 'index']);
    Route::get('/lang-texts/{id:\d+}', [LangTextController::class, 'show']);
    Route::post('/lang-texts', [LangTextController::class, 'store']);
    Route::delete('/lang-texts/{id:\d+}', [LangTextController::class, 'destroy']);

    Route::get('/dicts', [DictController::class, 'index']);
    Route::post('/dicts', [DictController::class, 'store']);
    Route::put('/dicts/{id:\d+}', [DictController::class, 'update']);
    Route::delete('/dicts/{id:\d+}', [DictController::class, 'destroy']);
    Route::get('/dicts/{id:\d+}/items', [DictController::class, 'items']);
    Route::put('/dicts/{id:\d+}/items', [DictController::class, 'saveItems']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications', [NotificationController::class, 'store']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::put('/notifications/{id:\d+}/read', [NotificationController::class, 'markRead']);

    // 会员管理
    Route::get('/members', [MemberController::class, 'index']);
    Route::get('/members/export', [MemberController::class, 'export']);
    Route::get('/members/ids', [MemberController::class, 'ids']);
    Route::get('/members/{id:\d+}', [MemberController::class, 'show']);
    Route::post('/members', [MemberController::class, 'store']);
    Route::post('/members/batch-create', [MemberController::class, 'batchStore']);
    Route::put('/members/{id:\d+}', [MemberController::class, 'update']);
    Route::delete('/members/{id:\d+}', [MemberController::class, 'destroy']);
    Route::post('/members/batch', [MemberController::class, 'batch']);
    Route::put('/members/{id:\d+}/toggle', [MemberController::class, 'toggleStatus']);
    Route::put('/members/{id:\d+}/wallet', [MemberController::class, 'adjustWallet']);
    Route::put('/members/{id:\d+}/password', [MemberController::class, 'resetPassword']);
    Route::put('/members/{id:\d+}/pay-password', [MemberController::class, 'resetPayPassword']);
    Route::put('/members/{id:\d+}/parent', [MemberController::class, 'changeParent']);
    Route::get('/members/{id:\d+}/subordinates', [MemberController::class, 'subordinates']);
    Route::get('/members/{id:\d+}/team', [MemberController::class, 'teamInfo']);
    Route::get('/members/{id:\d+}/ad-buffer-config', [MemberController::class, 'getAdBufferCountdownConfig']);
    Route::put('/members/{id:\d+}/ad-buffer-config', [MemberController::class, 'updateAdBufferCountdownConfig']);
    Route::get('/members/{id:\d+}/ad-ratio-config', [MemberController::class, 'getAdRatioConfig']);
    Route::put('/members/{id:\d+}/ad-ratio-config', [MemberController::class, 'updateAdRatioConfig']);
    Route::put('/members/{id:\d+}/ad-ratio-config/toggle', [MemberController::class, 'toggleAdRatioConfig']);

    // 银行卡管理
    Route::get('/member-cards', [MemberCardController::class, 'index']);
    Route::post('/member-cards', [MemberCardController::class, 'store']);
    Route::put('/member-cards/{id:\d+}', [MemberCardController::class, 'update']);
    Route::delete('/member-cards/{id:\d+}', [MemberCardController::class, 'destroy']);

    // 实名认证
    Route::get('/member-real-names', [MemberRealNameController::class, 'index']);
    Route::post('/member-real-names', [MemberRealNameController::class, 'store']);
    Route::put('/member-real-names/{id:\d+}', [MemberRealNameController::class, 'update']);
    Route::delete('/member-real-names/{id:\d+}', [MemberRealNameController::class, 'destroy']);
    Route::put('/member-real-names/{id:\d+}/audit', [MemberRealNameController::class, 'audit']);

    // 站内信
    Route::get('/member-messages', [MemberMessageController::class, 'index']);
    Route::post('/member-messages', [MemberMessageController::class, 'store']);
    Route::post('/member-messages/broadcast', [MemberMessageController::class, 'broadcast']);
    Route::delete('/member-messages/{id:\d+}', [MemberMessageController::class, 'destroy']);

    // 资金流水
    Route::get('/member-waters', [MemberWaterController::class, 'index']);
    Route::get('/member-waters/export', [MemberWaterController::class, 'export']);
    Route::get('/member-waters/flow', [MemberWaterController::class, 'rechargeWithdrawFlow']);
    Route::get('/member-waters/meta', [MemberWaterController::class, 'meta']);
    Route::put('/member-waters/{id:\d+}/toggle', [MemberWaterController::class, 'toggle']);

    // IP 监控
    Route::get('/member-ips', [MemberIpController::class, 'index']);
    Route::get('/member-ips/analysis', [MemberIpController::class, 'analysis']);
    Route::get('/member-ips/same', [MemberIpController::class, 'sameIpUsers']);
    Route::get('/member-ips/logs', [MemberIpController::class, 'logs']);

    // 信用分
    Route::get('/member-credit-scores/overview', [MemberCreditScoreController::class, 'overview']);
    Route::get('/member-credit-scores', [MemberCreditScoreLogController::class, 'index']);
    Route::get('/member-credit-scores/export', [MemberCreditScoreLogController::class, 'export']);
    Route::post('/member-credit-scores/recalculate', [MemberCreditScoreController::class, 'recalculate']);
    Route::post('/member-credit-scores/adjust', [MemberCreditScoreController::class, 'adjust']);

    // 羊毛党
    Route::get('/member-wool-party', [MemberWoolPartyController::class, 'index']);
    Route::get('/member-wool-party/export', [MemberWoolPartyController::class, 'export']);

    // 佣金池
    Route::get('/member-brokerage-pools', [MemberBrokeragePoolController::class, 'index']);

    // 虚拟用户
    Route::get('/member-virtuals', [MemberVirtualController::class, 'index']);
    Route::put('/member-virtuals/{id:\d+}', [MemberVirtualController::class, 'update']);
    Route::post('/member-virtuals/adjust', [MemberVirtualController::class, 'adjust']);
    Route::get('/member-virtuals/logs', [MemberVirtualController::class, 'logs']);
    Route::delete('/member-virtuals/logs/{id:\d+}', [MemberVirtualController::class, 'destroyLog']);

    // 账单管理 - 支付通道
    Route::get('/pay-platforms', [PayPlatformController::class, 'index']);
    Route::get('/pay-platforms/options', [PayPlatformController::class, 'options']);
    Route::post('/pay-platforms', [PayPlatformController::class, 'store']);
    Route::get('/pay-platforms/{id:\d+}', [PayPlatformController::class, 'show']);
    Route::put('/pay-platforms/{id:\d+}', [PayPlatformController::class, 'update']);
    Route::delete('/pay-platforms/{id:\d+}', [PayPlatformController::class, 'destroy']);
    Route::put('/pay-platforms/{id:\d+}/toggle', [PayPlatformController::class, 'toggle']);

    // 账单管理 - 充值订单
    Route::get('/recharge-orders', [RechargeController::class, 'index']);
    Route::post('/recharge-orders/manual', [RechargeController::class, 'manual']);
    Route::get('/recharge-orders/{id:\d+}', [RechargeController::class, 'show']);
    Route::put('/recharge-orders/{id:\d+}/audit', [RechargeController::class, 'audit']);

    // 账单管理 - 提现订单
    Route::get('/withdraw-orders', [WithdrawController::class, 'index']);
    Route::get('/withdraw-orders/export', [WithdrawController::class, 'export']);
    Route::post('/withdraw-orders/batch', [WithdrawController::class, 'batch']);
    Route::post('/withdraw-orders/batch-message', [WithdrawController::class, 'batchMessage']);
    Route::get('/withdraw-orders/{id:\d+}', [WithdrawController::class, 'show']);
    Route::put('/withdraw-orders/{id:\d+}/audit', [WithdrawController::class, 'audit']);
    Route::put('/withdraw-orders/{id:\d+}/offline-transfer', [WithdrawController::class, 'offlineTransfer']);
    Route::put('/withdraw-orders/{id:\d+}/card', [WithdrawController::class, 'card']);
    Route::put('/withdraw-orders/{id:\d+}/start-payout', [WithdrawController::class, 'startPayout']);
    Route::put('/withdraw-orders/{id:\d+}/fail', [WithdrawController::class, 'fail']);
    Route::put('/withdraw-orders/{id:\d+}/lock', [WithdrawController::class, 'lock']);
    Route::put('/withdraw-orders/{id:\d+}/unlock', [WithdrawController::class, 'unlock']);

    // 代理管理
    Route::get('/agents', [AgentController::class, 'index']);
    Route::post('/agents', [AgentController::class, 'store']);
    Route::post('/agents/batch', [AgentController::class, 'batch']);
    Route::get('/agents/{id:\d+}/subordinates', [AgentController::class, 'subordinates']);

    // 报表管理
    Route::get('/reports/daily', [ReportController::class, 'daily']);
    Route::get('/reports/register', [ReportRegisterController::class, 'index']);
    Route::get('/reports/register/users', [ReportRegisterController::class, 'users']);
    Route::get('/reports/finance-daily', [ReportFinanceController::class, 'index']);
    Route::get('/reports/comprehensive', [ReportComprehensiveController::class, 'index']);

    // AI托管
    Route::get('/investment-products', [InvestmentProductController::class, 'index']);
    Route::post('/investment-products', [InvestmentProductController::class, 'store']);
    Route::put('/investment-products/long-order-limit', [InvestmentProductController::class, 'saveLongOrderLimit']);
    Route::get('/investment-products/{id:\d+}', [InvestmentProductController::class, 'show']);
    Route::put('/investment-products/{id:\d+}', [InvestmentProductController::class, 'update']);
    Route::put('/investment-products/{id:\d+}/toggle', [InvestmentProductController::class, 'toggle']);
    Route::get('/investment-orders', [InvestmentProductOrderController::class, 'index']);
    Route::get('/investment-orders/agent-stats', [InvestmentProductOrderController::class, 'agentStats']);
    Route::get('/investment-orders/user-records', [InvestmentProductOrderController::class, 'userRecords']);
    Route::post('/investment-orders/{id:\d+}/action', [InvestmentProductOrderController::class, 'action']);
    Route::get('/investment-orders/{id:\d+}', [InvestmentProductOrderController::class, 'show']);
    Route::get('/investment-speed-codes', [InvestmentSpeedCodeController::class, 'index']);
    Route::post('/investment-speed-codes', [InvestmentSpeedCodeController::class, 'store']);
    Route::put('/investment-speed-codes/{id:\d+}', [InvestmentSpeedCodeController::class, 'update']);
    Route::delete('/investment-speed-codes/{id:\d+}', [InvestmentSpeedCodeController::class, 'destroy']);
    Route::get('/investment-settlement-times', [InvestmentSettlementTimeController::class, 'index']);
    Route::post('/investment-settlement-times', [InvestmentSettlementTimeController::class, 'store']);
    Route::put('/investment-settlement-times/{id:\d+}', [InvestmentSettlementTimeController::class, 'update']);
    Route::delete('/investment-settlement-times/{id:\d+}', [InvestmentSettlementTimeController::class, 'destroy']);
    Route::put('/investment-settlement-times/{id:\d+}/toggle', [InvestmentSettlementTimeController::class, 'toggle']);
    Route::put('/investment-settlement-cycle', [InvestmentSettlementTimeController::class, 'saveCycle']);
    Route::get('/investment-product-sessions', [InvestmentProductSessionController::class, 'index']);
    Route::post('/investment-product-sessions', [InvestmentProductSessionController::class, 'store']);
    Route::put('/investment-product-sessions/{id:\d+}', [InvestmentProductSessionController::class, 'update']);
    Route::delete('/investment-product-sessions/{id:\d+}', [InvestmentProductSessionController::class, 'destroy']);
    Route::put('/investment-product-sessions/{id:\d+}/toggle', [InvestmentProductSessionController::class, 'toggle']);

    // 活动管理
    Route::get('/activities', [ActivityController::class, 'index']);
    Route::post('/activities', [ActivityController::class, 'store']);
    Route::get('/activities/{id:\d+}', [ActivityController::class, 'show']);
    Route::put('/activities/{id:\d+}', [ActivityController::class, 'update']);
    Route::put('/activities/{id:\d+}/status', [ActivityController::class, 'toggleStatus']);
    Route::get('/activities/{activityId:\d+}/recharge-bonuses', [ActivityConfigController::class, 'rechargeBonuses']);
    Route::post('/activities/{activityId:\d+}/recharge-bonuses', [ActivityConfigController::class, 'storeRechargeBonus']);
    Route::put('/activities/{activityId:\d+}/recharge-bonuses/{id:\d+}', [ActivityConfigController::class, 'updateRechargeBonus']);
    Route::delete('/activities/{activityId:\d+}/recharge-bonuses/{id:\d+}', [ActivityConfigController::class, 'destroyRechargeBonus']);
    Route::get('/activities/{activityId:\d+}/passwords', [ActivityConfigController::class, 'passwords']);
    Route::post('/activities/{activityId:\d+}/passwords', [ActivityConfigController::class, 'storePassword']);
    Route::delete('/activities/{activityId:\d+}/passwords/{id:\d+}', [ActivityConfigController::class, 'destroyPassword']);
    Route::get('/activities/{activityId:\d+}/invite-tiers', [ActivityConfigController::class, 'inviteTiers']);
    Route::post('/activities/{activityId:\d+}/invite-tiers', [ActivityConfigController::class, 'storeInviteTier']);
    Route::put('/activities/{activityId:\d+}/invite-tiers/{id:\d+}', [ActivityConfigController::class, 'updateInviteTier']);
    Route::delete('/activities/{activityId:\d+}/invite-tiers/{id:\d+}', [ActivityConfigController::class, 'destroyInviteTier']);
    Route::get('/activity-records', [ActivityRecordController::class, 'index']);

    // 服务中心 / 交易分红
    Route::get('/service-centers', [ServiceCenterController::class, 'index']);
    Route::post('/service-centers', [ServiceCenterController::class, 'store']);
    Route::get('/service-centers/{id:\d+}', [ServiceCenterController::class, 'show']);
    Route::put('/service-centers/{id:\d+}', [ServiceCenterController::class, 'update']);
    Route::delete('/service-centers/{id:\d+}', [ServiceCenterController::class, 'destroy']);
    Route::get('/service-center-applications', [ServiceCenterController::class, 'applications']);
    Route::put('/service-center-applications/{id:\d+}/audit', [ServiceCenterController::class, 'auditApplication']);
    Route::get('/trade-dividend/config', [TradeDividendConfigController::class, 'show']);
    Route::put('/trade-dividend/config', [TradeDividendConfigController::class, 'save']);

    // 运营中心
    Route::get('/operation-centers', [TeamOperationCenterController::class, 'index']);
    Route::put('/operation-centers/{id:\d+}/status', [TeamOperationCenterController::class, 'changeStatus']);
    Route::put('/operation-centers/{id:\d+}/audit', [TeamOperationCenterController::class, 'audit']);
    Route::get('/operation-centers/brokerage-config/{key}', [TeamOperationCenterController::class, 'brokerageConfig']);
    Route::put('/operation-centers/brokerage-config/{key}', [TeamOperationCenterController::class, 'saveBrokerageConfig']);
    Route::get('/operation-center-issues', [TeamOperationCenterIssueController::class, 'index']);

    // 会员充提流水
    Route::get('/member-rw/flow', [MemberRwController::class, 'flow']);
    Route::get('/member-rw/export', [MemberRwController::class, 'export']);

    // 广告位订单
    Route::get('/ad-product-orders', [AdProductOrderController::class, 'index']);
    Route::get('/ad-product-orders/session', [AdProductOrderController::class, 'session']);
    Route::get('/ad-product-orders/agent-stats', [AdProductOrderController::class, 'agentStats']);
    Route::get('/ad-product-orders/user-records', [AdProductOrderController::class, 'userRecords']);
    Route::get('/ad-product-orders/agent-records', [AdProductOrderController::class, 'agentRecords']);
    Route::post('/ad-product-orders/end-session', [AdProductOrderController::class, 'endSession']);
    Route::get('/ad-product-orders/{id:\d+}', [AdProductOrderController::class, 'show']);
    Route::put('/ad-product-orders/{id:\d+}', [AdProductOrderController::class, 'update']);

    // 广告位等级配置
    Route::get('/ad-configs/meta', [AdConfigController::class, 'meta']);
    Route::get('/ad-configs/passwords', [AdConfigController::class, 'passwords']);
    Route::put('/ad-configs/passwords', [AdConfigController::class, 'updatePasswords']);
    Route::get('/ad-configs/popup-dialog', [AdConfigController::class, 'popupDialog']);
    Route::put('/ad-configs/popup-dialog', [AdConfigController::class, 'savePopupDialog']);
    Route::get('/ad-configs/platform-scale', [AdConfigController::class, 'platformScale']);
    Route::put('/ad-configs/platform-scale', [AdConfigController::class, 'savePlatformScale']);
    Route::get('/ad-configs/overdue-dialog', [AdConfigController::class, 'overdueDialog']);
    Route::put('/ad-configs/overdue-dialog', [AdConfigController::class, 'saveOverdueDialog']);
    Route::get('/ad-configs/task-sessions', [AdConfigController::class, 'taskSessions']);
    Route::put('/ad-configs/task-sessions', [AdConfigController::class, 'saveTaskSessions']);
    Route::get('/ad-configs', [AdConfigController::class, 'index']);
    Route::post('/ad-configs', [AdConfigController::class, 'store']);
    Route::get('/ad-configs/{id:\d+}', [AdConfigController::class, 'show']);
    Route::put('/ad-configs/{id:\d+}', [AdConfigController::class, 'update']);
    Route::delete('/ad-configs/{id:\d+}', [AdConfigController::class, 'destroy']);
    Route::put('/ad-configs/{id:\d+}/toggle', [AdConfigController::class, 'toggle']);

    Route::get('/level-teams', [LevelTeamController::class, 'index']);
    Route::get('/level-teams/for-user/{userId:\d+}', [LevelTeamController::class, 'forUser']);
    Route::post('/level-teams', [LevelTeamController::class, 'store']);
    Route::put('/level-teams/{id:\d+}', [LevelTeamController::class, 'update']);
    Route::put('/level-teams/{id:\d+}/toggle', [LevelTeamController::class, 'toggle']);
    Route::delete('/level-teams/{id:\d+}', [LevelTeamController::class, 'destroy']);
    Route::get('/ad-plans', [AdPlanController::class, 'index']);
    Route::get('/ad-plans/meta', [AdPlanController::class, 'meta']);
    Route::get('/ad-plans/{id:\d+}', [AdPlanController::class, 'show']);
    Route::post('/ad-plans', [AdPlanController::class, 'store']);
    Route::put('/ad-plans/{id:\d+}', [AdPlanController::class, 'update']);
    Route::put('/ad-plans/{id:\d+}/toggle', [AdPlanController::class, 'toggle']);
    Route::delete('/ad-plans/{id:\d+}', [AdPlanController::class, 'destroy']);
    Route::get('/ad-plans/{id:\d+}/days', [AdPlanController::class, 'days']);
    Route::post('/ad-plan-days', [AdPlanController::class, 'storeDay']);
    Route::delete('/ad-plan-days/{id:\d+}', [AdPlanController::class, 'destroyDay']);
    Route::get('/ad-plan-users', [AdPlanUserController::class, 'index']);
    Route::get('/ad-plan-users/{userId:\d+}/plan', [AdPlanUserController::class, 'plan']);
    Route::put('/ad-plan-users/{userId:\d+}/plan', [AdPlanUserController::class, 'savePlan']);
})->middleware([IpWhitelistMiddleware::class, AuthMiddleware::class, AdminLogMiddleware::class]);
