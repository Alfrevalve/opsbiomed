<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreManualArticleRequest;
use App\Http\Requests\StoreManualCategoryRequest;
use App\Http\Requests\UpdateManualArticleRequest;
use App\Http\Requests\UpdateManualCategoryRequest;
use App\Models\ManualArticle;
use App\Models\ManualCategory;
use App\Models\User;
use App\Services\Manual\ManualAdministrationService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class ManualAdminController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAccess();

        $articles = ManualArticle::query()
            ->with(['category', 'updatedBy'])
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q')->trim().'%';
                $query->where(function ($articleQuery) use ($term): void {
                    $articleQuery->where('title', 'like', $term)
                        ->orWhere('slug', 'like', $term)
                        ->orWhere('summary', 'like', $term);
                });
            })
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('level'), fn ($query) => $query->where('level', $request->string('level')->value()))
            ->when($request->input('status') === 'published', fn ($query) => $query->where('active', true))
            ->when($request->input('status') === 'disabled', fn ($query) => $query->where('active', false))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate(25)
            ->withQueryString();

        return view('admin.manual.index', [
            'articles' => $articles,
            'categories' => ManualCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
            'roles' => Role::query()->orderBy('name')->pluck('name'),
            'levels' => $this->levels(),
        ]);
    }

    public function create(): View
    {
        $this->authorizeAccess();

        return view('admin.manual.create', $this->formOptions());
    }

    public function store(StoreManualArticleRequest $request, ManualAdministrationService $service): RedirectResponse
    {
        $this->authorizeAccess();
        $article = $service->createArticle($request->validated(), $request->user());

        return redirect()->route('admin.manual.edit', $article)
            ->with('status', 'Tutorial creado correctamente.');
    }

    public function edit(ManualArticle $article): View
    {
        $this->authorizeAccess();
        $article->load(['category', 'updatedBy']);

        return view('admin.manual.edit', $this->formOptions($article));
    }

    public function update(UpdateManualArticleRequest $request, ManualArticle $article, ManualAdministrationService $service): RedirectResponse
    {
        $this->authorizeAccess();
        $service->updateArticle($article, $request->validated(), $request->user());

        return redirect()->route('admin.manual.edit', $article)
            ->with('status', 'Tutorial actualizado correctamente.');
    }

    public function publish(ManualArticle $article, ManualAdministrationService $service): RedirectResponse
    {
        $this->authorizeAccess();
        $service->publishArticle($article, request()->user());

        return back()->with('status', 'Tutorial publicado correctamente.');
    }

    public function disable(ManualArticle $article, ManualAdministrationService $service): RedirectResponse
    {
        $this->authorizeAccess();
        $service->disableArticle($article, request()->user());

        return back()->with('status', 'Tutorial desactivado correctamente.');
    }

    public function destroy(Request $request, ManualArticle $article, ManualAdministrationService $service): RedirectResponse
    {
        $this->authorizeAccess();
        $request->validate(['confirm' => ['accepted']]);
        $service->deleteArticle($article, $request->user());

        return redirect()->route('admin.manual.index')
            ->with('status', 'Tutorial eliminado correctamente.');
    }

    public function preview(ManualArticle $article): View
    {
        $this->authorizeAccess();
        $article->load(['category', 'updatedBy']);

        return view('admin.manual.preview', compact('article'));
    }

    public function categoryCreate(): View
    {
        $this->authorizeAccess();

        return view('admin.manual.categories.create');
    }

    public function categoryStore(StoreManualCategoryRequest $request, ManualAdministrationService $service): RedirectResponse
    {
        $this->authorizeAccess();
        $service->createCategory($request->validated(), $request->user());

        return redirect()->route('admin.manual.index')
            ->with('status', 'Categoria creada correctamente.');
    }

    public function categoryEdit(ManualCategory $category): View
    {
        $this->authorizeAccess();

        return view('admin.manual.categories.edit', compact('category'));
    }

    public function categoryUpdate(UpdateManualCategoryRequest $request, ManualCategory $category, ManualAdministrationService $service): RedirectResponse
    {
        $this->authorizeAccess();
        $service->updateCategory($category, $request->validated(), $request->user());

        return redirect()->route('admin.manual.index')
            ->with('status', 'Categoria actualizada correctamente.');
    }

    public function categoryDestroy(Request $request, ManualCategory $category, ManualAdministrationService $service): RedirectResponse
    {
        $this->authorizeAccess();
        $request->validate(['confirm' => ['accepted']]);

        try {
            $service->deleteCategory($category, $request->user());
        } catch (DomainException $exception) {
            return back()->withErrors(['category' => $exception->getMessage()]);
        }

        return redirect()->route('admin.manual.index')
            ->with('status', 'Categoria eliminada correctamente.');
    }

    /** @return array<string, mixed> */
    private function formOptions(?ManualArticle $article = null): array
    {
        $selectedCategoryId = $article?->category_id;
        $categories = ManualCategory::query()
            ->where(function ($query) use ($selectedCategoryId): void {
                $query->where('active', true);

                if ($selectedCategoryId !== null) {
                    $query->orWhere('id', $selectedCategoryId);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return [
            'article' => $article,
            'categories' => $categories,
            'roles' => Role::query()->orderBy('name')->pluck('name'),
            'levels' => $this->levels(),
            'statuses' => ['published' => 'Publicado', 'disabled' => 'Desactivado'],
        ];
    }

    /** @return array<string, string> */
    private function levels(): array
    {
        return [
            'basico' => 'Basico',
            'operativo' => 'Operativo',
            'supervision' => 'Supervision',
            'administracion' => 'Administracion',
        ];
    }

    private function authorizeAccess(): void
    {
        $user = auth()->user();

        abort_unless($user instanceof User && $user->can('manual.manage') && $user->hasAnyRole(['Administrador', 'Direccion Tecnica']), 403);
    }
}
