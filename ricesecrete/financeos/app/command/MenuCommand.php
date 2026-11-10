<?php

declare(strict_types=1);

namespace app\command;

use app\support\Menu\AdminMenuScanner;
use app\support\Menu\AdminMenuSynchronizer;
use database\support\DatabaseBootstrap;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('menu', '扫描 admin 控制器中的 menuConfig 并同步菜单与 API 权限')]
class MenuCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('scan', 's', InputOption::VALUE_NONE, '仅扫描并输出菜单配置，不写入数据库');
        $this->addOption('fresh', null, InputOption::VALUE_NONE, '同步后删除不在扫描结果中的权限');
        $this->addOption('reseed', null, InputOption::VALUE_NONE, '清空权限表后重新同步，并恢复编辑员默认权限');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        DatabaseBootstrap::init();

        $scanner = new AdminMenuScanner();

        if ($input->getOption('scan')) {
            $data = $scanner->flatten();
            $output->writeln('<info>菜单 (' . count($data['menus']) . ')</info>');
            foreach ($data['menus'] as $menu) {
                $parent = $menu['parent_id'] > 0
                    ? (string) $menu['parent_id']
                    : ($menu['parent_slug'] !== '' ? $menu['parent_slug'] : '0');
                $output->writeln(sprintf(
                    '  [%s] %s (%s) parent=%s path=%s',
                    $menu['id'] === null ? '?' : (string) $menu['id'],
                    $menu['name'],
                    $menu['slug'],
                    $parent,
                    $menu['path']
                ));
            }
            $output->writeln('<info>API (' . count($data['apis']) . ')</info>');
            foreach ($data['apis'] as $api) {
                $parent = $api['parent_id'] > 0
                    ? (string) $api['parent_id']
                    : ($api['parent_menu_slug'] !== '' ? $api['parent_menu_slug'] : '0');
                $output->writeln(sprintf(
                    '  [%s] %s %s %s parent=%s',
                    $api['id'] === null ? '?' : (string) $api['id'],
                    $api['method'],
                    $api['path'],
                    $api['slug'],
                    $parent
                ));
            }
            return Command::SUCCESS;
        }

        $synchronizer = new AdminMenuSynchronizer($scanner);

        if ($input->getOption('reseed')) {
            $synchronizer->reseedAll();
            $output->writeln('<info>权限已重置并同步完成</info>');
            return Command::SUCCESS;
        }

        $result = $synchronizer->sync((bool) $input->getOption('fresh'));
        $output->writeln("<info>已同步菜单 {$result['menus']} 条，API {$result['apis']} 条</info>");

        if ($input->getOption('fresh') && $result['removed'] > 0) {
            $output->writeln("<comment>已删除 {$result['removed']} 条过期权限</comment>");
        }

        return Command::SUCCESS;
    }
}
