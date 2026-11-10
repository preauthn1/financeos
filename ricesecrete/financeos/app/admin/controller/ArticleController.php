<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\admin\controller\concerns\RequiresPermission;
use app\service\ArticleService;
use app\support\CsvHelper;
use support\Request;
use Webman\Http\Response;

class ArticleController extends BaseController
{
    use DefinesAdminMenu;
    use RequiresPermission;

    public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '内容管理',
                'slug' => 'content',
                'path' => '/content',
                'icon' => 'document',
                'sort' => 10,
            ],
            'menu' => [
                'name' => '文章管理',
                'slug' => 'article-menu',
                'path' => '/content/article',
                'icon' => 'edit',
                'component' => 'views/article/index',
                'sort' => 1,
            ],
            'apis' => [
                ['name' => '文章列表', 'slug' => 'article:list', 'path' => '/admin/articles', 'method' => 'GET', 'sort' => 1],
                ['name' => '文章元数据', 'slug' => 'article:meta', 'path' => '/admin/articles/meta', 'method' => 'GET', 'sort' => 2],
                ['name' => '创建文章', 'slug' => 'article:create', 'path' => '/admin/articles', 'method' => 'POST', 'sort' => 3],
                ['name' => '更新文章', 'slug' => 'article:update', 'path' => '/admin/articles/*', 'method' => 'PUT', 'sort' => 4],
                ['name' => '删除文章', 'slug' => 'article:delete', 'path' => '/admin/articles/*', 'method' => 'DELETE', 'sort' => 5],
                ['name' => '导出文章', 'slug' => 'article:export', 'path' => '/admin/articles/export', 'method' => 'GET', 'sort' => 6],
                ['name' => '导入文章', 'slug' => 'article:import', 'path' => '/admin/articles/import', 'method' => 'POST', 'sort' => 7],
            ],
        ];
    }

    protected ArticleService $articleService;

    public function __construct(?ArticleService $articleService = null)
    {
        $this->articleService = $articleService ?? new ArticleService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['keyword', 'status', 'category_id', 'type', 'lang']);
        $result = $this->articleService->list($page, $limit, $filters, false, $this->adminId($request));
        $result['categories'] = $this->articleService->categoryOptionItems();

        return success($result);
    }

    /** 文章分类选项（兼容独立 meta 路由，Windows 需重启后路由才生效） */
    public function meta(Request $request)
    {
        return success([
            'categories' => $this->articleService->categoryOptionItems(),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $article = $this->articleService->find($id, false, $this->adminId($request));
        return success($this->articleService->formatArticleRow($article));
    }

    public function store(Request $request)
    {
        $this->requireButtonPermission($request, 'article:create');
        $data = $request->only(['title', 'summary', 'content', 'category_id', 'thumb', 'lang', 'sort', 'status']);
        if (empty($data['title'])) {
            return fail(42201, '标题不能为空');
        }
        $data['status'] = (int) ($data['status'] ?? 0);
        $article = $this->articleService->create($data, $this->adminId($request));
        return success($article, '创建成功');
    }

    public function update(Request $request, int $id)
    {
        $this->requireButtonPermission($request, 'article:update');
        $data = $request->only(['title', 'summary', 'content', 'category_id', 'thumb', 'lang', 'sort', 'status']);
        return success($this->articleService->update($id, $data, $this->adminId($request)), '更新成功');
    }

    public function destroy(Request $request, int $id)
    {
        $this->requireButtonPermission($request, 'article:delete');
        $this->articleService->delete($id, $this->adminId($request));
        return success(null, '删除成功');
    }

    public function export(Request $request): Response
    {
        $this->requireButtonPermission($request, 'article:export');
        $rows = $this->articleService->exportRows($this->adminId($request));
        $csvRows = array_map(static fn (array $row) => [
            $row['id'],
            $row['title'],
            $row['summary'],
            $row['category'],
            $row['author'],
            $row['status'],
            $row['view_count'],
            $row['created_at'],
        ], $rows);

        $content = CsvHelper::build(
            ['id', 'title', 'summary', 'category', 'author', 'status', 'view_count', 'created_at'],
            $csvRows
        );

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="articles_' . date('YmdHis') . '.csv"',
        ]);
    }

    public function import(Request $request)
    {
        $this->requireButtonPermission($request, 'article:import');
        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return fail(42201, '请上传 CSV 文件');
        }

        $rows = CsvHelper::parse((string) file_get_contents($file->getPathname()));
        $count = $this->articleService->importRows($rows, $this->adminId($request));
        return success(['imported' => $count], "成功导入 {$count} 条");
    }
}
