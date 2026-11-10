<?php

declare(strict_types=1);

namespace app\command;

use app\model\member\UserModel;
use database\support\DatabaseBootstrap;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('member:normalize-tree', '统一 tree_id 为 /id/ 路径格式')]
class MemberNormalizeTreeCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        DatabaseBootstrap::init();

        $count = 0;
        UserModel::query()->orderBy('id')->chunk(500, function ($users) use (&$count) {
            foreach ($users as $user) {
                $normalized = $this->normalizeTreeId((string) $user->tree_id, (int) $user->id);
                if ($normalized !== (string) $user->tree_id) {
                    $user->update(['tree_id' => $normalized]);
                    $count++;
                }
            }
        });

        $output->writeln("<info>已修正 {$count} 个用户的 tree_id</info>");

        return Command::SUCCESS;
    }

    private function normalizeTreeId(string $treeId, int $userId): string
    {
        $treeId = trim($treeId);
        if ($treeId === '') {
            return '/' . $userId . '/';
        }

        if (str_contains($treeId, '/')) {
            return '/' . trim($treeId, '/') . '/';
        }

        $parts = array_values(array_filter(explode(',', trim($treeId, ','))));
        if ($parts === []) {
            return '/' . $userId . '/';
        }

        return '/' . implode('/', $parts) . '/';
    }
}
