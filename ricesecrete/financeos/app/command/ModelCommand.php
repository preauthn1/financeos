<?php

declare(strict_types=1);

namespace app\command;

use app\support\Schema\ModelSchemaSynchronizer;
use database\support\DatabaseBootstrap;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('model', '根据 app/model 中的 tableSchema 定义同步数据库表结构与索引')]
class ModelCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::OPTIONAL, '模型类名，如 Article 或 ArticleModel');
        $this->addOption('make', 'm', InputOption::VALUE_NONE, '生成带 tableSchema 的模型存根');
        $this->addOption('force', 'f', InputOption::VALUE_NONE, '生成模型时强制覆盖已存在文件');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        DatabaseBootstrap::init();

        $name = $input->getArgument('name');
        if ($input->getOption('make')) {
            if ($name === null || $name === '') {
                $output->writeln('<error>请指定模型名，例如: php webman model ArticleModel --make</error>');
                return Command::FAILURE;
            }
            return $this->makeModel($name, (bool) $input->getOption('force'), $output);
        }

        $synchronizer = new ModelSchemaSynchronizer();
        $result = $synchronizer->sync($name);

        if ($name !== null && $result['created'] === [] && $result['updated'] === [] && $result['skipped'] === []) {
            $output->writeln("<error>未找到模型: {$name}</error>");
            return Command::FAILURE;
        }

        foreach ($result['created'] as $table) {
            $output->writeln("<info>已创建表: {$table}</info>");
        }
        foreach ($result['updated'] as $table) {
            $output->writeln("<info>已更新表: {$table}</info>");
        }
        foreach ($result['skipped'] as $table) {
            $output->writeln("<comment>无需变更: {$table}</comment>");
        }

        if ($result['created'] === [] && $result['updated'] === [] && $result['skipped'] === []) {
            $output->writeln('<comment>未发现实现 DefinesTableSchema 的模型</comment>');
        }

        return Command::SUCCESS;
    }

    private function makeModel(string $name, bool $force, OutputInterface $output): int
    {
        $className = ModelSchemaSynchronizer::normalizeModelClassName($name);
        $path = base_path("app/model/{$className}.php");

        if (file_exists($path) && !$force) {
            $output->writeln("<error>模型已存在: {$path}，使用 --force 覆盖</error>");
            return Command::FAILURE;
        }

        $entityName = str_ends_with($className, 'Model') ? substr($className, 0, -5) : $className;
        $table = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $entityName) ?? $entityName) . 's';
        $stub = <<<PHP
<?php

declare(strict_types=1);

namespace app\model;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class {$className} extends Model
{
    use DefinesTableSchema;

    protected \$table = '{$table}';

    protected \$fillable = [];

    public static function tableSchema(): array
    {
        return [
            'table' => '{$table}',
            'comment' => '',
            'columns' => [
                'id' => ['type' => 'increments'],
            ],
            'timestamps' => true,
            'indexes' => [],
        ];
    }
}

PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $stub);
        $output->writeln("<info>已生成模型: {$path}</info>");

        return Command::SUCCESS;
    }
}
