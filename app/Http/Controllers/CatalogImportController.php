<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommitCatalogImportRequest;
use App\Http\Requests\StoreCatalogImportRequest;
use App\Models\CatalogImport;
use App\Services\Catalog\CatalogImportService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

class CatalogImportController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('catalog.import'), 403);

        return view('catalog.imports.index', [
            'imports' => CatalogImport::query()
                ->with('uploadedBy')
                ->latest()
                ->paginate(15),
        ]);
    }

    public function store(StoreCatalogImportRequest $request, CatalogImportService $catalogImportService): RedirectResponse
    {
        try {
            $import = $catalogImportService->stage($request->file('catalog'), $request->user()->id);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return redirect()->route('catalog.imports.show', $import)
            ->with('status', 'Archivo cargado a staging y validado.');
    }

    public function show(CatalogImport $import, CatalogImportService $catalogImportService): View
    {
        abort_unless(auth()->user()?->can('catalog.import'), 403);

        $import->load([
            'uploadedBy',
            'rows' => fn ($query) => $query
                ->orderByRaw("CASE status WHEN 'error' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END")
                ->orderBy('row_number'),
        ]);

        return view('catalog.imports.show', [
            'import' => $import,
            'diagnostics' => $catalogImportService->diagnostics($import),
        ]);
    }

    public function commit(
        CommitCatalogImportRequest $request,
        CatalogImport $import,
        CatalogImportService $catalogImportService,
    ): RedirectResponse {
        try {
            $catalogImportService->commit(
                $import,
                $request->boolean('confirm_negative_inconsistencies'),
            );
        } catch (DomainException $exception) {
            return redirect()->route('catalog.imports.show', $import)
                ->with('error', $exception->getMessage());
        }

        return redirect()->route('catalog.imports.show', $import)
            ->with('status', 'Importacion confirmada. El inventario y el dashboard fueron actualizados.');
    }
}
