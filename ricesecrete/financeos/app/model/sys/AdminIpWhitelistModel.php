<?php

declare(strict_types=1);

namespace app\model\sys;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class AdminIpWhitelistModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'sy_admin_ip_whitelists';

    protected $fillable = ['ip_rule', 'remark', 'enabled'];

    public static function tableSchema(): array
    {
        return [
            'table' => 'sy_admin_ip_whitelists',
            'comment' => '后台 IP 白名单表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'ip_rule' => ['type' => 'string', 'length' => 100, 'comment' => 'IP 规则（单 IP 或 CIDR）'],
                'remark' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '备注'],
                'enabled' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 1, 'comment' => '启用：0否 1是'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'unique' => [
                ['columns' => ['ip_rule']],
            ],
            'indexes' => [
                ['columns' => ['enabled']],
            ],
        ];
    }
}
