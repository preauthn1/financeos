<?php

declare(strict_types=1);

namespace app\model\member;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRealNameAuthModel extends Model
{
    use DefinesTableSchema;
    use SoftDeletes;

    protected $table = 'me_user_real_name_auth';

    protected $fillable = [
        'user_id', 'real_name', 'id_card', 'id_card_front', 'id_card_back',
        'hand_hold_photo', 'document_type', 'status', 'remark',
        'admin_id', 'audited_at',
    ];

    public static function tableSchema(): array
    {
        return [
            'table'   => 'me_user_real_name_auth',
            'comment' => '用户实名认证表',
            'columns' => [
                'id'        => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id'   => ['type' => 'unsignedBigInteger', 'comment' => '用户ID'],
                'real_name' => ['type' => 'string', 'length' => 50, 'default' => '', 'comment' => '真实姓名'],
                'id_card'   => ['type' => 'string', 'length' => 50, 'default' => '', 'comment' => '身份证号'],
                'id_card_front'    => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '身份证正面照'],
                'id_card_back'     => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '身份证反面照'],
                'hand_hold_photo'  => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '手持身份证照'],
                'document_type'    => ['type' => 'string', 'length' => 20, 'default' => 'id_card', 'comment' => '证件类型'],
                'status'     => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '审核状态:0待审核 1通过 2拒绝'],
                'remark'     => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '审核备注'],
                'admin_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '审核人ID'],
                'audited_at' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '审核时间(unix)'],
                'deleted_at' => ['type' => 'timestamp', 'nullable' => true, 'comment' => '软删除时间'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
                'deleted_at' => '软删除时间',
            ],
            'indexes' => [
                ['columns' => ['user_id']],
                ['columns' => ['id_card']],
                ['columns' => ['status']],
            ],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
