<?php

declare(strict_types=1);

namespace app\model\member;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAdCountdownConfigModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'me_user_ad_countdown_config';

    protected $fillable = [
        'user_id', 'can_click_on_no', 'can_click_on_yes',
        'button_duration', 'cancel_duration', 'apply_to_subordinates',
    ];

    public static function tableSchema(): array
    {
        return [
            'table'   => 'me_user_ad_countdown_config',
            'comment' => '广告缓冲倒计时配置',
            'columns' => [
                'id'      => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id' => ['type' => 'unsignedBigInteger', 'comment' => '用户ID'],
                'can_click_on_no'          => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '选否时可点击'],
                'can_click_on_yes'         => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '选是时可点击'],
                'button_duration'       => ['type' => 'unsignedBigInteger', 'default' => 3, 'comment' => '是按钮loading时长(秒)'],
                'cancel_duration'       => ['type' => 'unsignedBigInteger', 'default' => 3, 'comment' => '否按钮loading时长(秒)'],
                'apply_to_subordinates' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '对下级生效:0否 1是'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'unique'  => [['columns' => ['user_id']]],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
