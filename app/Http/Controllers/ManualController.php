<?php

namespace App\Http\Controllers;

use App\Models\ManualArticle;
use App\Models\ManualCategory;
use App\Models\ManualProgress;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class ManualController extends Controller
{
    public function index(Request $request): View
    {
        return $this->renderIndex($request);
    }

    public function search(Request $request): View
    {
        return $this->renderIndex($request, true);
    }

    public function quickStart(Request $request): View
    {
        return $this->renderIndex($request, false, true);
    }

    public function show(string $slug): View
    {
        $user = auth()->user();
        $article = ManualArticle::query()
            ->with('category')
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        abort_unless($article->isVisibleTo($user), 403);

        $progress = ManualProgress::query()->updateOrCreate(
            ['user_id' => $user->id, 'article_id' => $article->id],
            ['last_viewed_at' => now()],
        );
        $relatedArticles = $this->visibleArticles($user)
            ->where('category_id', $article->category_id)
            ->where('id', '!=', $article->id)
            ->take(4)
            ->values();

        return view('manual.show', [
            'article' => $article,
            'progress' => $progress,
            'relatedArticles' => $relatedArticles,
            'moduleUrl' => $this->moduleUrl($article, $user),
            'roleName' => $user->getRoleNames()->first(),
        ]);
    }

    public function markAsRead(ManualArticle $article): RedirectResponse
    {
        $user = auth()->user();

        abort_unless($article->active && $article->isVisibleTo($user), 403);

        ManualProgress::query()->updateOrCreate(
            ['user_id' => $user->id, 'article_id' => $article->id],
            ['completed_at' => now(), 'last_viewed_at' => now()],
        );

        return redirect()
            ->route('manual.show', $article->slug)
            ->with('status', 'manual-progress-updated');
    }

    private function renderIndex(Request $request, bool $isSearch = false, bool $isQuickStart = false): View
    {
        $user = auth()->user();
        $filters = $this->validatedFilters($request);
        if ($isQuickStart) {
            $filters['level'] = 'basico';
        }

        $articles = $this->visibleArticles($user, $filters);
        $articleIds = $articles->pluck('id');
        $progressByArticle = ManualProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('article_id', $articleIds->isEmpty() ? [0] : $articleIds)
            ->get()
            ->keyBy('article_id');
        $categories = ManualCategory::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $categoryProgress = $categories->mapWithKeys(function (ManualCategory $category) use ($articles, $progressByArticle): array {
            $categoryArticles = $articles->where('category_id', $category->id);
            $completed = $categoryArticles
                ->filter(fn (ManualArticle $article): bool => $progressByArticle->get($article->id)?->completed_at !== null)
                ->count();

            return [$category->id => [
                'total' => $categoryArticles->count(),
                'completed' => $completed,
                'percentage' => $categoryArticles->isEmpty() ? 0 : (int) round(($completed / $categoryArticles->count()) * 100),
            ]];
        });
        $recentProgress = ManualProgress::query()
            ->with(['article.category'])
            ->where('user_id', $user->id)
            ->latest('last_viewed_at')
            ->limit(6)
            ->get()
            ->filter(fn (ManualProgress $progress): bool => $progress->article?->isVisibleTo($user) === true)
            ->values();
        $roleGuide = $this->roleGuide($user->getRoleNames()->first());
        $guideArticles = ManualArticle::query()
            ->with('category')
            ->published()
            ->whereIn('slug', collect($roleGuide)->pluck('slug')->all())
            ->get()
            ->filter(fn (ManualArticle $article): bool => $article->isVisibleTo($user))
            ->keyBy('slug');

        return view('manual.index', [
            'articles' => $articles,
            'categories' => $categories,
            'categoryProgress' => $categoryProgress,
            'progressByArticle' => $progressByArticle,
            'recentProgress' => $recentProgress,
            'quickActions' => $this->quickActions($articles),
            'roleGuide' => $roleGuide,
            'guideArticles' => $guideArticles,
            'filters' => $filters,
            'roleName' => $user->getRoleNames()->first(),
            'roleOptions' => Role::query()->orderBy('name')->pluck('name'),
            'moduleOptions' => $this->visibleArticles($user)->pluck('module')->filter()->unique()->sort()->values(),
            'levelOptions' => [
                'basico' => 'Básico',
                'operativo' => 'Operativo',
                'supervision' => 'Supervisión',
                'administracion' => 'Administración',
            ],
            'isSearch' => $isSearch,
            'isQuickStart' => $isQuickStart,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, ManualArticle>
     */
    private function visibleArticles(User $user, array $filters = []): Collection
    {
        $query = ManualArticle::query()
            ->with('category')
            ->published()
            ->orderBy('sort_order')
            ->orderBy('title');

        if (($filters['category'] ?? null) !== null) {
            $query->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('slug', $filters['category']));
        }

        if (($filters['level'] ?? null) !== null) {
            $query->where('level', $filters['level']);
        }

        if (($filters['module'] ?? null) !== null) {
            $query->where('module', $filters['module']);
        }

        $articles = $query->get()->filter(fn (ManualArticle $article): bool => $article->isVisibleTo($user));
        if (($filters['role'] ?? null) !== null) {
            $articles = $articles->filter(function (ManualArticle $article) use ($filters): bool {
                $roles = $article->roles_json ?? [];

                return $roles === [] || in_array($filters['role'], $roles, true);
            });
        }

        if (filter_var($filters['recommended'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $articles = $articles->filter(fn (ManualArticle $article): bool => $article->isRecommendedFor($user));
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $search = mb_strtolower($search);
            $articles = $articles->filter(function (ManualArticle $article) use ($search): bool {
                $haystack = implode(' ', [
                    $article->title,
                    $article->summary,
                    $article->body,
                    $article->module,
                    $article->category?->name,
                    implode(' ', $article->keywords_json ?? []),
                ]);

                return str_contains(mb_strtolower($haystack), $search);
            });
        }

        return $articles->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'level' => ['nullable', 'in:basico,operativo,supervision,administracion'],
            'category' => ['nullable', 'string', 'max:80'],
            'role' => ['nullable', 'string', 'max:80'],
            'module' => ['nullable', 'string', 'max:80'],
            'recommended' => ['nullable', 'boolean'],
        ]);
    }

    private function moduleUrl(ManualArticle $article, User $user): ?string
    {
        if (! $article->route_name || ! Route::has($article->route_name)) {
            return null;
        }

        return ! $article->permission || $user->can($article->permission)
            ? route($article->route_name)
            : null;
    }

    /**
     * @return array<int, array{label: string, slug: string}>
     */
    private function quickActions(Collection $articles): array
    {
        $actions = [
            ['label' => 'Crear solicitud', 'slug' => 'crear-solicitud-quirurgica'],
            ['label' => 'Reservar material', 'slug' => 'reservar-material-por-lote'],
            ['label' => 'Revisar una cirugía', 'slug' => 'control-operativo-de-una-cirugia'],
            ['label' => 'Cerrar cirugía', 'slug' => 'cerrar-una-cirugia'],
            ['label' => 'Reportar una falla', 'slug' => 'reportar-falla-tecnica'],
            ['label' => 'Inspeccionar devolución', 'slug' => 'inspeccionar-devolucion'],
            ['label' => 'Consultar inventario', 'slug' => 'buscar-inventario'],
            ['label' => 'Revisar cobranza', 'slug' => 'revisar-facturacion-y-cobranza'],
            ['label' => 'Escanear un código', 'slug' => 'escanear-codigo-trazable'],
            ['label' => 'Resolver una alerta', 'slug' => 'resolver-alertas-operativas'],
        ];

        return collect($actions)
            ->filter(fn (array $action): bool => $articles->contains('slug', $action['slug']))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{step: string, slug: string}>
     */
    private function roleGuide(?string $role): array
    {
        $guides = [
            'Instrumentista' => [
                ['step' => 'Consultar la cirugía', 'slug' => 'control-operativo-de-una-cirugia'],
                ['step' => 'Revisar material reservado', 'slug' => 'reservar-material-por-lote'],
                ['step' => 'Escanear el lote', 'slug' => 'escanear-codigo-trazable'],
                ['step' => 'Registrar consumo', 'slug' => 'cerrar-una-cirugia'],
                ['step' => 'Reportar fallas', 'slug' => 'reportar-falla-tecnica'],
                ['step' => 'Confirmar cierre', 'slug' => 'cerrar-una-cirugia'],
            ],
            'Almacen' => [
                ['step' => 'Revisar agenda', 'slug' => 'revisar-agenda-y-conflictos'],
                ['step' => 'Preparar la reserva', 'slug' => 'reservar-material-por-lote'],
                ['step' => 'Verificar lote y serie', 'slug' => 'escanear-codigo-trazable'],
                ['step' => 'Registrar salida', 'slug' => 'control-operativo-de-una-cirugia'],
                ['step' => 'Recibir devolución', 'slug' => 'inspeccionar-devolucion'],
                ['step' => 'Enviar a inspección', 'slug' => 'inspeccionar-devolucion'],
            ],
            'Jefe de Linea' => [
                ['step' => 'Revisar alertas', 'slug' => 'resolver-alertas-operativas'],
                ['step' => 'Validar cobertura', 'slug' => 'analizar-cobertura-y-forecast'],
                ['step' => 'Revisar conflictos', 'slug' => 'revisar-agenda-y-conflictos'],
                ['step' => 'Liberar o bloquear material', 'slug' => 'revisar-fallas-tecnicas'],
                ['step' => 'Supervisar cierres', 'slug' => 'cerrar-una-cirugia'],
                ['step' => 'Revisar indicadores', 'slug' => 'consultar-reportes-ejecutivos'],
            ],
            'Direccion Tecnica' => [
                ['step' => 'Revisar fallas técnicas', 'slug' => 'revisar-fallas-tecnicas'],
                ['step' => 'Inspeccionar devoluciones', 'slug' => 'inspeccionar-devolucion'],
                ['step' => 'Validar evidencia', 'slug' => 'adjuntar-documentos-y-evidencias'],
                ['step' => 'Liberar material autorizado', 'slug' => 'revisar-fallas-tecnicas'],
            ],
            'Cobranza' => [
                ['step' => 'Revisar valorizaciones', 'slug' => 'revisar-facturacion-y-cobranza'],
                ['step' => 'Actualizar factura y OC', 'slug' => 'revisar-facturacion-y-cobranza'],
                ['step' => 'Controlar deuda vencida', 'slug' => 'revisar-facturacion-y-cobranza'],
                ['step' => 'Consultar reportes', 'slug' => 'consultar-reportes-ejecutivos'],
            ],
            'Comercial' => [
                ['step' => 'Revisar solicitudes', 'slug' => 'consultar-solicitudes'],
                ['step' => 'Consultar disponibilidad', 'slug' => 'buscar-inventario'],
                ['step' => 'Revisar cobertura', 'slug' => 'analizar-cobertura-y-forecast'],
                ['step' => 'Dar seguimiento a la cuenta', 'slug' => 'consultar-reportes-ejecutivos'],
            ],
        ];

        return $guides[$role ?? ''] ?? [
            ['step' => 'Revisar el dashboard', 'slug' => 'interpretar-el-dashboard'],
            ['step' => 'Consultar solicitudes', 'slug' => 'consultar-solicitudes'],
            ['step' => 'Consultar inventario', 'slug' => 'buscar-inventario'],
            ['step' => 'Revisar seguridad y buenas prácticas', 'slug' => 'seguridad-y-buenas-practicas'],
        ];
    }
}
