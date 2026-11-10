<?php

declare(strict_types=1);

namespace app\model\invest;

use app\model\Concerns\DefinesTableSchema;
use app\model\member\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentProductOrderModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'iv_product_order';

    protected $fillable = [
        'user_id', 'level_id', 'order_no', 'cycle', 'ended_at', 'money',
        'investment_product_id', 'investment_product_name', 'profit', 'profit_amount',
        'profit_type', 'settled_at', 'settlement_times', 'started_at', 'status',
        'profit_settled_early',
    ];

    public const STATUS_PENDING = 0;
    public const STATUS_WAIT_BUY = 1;
    public const STATUS_BOUGHT = 2;
    public const STATUS_SETTLED = 3;
    public const STATUS_EXPIRED = 4;

    public static array $statusMap = [
        self::STATUS_PENDING => "\u{5F85}\u{5339}\u{914D}",
        self::STATUS_WAIT_BUY => "\u{5F85}\u{8D2D}\u{4E70}",
        self::STATUS_BOUGHT => "\u{5DF2}\u{8D2D}\u{4E70}",
        self::STATUS_SETTLED => "\u{5DF2}\u{7ED3}\u{7B97}",
        self::STATUS_EXPIRED => "\u{5DF2}\u{8FC7}\u{671F}",
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'iv_product_order', 'comment' => 'AI?????',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '??ID'],
                'user_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '??ID'],
                'level_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '??ID'],
                'order_no' => ['type' => 'string', 'length' => 64, 'default' => '', 'comment' => '???'],
                'cycle' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '????'],
                'ended_at' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '????(unix)'],
                'money' => ['type' => 'decimal', 'precision' => 20, 'scale' => 8, 'default' => 0, 'comment' => '????'],
                'investment_product_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '??ID'],
                'investment_product_name' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '????'],
                'profit' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '???'],
                'profit_amount' => ['type' => 'decimal', 'precision' => 20, 'scale' => 8, 'default' => 0, 'comment' => '????'],
                'profit_type' => ['type' => 'tinyInteger', 'default' => 2, 'comment' => '????'],
                'settled_at' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '????(unix)'],
                'settlement_times' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '????'],
                'started_at' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '????(unix)'],
                'status' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '??'],
                'profit_settled_early' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '????????'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '????',
                'updated_at' => '????',
            ],
            'indexes' => [
                ['columns' => ['user_id', 'status']],
                ['columns' => ['status']],
                ['columns' => ['order_no']],
            ],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(InvestmentProductModel::class, 'investment_product_id');
    }
}
