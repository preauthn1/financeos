<?php

declare(strict_types=1);

namespace app\command;

use app\service\FinanceSettleJobService;
use database\support\DatabaseBootstrap;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('finance:settle', '广告位/AI托管订单到期结算')]
class UserFinanceSettleCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        DatabaseBootstrap::init();

        $result = (new FinanceSettleJobService())->runWithLock();
        if ($result === null) {
            $output->writeln('<comment>结算任务正在执行中，跳过</comment>');

            return Command::SUCCESS;
        }

        $output->writeln(sprintf(
            '<info>结算完成：广告结算 %d 笔，广告过期 %d 笔，AI托管结算 %d 笔，提现转审 %d 笔，佣金池更新 %d 人，推广奖励 %d 人，体验金回收 %d 笔，交易分红 %d 人，邀请有效用户 %d 人，提现等级 %d 人，保证金返还 %d 笔</info>',
            $result['ad_settled'],
            $result['ad_expired'],
            $result['investment_settled'],
            $result['withdraw_moved'],
            $result['brokerage_settled'],
            $result['promotion_rewarded'],
            $result['trial_returned'],
            $result['trade_dividend_settled'] ?? 0,
            $result['invite_valid_settled'] ?? 0,
            $result['withdraw_level_updated'] ?? 0,
            $result['deposit_returned'] ?? 0,
        ));

        return Command::SUCCESS;
    }
}
