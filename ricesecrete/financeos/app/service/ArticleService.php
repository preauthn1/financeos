<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\content\ArticleModel;
use app\support\ArticleCategoryDict;
use app\support\ErrorCode;

class ArticleService
{
    protected PermissionService $permissionService;
    protected DictService $dictService;

    /** @var array<string, string>|null */
    private ?array $categoryLabelMap = null;

    public function __construct(?PermissionService $permissionService = null, ?DictService $dictService = null)
    {
        $this->permissionService = $permissionService ?? new PermissionService();
        $this->dictService = $dictService ?? new DictService();
    }

    public function list(int $page, int $limit, array $filters = [], bool $publicOnly = false, ?int $adminId = null): array
    {
        $query = ArticleModel::query();

        if ($publicOnly) {
            $query->where('status', 1);
        } elseif (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        $categoryId = $this->resolveCategoryFilter($filters);
        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }
        if (!empty($filters['lang'])) {
            $query->where('lang', $this->normalizeLang((string) $filters['lang']));
        }
        if (!empty($filters['keyword'])) {
            $query->where('title', 'like', '%' . $filters['keyword'] . '%');
        }

        if (!$publicOnly && $adminId !== null) {
            $this->permissionService->applyDataScope($query, $adminId, 'author_id');
        }

        $total = $query->count();
        $items = $query->with(['author:id,nickname'])
            ->orderBy('sort')
            ->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(fn (ArticleModel $article) => $this->formatArticleRow($article))
            ->values()
            ->all();

        return ['total' => $total, 'items' => $items];
    }

    /** @return array{total:int,data:array<int,array<string,mixed>>} */
    public function publicLegacyList(int $page, int $limit, array $filters = []): array
    {
        $query = ArticleModel::query()->where('status', 1);

        $categoryId = $this->resolveCategoryFilter($filters);
        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }
        if (!empty($filters['lang'])) {
            $query->where('lang', $this->normalizeLang((string) $filters['lang']));
        }

        $total = (int) $query->count();
        $items = $query->orderBy('sort')
            ->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(fn (ArticleModel $row) => [
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'thumb' => (string) ($row->thumb ?? ''),
                'content' => $row->content ? nl2br((string) $row->content) : '',
            ])
            ->values()
            ->all();

        return ['total' => $total, 'data' => $items];
    }

    /** @return array{title:string,thumb:string,content:string} */
    public function publicLegacyDetail(int $id, string $type = '', ?string $lang = null): array
    {
        $query = ArticleModel::query()->where('status', 1);
        if ($id > 0) {
            $query->where('id', $id);
        } elseif ($type !== '') {
            $query->where('category_id', (int) $type);
        } else {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '文章ID或类型无效');
        }

        if ($lang !== null && $lang !== '') {
            $query->where('lang', $this->normalizeLang($lang));
        }

        $article = $query->orderBy('sort')->orderBy('id', 'desc')->first();
        if (!$article) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '暂无数据');
        }

        return [
            'title' => (string) $article->title,
            'thumb' => (string) ($article->thumb ?? ''),
            'content' => html_entity_decode((string) ($article->content ?? '')),
        ];
    }

    private function normalizeLang(string $lang): string
    {
        $lang = strtolower(trim($lang));
        return match ($lang) {
            'zh', 'zh_cn', 'zh-cn', 'cn' => 'zh-cn',
            'pt', 'pt-br', 'pt_br' => 'pt-br',
            default => $lang,
        };
    }

    /** @param array<string, mixed> $filters */
    private function resolveCategoryFilter(array $filters): ?int
    {
        if (isset($filters['category_id']) && $filters['category_id'] !== '' && $filters['category_id'] !== null) {
            return (int) $filters['category_id'];
        }
        if (isset($filters['type']) && $filters['type'] !== '' && $filters['type'] !== null) {
            return (int) $filters['type'];
        }

        return null;
    }

    public function find(int $id, bool $publicOnly = false, ?int $adminId = null): ArticleModel
    {
        $article = ArticleModel::with(['author:id,nickname'])->find($id);
        if (!$article) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '文章不存在');
        }
        if ($publicOnly && $article->status !== 1) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '文章不存在');
        }
        if (!$publicOnly && $adminId !== null) {
            $this->assertCanAccess($adminId, (int) $article->author_id);
        }
        return $this->appendCategoryMeta($article);
    }

    public function create(array $data, int $authorId): ArticleModel
    {
        $data['author_id'] = $authorId;
        $data = $this->normalizeArticleData($data);
        return $this->appendCategoryMeta(ArticleModel::create($data));
    }

    public function update(int $id, array $data, ?int $adminId = null): ArticleModel
    {
        $article = ArticleModel::find($id);
        if (!$article) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '文章不存在');
        }
        if ($adminId !== null) {
            $this->assertCanAccess($adminId, (int) $article->author_id);
        }
        $article->update($this->normalizeArticleData($data));
        return $this->appendCategoryMeta($article->fresh(['author:id,nickname']));
    }

    public function delete(int $id, ?int $adminId = null): void
    {
        $article = ArticleModel::find($id);
        if (!$article) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '文章不存在');
        }
        if ($adminId !== null) {
            $this->assertCanAccess($adminId, (int) $article->author_id);
        }
        $article->delete();
    }

    public function incrementView(int $id): void
    {
        ArticleModel::where('id', $id)->increment('view_count');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function exportRows(?int $adminId = null): array
    {
        $query = ArticleModel::query()->with(['author:id,nickname']);
        if ($adminId !== null) {
            $this->permissionService->applyDataScope($query, $adminId, 'author_id');
        }

        $labelMap = $this->categoryLabelMap();
        return $query->orderBy('id')->get()->map(static function (ArticleModel $article) use ($labelMap) {
            $categoryId = $article->category_id;
            return [
                'id' => $article->id,
                'title' => $article->title,
                'summary' => $article->summary,
                'category_id' => $categoryId,
                'category' => $categoryId !== null ? ($labelMap[(string) $categoryId] ?? (string) $categoryId) : '',
                'author' => $article->author?->nickname ?? '',
                'status' => $article->status,
                'view_count' => $article->view_count,
                'created_at' => (string) $article->created_at,
            ];
        })->all();
    }

    public function importRows(array $rows, int $authorId): int
    {
        $imported = 0;
        foreach ($rows as $row) {
            if (empty($row['title'])) {
                continue;
            }
            ArticleModel::create($this->normalizeArticleData([
                'title' => (string) $row['title'],
                'summary' => (string) ($row['summary'] ?? ''),
                'content' => (string) ($row['content'] ?? (string) ($row['summary'] ?? '')),
                'category_id' => (int) ($row['category_id'] ?? 0) ?: null,
                'status' => (int) ($row['status'] ?? 0),
                'author_id' => $authorId,
            ]));
            $imported++;
        }

        return $imported;
    }

    /** @return array<string, string> */
    public function categoryOptions(): array
    {
        return $this->categoryLabelMap();
    }

    /** @return array<int, array{label:string,value:string}> */
    public function categoryOptionItems(): array
    {
        $items = [];
        foreach ($this->categoryLabelMap() as $value => $label) {
            $items[] = ['label' => $label, 'value' => (string) $value];
        }

        usort($items, static fn (array $a, array $b): int => (int) $a['value'] <=> (int) $b['value']);

        return $items;
    }

    /** @return array<string, mixed> */
    public function formatArticleRow(ArticleModel $article): array
    {
        return $this->appendCategoryMeta($article)->toArray();
    }

    private function appendCategoryMeta(ArticleModel $article): ArticleModel
    {
        $value = $article->category_id;
        $article->setAttribute(
            'category_label',
            $value !== null ? ($this->categoryLabelMap()[(string) $value] ?? '') : ''
        );

        return $article;
    }

    /** @return array<string, string> */
    private function categoryLabelMap(): array
    {
        if ($this->categoryLabelMap !== null) {
            return $this->categoryLabelMap;
        }

        $map = [];
        foreach ($this->dictService->getByCode(ArticleCategoryDict::CODE) as $item) {
            $map[(string) $item['value']] = (string) $item['label'];
        }

        if ($map === []) {
            foreach (ArticleCategoryDict::defaultItems() as $item) {
                $map[$item['value']] = $item['label'];
            }
        }

        $this->categoryLabelMap = $map;
        return $map;
    }

    /** @param array<string, mixed> $data */
    private function normalizeArticleData(array $data): array
    {
        if (array_key_exists('category_id', $data)) {
            $categoryId = $data['category_id'];
            if ($categoryId === '' || $categoryId === null || (int) $categoryId <= 0) {
                $data['category_id'] = null;
            } else {
                $data['category_id'] = (int) $categoryId;
                $this->assertValidCategoryId((int) $data['category_id']);
            }
        }

        return $data;
    }

    private function assertValidCategoryId(int $categoryId): void
    {
        if (!isset($this->categoryLabelMap()[(string) $categoryId])) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '文章分类无效');
        }
    }

    private function assertCanAccess(int $adminId, int $ownerId): void
    {
        if (!$this->permissionService->canAccessOwnedRecord($adminId, $ownerId)) {
            throw new BusinessException(ErrorCode::FORBIDDEN, '无权访问该数据');
        }
    }
}
