<?php

declare(strict_types=1);

namespace app\command;

use app\model\member\UserLoginModel;
use database\support\DatabaseBootstrap;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('member:fix-login-log-type', '将登录日志 type=2 修正为 type=0')]
class MemberFixLoginLogTypeCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        DatabaseBootstrap::init();

        $count = UserLoginModel::query()->where('type', 2)->update(['type' => 0]);
        $output->writeln("<info>已修正 {$count} 条登录日志</info>");

        return Command::SUCCESS;
    }
}
