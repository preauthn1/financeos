<?php

declare(strict_types=1);

namespace app\support\Schema;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Capsule\Manager as DB;
use ReflectionClass;

class ModelSchemaSynchronizer
{
    /** @var array<string, int> */
    private const SYNC_ORDER = [
        // sys 模块 (sy_)
        'AdminIpWhitelistModel' => 5,
        'AdminModel' => 10,
        'RoleModel' => 20,
        'PermissionModel' => 30,
        'DictTypeModel' => 35,
        'AdminNotificationModel' => 45,
        'ArticleModel' => 50,
        'SettingModel' => 60,
        'LangModel' => 62,
        'LangItemModel' => 63,
        'LangItemDetailModel' => 64,
        'AdminLogModel' => 70,
        'AdminRoleModel' => 80,
        'DictItemModel' => 85,
        'RolePermissionModel' => 90,
        // member 模块 (me_)
        'UserModel'            => 100, // 主表，最优先
        'UserCardModel'        => 110,
        'UserRealNameAuthModel'=> 115,
        'UserWaterModel'       => 120,
        'UserVirtualModel'     => 130,
        'UserVirtualLogModel'  => 135,
        'UserLevelModel'       => 140,
        'UserLoginModel'       => 150,
        'UserLogModel'         => 155,
        'UserIpModel'          => 160,
        'UserMessageModel'     => 165,
        'UserCreditScoreModel'    => 170,
        'UserCreditScoreLogModel' => 171,
        'UserBalanceModel'     => 180,
        'UserBrokeragePoolModel'=> 185,
        'UserAddressModel'     => 190,
        'UserAdCountdownConfigModel' => 195,
        'UserAdRatioConfigModel'     => 200,
        'LevelTeamModel'             => 205,
        'LevelTeamInvestTimeModel'   => 206,
        'TeamActiveRewardModel'      => 210,
        'TeamActiveRewardV2Model'    => 215,
        'TaskSummaryV2Model'         => 220,
        'PromotionRewardDayModel'    => 225,
        'TradeDividendDailyModel'    => 228,
        'WithdrawLevelDepositModel'  => 229,
        // pay 模块 (pa_)
        'PayPlatformModel'    => 300,
        'RechargeOrderModel'  => 310,
        'WithdrawOrderModel'  => 320,
        // ad 模块 (ad_)
        'AdProductOrderModel' => 400,
        'AdPlanModel' => 405,
        'AdPlanDaysConfigModel' => 406,
        'AdConfigModel' => 409,
        'AdPlanUserModel' => 407,
        'AdPlanUserDaysConfigModel' => 408,
        'AdAdvertisSpaceModel' => 410,
        'AdAdvertisNumModel' => 420,
        // invest 模块 (iv_)
        'InvestmentProductModel'      => 500,
        'InvestmentProductOrderModel' => 510,
        'InvestmentSpeedCodeModel'    => 515,
        // activity 模块 (act_)
        'ActivityModel'       => 600,
        'ActivityRecordModel' => 610,
        'ActivityRechargeBonusModel' => 612,
        'ActivityPasswordModel' => 613,
        'ActivityPasswordRecordModel' => 614,
        'ActivityInviteTierModel' => 615,
        'TurntableModel' => 620,
        'TurntablePrizeModel' => 625,
        'TurntableLogModel' => 630,
        'TurntableCountModel' => 635,
        'PddTurntableModel' => 640,
        'PddTurntableRecordModel' => 645,
        'PddTurntableCountModel' => 648,
        // ops 模块 (toc_)
        'TeamOperationCenterModel'      => 700,
        'TeamOperationCenterIssueModel' => 710,
        'ServiceCenterModel' => 720,
        'ServiceCenterApplicationModel' => 725,
        // content 模块 (co_)
        'BannerModel' => 55,
        'NoticeModel' => 56,
        'NoticeInfoModel' => 57,
        'CustomerServiceLinkModel' => 58,
        'VideoModel' => 800,
    ];

    /**
     * @return array<int, array{class: class-string, schema: array}>
     */
    public function discover(?string $modelName = null): array
    {
        $models = [];

        $patterns = [
            ['glob' => base_path('app/model/*.php'), 'namespace' => 'app\\model'],
            ['glob' => base_path('app/model/sys/*.php'), 'namespace' => 'app\\model\\sys'],
            ['glob' => base_path('app/model/content/*.php'), 'namespace' => 'app\\model\\content'],
            ['glob' => base_path('app/model/member/*.php'), 'namespace' => 'app\\model\\member'],
            ['glob' => base_path('app/model/pay/*.php'), 'namespace' => 'app\\model\\pay'],
            ['glob' => base_path('app/model/ad/*.php'), 'namespace' => 'app\\model\\ad'],
            ['glob' => base_path('app/model/invest/*.php'), 'namespace' => 'app\\model\\invest'],
            ['glob' => base_path('app/model/activity/*.php'), 'namespace' => 'app\\model\\activity'],
            ['glob' => base_path('app/model/ops/*.php'), 'namespace' => 'app\\model\\ops'],
        ];

        foreach ($patterns as $pattern) {
            foreach (glob($pattern['glob']) ?: [] as $file) {
                $baseName = basename($file, '.php');
                if ($modelName !== null) {
                    $target = self::normalizeModelClassName($modelName);
                    if (strcasecmp($baseName, $target) !== 0) {
                        continue;
                    }
                }

                $class = $pattern['namespace'] . '\\' . $baseName;
                if (!class_exists($class)) {
                    continue;
                }

                $reflection = new ReflectionClass($class);
                if ($reflection->isAbstract() || !$reflection->hasMethod('tableSchema')) {
                    continue;
                }

                if (!in_array(DefinesTableSchema::class, class_uses($class), true)) {
                    continue;
                }

                $schema = $class::tableSchema();
                $schema['table'] = $schema['table'] ?? $this->guessTableName($baseName);

                $models[] = [
                    'class' => $class,
                    'name' => $baseName,
                    'schema' => $schema,
                    'order' => self::SYNC_ORDER[$baseName] ?? 1000,
                ];
            }
        }

        usort($models, static fn (array $a, array $b) => $a['order'] <=> $b['order']);

        return $models;
    }

    /**
     * @return array{created: string[], updated: string[], skipped: string[]}
     */
    public function sync(?string $modelName = null): array
    {
        $result = ['created' => [], 'updated' => [], 'skipped' => []];
        $models = $this->discover($modelName);

        if ($models === []) {
            return $result;
        }

        foreach ($models as $model) {
            $table = $model['schema']['table'];
            $schema = $model['schema'];

            if (!DB::schema()->hasTable($table)) {
                DB::schema()->create($table, function ($blueprint) use ($schema) {
                    SchemaBlueprintBuilder::apply($blueprint, $schema, true);
                });
                $result['created'][] = $table;
                continue;
            }

            $structureChanged = $this->tableNeedsUpdate($schema);
            $commentChanged = SchemaCommentSynchronizer::commentsNeedUpdate($schema);

            if ($structureChanged) {
                DB::schema()->table($table, function ($blueprint) use ($schema) {
                    SchemaBlueprintBuilder::apply($blueprint, $schema, false);
                });
            }

            if ($commentChanged) {
                SchemaCommentSynchronizer::sync($schema);
            }

            if ($structureChanged || $commentChanged) {
                $result['updated'][] = $table;
            } else {
                $result['skipped'][] = $table;
            }
        }

        return $result;
    }

    private function tableNeedsUpdate(array $schema): bool
    {
        $table = $schema['table'];
        $builder = DB::schema();

        foreach ($schema['columns'] ?? [] as $name => $definition) {
            if (!$builder->hasColumn($table, $name)) {
                return true;
            }
        }

        foreach ($schema['indexes'] ?? [] as $index) {
            $columns = $index['columns'] ?? [];
            if ($columns === []) {
                continue;
            }
            $name = $index['name'] ?? SchemaBlueprintBuilder::indexName($table, $columns);
            if (!$builder->hasIndex($table, $name)) {
                return true;
            }
        }

        foreach ($schema['unique'] ?? [] as $unique) {
            $columns = $unique['columns'] ?? [];
            if ($columns === []) {
                continue;
            }
            $name = $unique['name'] ?? SchemaBlueprintBuilder::uniqueName($table, $columns);
            if (!$builder->hasIndex($table, $name)) {
                return true;
            }
        }

        return false;
    }

    private function guessTableName(string $baseName): string
    {
        $name = str_ends_with($baseName, 'Model') ? substr($baseName, 0, -5) : $baseName;

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name) ?? $name) . 's';
    }

    public static function normalizeModelClassName(string $name): string
    {
        $name = ucfirst($name);

        return str_ends_with($name, 'Model') ? $name : $name . 'Model';
    }
}
