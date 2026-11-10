<?php

declare(strict_types=1);

use Webman\Route;
use app\api\controller\ArticleController as ApiArticleController;
use app\api\controller\UploadController as ApiUploadController;
use app\api\controller\LoginController;
use app\api\controller\IndexController;
use app\api\controller\UserController;
use app\api\controller\RechargeController;
use app\api\controller\WithdrawalController;
use app\api\controller\MoneyLogController;
use app\api\controller\InvestmentProductController;
use app\api\controller\FinanceProductController;
use app\api\controller\TeamController;
use app\api\controller\MessageController;
use app\api\controller\BrokeragePoolController;
use app\api\controller\ActivityController;
use app\api\controller\TradeDividendController;
use app\api\controller\ServiceCenterController;
use app\api\controller\NoticeController;
use app\api\controller\BankController;
use app\api\controller\CustomerServiceController;
use app\api\controller\AdvertisController;
use app\api\controller\TaskController;
use app\api\controller\MissionController;
use app\api\controller\TeamActiveRewardController;
use app\api\controller\PddController;
use app\api\controller\ScaleController;
use app\api\controller\LevelController;
use app\api\controller\LangController as ApiLangController;
use app\api\controller\NotifyController;
use app\middleware\MemberAuthMiddleware;
use app\middleware\OptionalMemberAuthMiddleware;

Route::options('/api/{path:.+}', fn () => response('', 204));

$memberAuth = [MemberAuthMiddleware::class];
$optionalAuth = [OptionalMemberAuthMiddleware::class];
$p = static fn (string $path): string => '/api/' . ltrim($path, '/');

// 公开接口
Route::post($p('/login/doLogin'), [LoginController::class, 'doLogin']);
Route::post($p('/login/doReg'), [LoginController::class, 'doReg']);
Route::get($p('/login/sendSms'), [LoginController::class, 'sendSms']);
Route::get($p('/login/sendEmail'), [LoginController::class, 'sendEmail']);
Route::get($p('/login/refresh_token'), [LoginController::class, 'refreshToken']);
Route::post($p('/login/refresh_token'), [LoginController::class, 'refreshToken']);

Route::get($p('/index'), [IndexController::class, 'index'])->middleware($optionalAuth);
Route::get($p('/index/init'), [IndexController::class, 'init']);
Route::get($p('/index/vip_list'), [IndexController::class, 'vipList']);

Route::get($p('/login/check_lang'), [ApiLangController::class, 'checkLang']);

Route::get($p('/notice'), [NoticeController::class, 'index']);
Route::get($p('/customer_service'), [CustomerServiceController::class, 'index']);
Route::get($p('/scale/index'), [ScaleController::class, 'index']);

// 支付/代付回调（无需登录）
Route::post($p('/notify/recharge'), [NotifyController::class, 'recharge']);
Route::post($p('/notify/withdraw'), [NotifyController::class, 'withdraw']);
Route::any($p('/notify/{type}'), [NotifyController::class, 'index']);

Route::get($p('/advertis/index'), [AdvertisController::class, 'index']);
Route::get($p('/advertis/detail'), [AdvertisController::class, 'detail']);

Route::get($p('/article'), [ApiArticleController::class, 'index']);
Route::get($p('/article/detail'), [ApiArticleController::class, 'detail']);

// 需登录
Route::post($p('/login/loginOut'), [LoginController::class, 'loginOut'])->middleware($memberAuth);

Route::get($p('/user/info'), [UserController::class, 'info'])->middleware($memberAuth);
Route::post($p('/user/change_password'), [UserController::class, 'changePassword'])->middleware($memberAuth);
Route::get($p('/user/change_paypwd'), [UserController::class, 'changePaypwd'])->middleware($memberAuth);
Route::post($p('/user/change_paypwd'), [UserController::class, 'changePaypwd'])->middleware($memberAuth);
Route::post($p('/user/bind_bank'), [UserController::class, 'bindBank'])->middleware($memberAuth);
Route::get($p('/user/bind_bank_list'), [UserController::class, 'bindBankList'])->middleware($memberAuth);
Route::get($p('/user/signList'), [UserController::class, 'signList'])->middleware($memberAuth);
Route::get($p('/user/sign'), [UserController::class, 'sign'])->middleware($memberAuth);
Route::post($p('/user/realNameAuth'), [UserController::class, 'realNameAuth'])->middleware($memberAuth);
Route::post($p('/user/getRealNameAuth'), [UserController::class, 'getRealNameAuth'])->middleware($memberAuth);
Route::get($p('/user/getBalanceLog'), [UserController::class, 'getBalanceLog'])->middleware($memberAuth);
Route::get($p('/user/getTurntable'), [UserController::class, 'getTurntable'])->middleware($memberAuth);
Route::get($p('/user/onTurntable'), [UserController::class, 'onTurntable'])->middleware($memberAuth);
Route::post($p('/user/onTurntable'), [UserController::class, 'onTurntable'])->middleware($memberAuth);
Route::post($p('/user/transfer_money'), [UserController::class, 'transferMoney'])->middleware($memberAuth);
Route::get($p('/user/promotion_reward_list'), [UserController::class, 'promotionRewardList'])->middleware($memberAuth);
Route::get($p('/user/receive_promotion_reward'), [UserController::class, 'receivePromotionReward'])->middleware($memberAuth);

Route::get($p('/recharge'), [RechargeController::class, 'index'])->middleware($memberAuth);
Route::get($p('/recharge/getRechargeAddress'), [RechargeController::class, 'getRechargeAddress'])->middleware($memberAuth);
Route::post($p('/recharge/offline_pay'), [RechargeController::class, 'offlinePay'])->middleware($memberAuth);

Route::get($p('/withdrawals'), [WithdrawalController::class, 'index'])->middleware($memberAuth);
Route::post($p('/withdrawals/store'), [WithdrawalController::class, 'store'])->middleware($memberAuth);
Route::post($p('/withdrawals/deposit_upgrade'), [WithdrawalController::class, 'depositUpgrade'])->middleware($memberAuth);

Route::get($p('/money_log'), [MoneyLogController::class, 'index'])->middleware($memberAuth);
Route::get($p('/bank'), [BankController::class, 'index'])->middleware($memberAuth);

Route::get($p('/message'), [MessageController::class, 'index'])->middleware($memberAuth);
Route::get($p('/message/detail'), [MessageController::class, 'detail'])->middleware($memberAuth);
Route::get($p('/message/pop_list'), [MessageController::class, 'popList'])->middleware($memberAuth);

Route::get($p('/investment_product/index'), [InvestmentProductController::class, 'index'])->middleware($memberAuth);
Route::get($p('/investment_product/detail'), [InvestmentProductController::class, 'detail'])->middleware($memberAuth);
Route::post($p('/investment_product/buy'), [InvestmentProductController::class, 'buy'])->middleware($memberAuth);
Route::get($p('/investment_product/order_list'), [InvestmentProductController::class, 'orderList'])->middleware($memberAuth);
Route::post($p('/investment_product/speed_settle'), [InvestmentProductController::class, 'speedSettle'])->middleware($memberAuth);

Route::get($p('/finance_product'), [FinanceProductController::class, 'index'])->middleware($memberAuth);
Route::get($p('/finance_product/order_list'), [FinanceProductController::class, 'orderList'])->middleware($memberAuth);
Route::get($p('/finance_product/order_count'), [FinanceProductController::class, 'orderCount'])->middleware($memberAuth);
Route::get($p('/finance_product/details'), [FinanceProductController::class, 'details'])->middleware($memberAuth);
Route::post($p('/finance_product/overdue_config'), [FinanceProductController::class, 'overdueConfig'])->middleware($memberAuth);
Route::post($p('/finance_product/match'), [FinanceProductController::class, 'match'])->middleware($memberAuth);
Route::post($p('/finance_product/overdue_match'), [FinanceProductController::class, 'overdueMatch'])->middleware($memberAuth);
Route::get($p('/finance_product/get_finance_product_order'), [FinanceProductController::class, 'getFinanceProductOrder'])->middleware($memberAuth);

Route::get($p('/team/team_info'), [TeamController::class, 'teamInfo'])->middleware($memberAuth);
Route::get($p('/team/detail/{level:\d+}'), [TeamController::class, 'detail'])->middleware($memberAuth);

Route::get($p('/brokerage_pool'), [BrokeragePoolController::class, 'index'])->middleware($memberAuth);
Route::post($p('/brokerage_pool/receive'), [BrokeragePoolController::class, 'receive'])->middleware($memberAuth);

Route::get($p('/activitynew'), [ActivityController::class, 'index'])->middleware($memberAuth);
Route::get($p('/activitynew/join'), [ActivityController::class, 'join'])->middleware($memberAuth);
Route::get($p('/activitynew/getReward'), [ActivityController::class, 'getReward'])->middleware($memberAuth);
Route::get($p('/activitynew/recharge_bonus'), [ActivityController::class, 'rechargeBonus'])->middleware($memberAuth);
Route::get($p('/activitynew/recharge_now'), [ActivityController::class, 'rechargeNow'])->middleware($memberAuth);
Route::get($p('/activitynew/claim_register_trial'), [ActivityController::class, 'claimRegisterTrial'])->middleware($memberAuth);

Route::get($p('/trade_dividend'), [TradeDividendController::class, 'index'])->middleware($memberAuth);
Route::post($p('/trade_dividend/claim'), [TradeDividendController::class, 'claim'])->middleware($memberAuth);

Route::get($p('/service_center'), [ServiceCenterController::class, 'index'])->middleware($memberAuth);
Route::get($p('/service_center/{id:\d+}'), [ServiceCenterController::class, 'show'])->middleware($memberAuth);
Route::post($p('/service_center/{id:\d+}/apply'), [ServiceCenterController::class, 'apply'])->middleware($memberAuth);

Route::get($p('/task/video_list'), [TaskController::class, 'videoList'])->middleware($optionalAuth);
Route::get($p('/task/video_detail'), [TaskController::class, 'videoDetail'])->middleware($optionalAuth);
Route::get($p('/task/recommend_video_list'), [TaskController::class, 'recommendVideoList'])->middleware($optionalAuth);
Route::get($p('/task/list'), [TaskController::class, 'list'])->middleware($memberAuth);
Route::get($p('/task/task_info'), [TaskController::class, 'taskInfo'])->middleware($memberAuth);
Route::get($p('/task/rec_reward'), [TaskController::class, 'recReward'])->middleware($memberAuth);
Route::get($p('/task/get_rec_reward'), [TaskController::class, 'getRecReward'])->middleware($memberAuth);
Route::get($p('/mission/addVideoTask'), [MissionController::class, 'addVideoTask'])->middleware($memberAuth);

Route::get($p('/team_active_reward/indexV2'), [TeamActiveRewardController::class, 'indexV2'])->middleware($memberAuth);
Route::get($p('/team_active_reward/getRewardV2'), [TeamActiveRewardController::class, 'getRewardV2'])->middleware($memberAuth);
Route::post($p('/team_active_reward/addProxyCenterData'), [TeamActiveRewardController::class, 'addProxyCenterData'])->middleware($memberAuth);
Route::post($p('/team_active_reward/addGroupCenterData'), [TeamActiveRewardController::class, 'addGroupCenterData'])->middleware($memberAuth);
Route::post($p('/team_active_reward/addCityCenterData'), [TeamActiveRewardController::class, 'addCityCenterData'])->middleware($memberAuth);
Route::post($p('/team_active_reward/handleCenterReward'), [TeamActiveRewardController::class, 'handleCenterReward'])->middleware($memberAuth);

Route::get($p('/pdd/getTurntable'), [PddController::class, 'getTurntable'])->middleware($memberAuth);
Route::get($p('/pdd/draw'), [PddController::class, 'draw'])->middleware($memberAuth);
Route::post($p('/pdd/draw'), [PddController::class, 'draw'])->middleware($memberAuth);
Route::get($p('/pdd/updateBigPrizeProgress'), [PddController::class, 'updateBigPrizeProgress'])->middleware($memberAuth);
Route::post($p('/pdd/updateBigPrizeProgress'), [PddController::class, 'updateBigPrizeProgress'])->middleware($memberAuth);
Route::get($p('/pdd/receiveBigPrize'), [PddController::class, 'receiveBigPrize'])->middleware($memberAuth);
Route::post($p('/pdd/receiveBigPrize'), [PddController::class, 'receiveBigPrize'])->middleware($memberAuth);
Route::get($p('/pdd/receiveRecord'), [PddController::class, 'receiveRecord'])->middleware($memberAuth);
Route::get($p('/pdd/drawRecord'), [PddController::class, 'drawRecord'])->middleware($memberAuth);
Route::get($p('/pdd/invitedRecord'), [PddController::class, 'invitedRecord'])->middleware($memberAuth);
Route::get($p('/pdd/financeProductRecord'), [PddController::class, 'financeProductRecord'])->middleware($memberAuth);

Route::post($p('/level/upLevel'), [LevelController::class, 'upLevel'])->middleware($memberAuth);
Route::get($p('/level/checkPassword'), [LevelController::class, 'checkPassword'])->middleware($memberAuth);
Route::post($p('/level/checkPassword'), [LevelController::class, 'checkPassword'])->middleware($memberAuth);

Route::post($p('/upload/upload'), [ApiUploadController::class, 'store'])->middleware($memberAuth);
Route::post($p('/upload'), [ApiUploadController::class, 'store'])->middleware($memberAuth);
