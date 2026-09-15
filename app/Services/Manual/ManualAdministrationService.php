<?php

namespace App\Services\Manual;

use App\Models\ManualArticle;
use App\Models\ManualCategory;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ManualAdministrationService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $data */
    public function createArticle(array $data, User $user): ManualArticle
    {
        return DB::transaction(function () use ($data, $user): ManualArticle {
            $article = ManualArticle::create($this->articleAttributes($data, $user));
            $this->auditLogger->record('manual.article.created', $article, [], $this->snapshot($article));

            return $article;
        });
    }

    /** @param array<string, mixed> $data */
    public function updateArticle(ManualArticle $article, array $data, User $user): ManualArticle
    {
        return DB::transaction(function () use ($article, $data, $user): ManualArticle {
            $before = $this->snapshot($article);
            $article->update($this->articleAttributes($data, $user, $article));
            $article->refresh();
            $this->auditLogger->record('manual.article.updated', $article, $before, $this->snapshot($article));

            return $article;
        });
    }

    public function publishArticle(ManualArticle $article, User $user): ManualArticle
    {
        return $this->changePublication($article, true, $user);
    }

    public function disableArticle(ManualArticle $article, User $user): ManualArticle
    {
        return $this->changePublication($article, false, $user);
    }

    public function deleteArticle(ManualArticle $article, User $user): void
    {
        DB::transaction(function () use ($article, $user): void {
            $before = $this->snapshot($article);
            $article->delete();
            $this->auditLogger->record('manual.article.deleted', $article, $before, ['deleted' => true, 'user_id' => $user->id]);
        });
    }

    /** @param array<string, mixed> $data */
    public function createCategory(array $data, User $user): ManualCategory
    {
        return DB::transaction(function () use ($data): ManualCategory {
            $category = ManualCategory::create($data);
            $this->auditLogger->record('manual.category.created', $category, [], $this->categorySnapshot($category));

            return $category;
        });
    }

    /** @param array<string, mixed> $data */
    public function updateCategory(ManualCategory $category, array $data, User $user): ManualCategory
    {
        return DB::transaction(function () use ($category, $data): ManualCategory {
            $before = $this->categorySnapshot($category);
            $category->update($data);
            $category->refresh();
            $this->auditLogger->record('manual.category.updated', $category, $before, $this->categorySnapshot($category));

            return $category;
        });
    }

    public function deleteCategory(ManualCategory $category, User $user): void
    {
        if ($category->articles()->exists()) {
            throw new DomainException('No puedes eliminar una categoria que tiene tutoriales. Desactivala o mueve sus articulos primero.');
        }

        DB::transaction(function () use ($category, $user): void {
            $before = $this->categorySnapshot($category);
            $category->delete();
            $this->auditLogger->record('manual.category.deleted', $category, $before, ['deleted' => true, 'user_id' => $user->id]);
        });
    }

    /** @param array<string, mixed> $data */
    private function articleAttributes(array $data, User $user, ?ManualArticle $article = null): array
    {
        $published = ($data['status'] ?? 'disabled') === 'published';

        return [
            'category_id' => $data['category_id'],
            'title' => $data['title'],
            'slug' => $data['slug'],
            'summary' => $data['summary'],
            'level' => $data['level'],
            'body' => $data['body'] ?? null,
            'estimated_minutes' => $data['estimated_minutes'],
            'roles_json' => $this->lines($data['roles'] ?? []),
            'keywords_json' => $this->lines($data['keywords'] ?? null),
            'prerequisites_json' => $this->lines($data['prerequisites'] ?? null),
            'steps_json' => $this->lines($data['steps'] ?? null),
            'required_fields_json' => $this->lines($data['required_fields'] ?? null),
            'expected_result' => $data['expected_result'] ?? null,
            'common_errors_json' => $this->lines($data['common_errors'] ?? null),
            'blocked_action' => $data['blocked_action'] ?? null,
            'escalation_role' => $data['escalation_role'] ?? null,
            'module' => $data['module'] ?? null,
            'permission' => $data['permission'] ?? null,
            'route_name' => $data['route_name'] ?? null,
            'sort_order' => $data['sort_order'],
            'active' => $published,
            'version' => $data['version'],
            'published_at' => $published ? ($article?->published_at ?? now()) : null,
            'updated_by' => $user->id,
        ];
    }

    private function changePublication(ManualArticle $article, bool $published, User $user): ManualArticle
    {
        return DB::transaction(function () use ($article, $published, $user): ManualArticle {
            $before = $this->snapshot($article);
            $article->update([
                'active' => $published,
                'published_at' => $published ? now() : null,
                'updated_by' => $user->id,
            ]);
            $article->refresh();
            $this->auditLogger->record($published ? 'manual.article.published' : 'manual.article.disabled', $article, $before, $this->snapshot($article));

            return $article;
        });
    }

    /** @param string|array<int, string>|null $value */
    private function lines(string|array|null $value): array
    {
        $values = is_array($value) ? $value : (preg_split('/\r\n|\r|\n/', (string) $value) ?: []);

        return collect($values)
            ->map(fn ($line): string => trim((string) $line))
            ->filter()
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function snapshot(Model $article): array
    {
        return [
            'id' => $article->getKey(),
            'title' => $article->getAttribute('title'),
            'slug' => $article->getAttribute('slug'),
            'category_id' => $article->getAttribute('category_id'),
            'level' => $article->getAttribute('level'),
            'active' => (bool) $article->getAttribute('active'),
            'version' => $article->getAttribute('version'),
            'updated_by' => $article->getAttribute('updated_by'),
        ];
    }

    /** @return array<string, mixed> */
    private function categorySnapshot(ManualCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'active' => (bool) $category->active,
            'sort_order' => $category->sort_order,
        ];
    }
}
