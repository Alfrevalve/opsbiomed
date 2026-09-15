<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentEvidenceRequest;
use App\Http\Requests\ValidateDocumentEvidenceRequest;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\BillingRecord;
use App\Models\CaseReturn;
use App\Models\DocumentEvidence;
use App\Models\Failure;
use App\Models\InventoryLot;
use App\Models\OperationalAlert;
use App\Models\SurgeryCase;
use App\Services\Documents\DocumentEvidenceService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentEvidenceController extends Controller
{
    /** @var array<string, string> */
    private const DOCUMENT_TYPES = [
        'solicitud' => 'Solicitud',
        'guia_internamiento' => 'Guia de internamiento',
        'cargo_recepcion' => 'Cargo de recepcion',
        'evidencia_consumo' => 'Evidencia de consumo',
        'hoja_consumo' => 'Hoja de consumo',
        'reporte_falla' => 'Reporte de falla',
        'evidencia_falla' => 'Evidencia de falla',
        'evidencia_devolucion' => 'Evidencia de devolucion',
        'inspeccion' => 'Inspeccion',
        'orden_compra' => 'Orden de compra',
        'factura' => 'Factura',
        'boleta' => 'Boleta',
        'aprobacion_costo_cero' => 'Aprobacion de costo cero',
        'solicitud_pago' => 'Solicitud de pago',
        'comprobante_pago' => 'Comprobante de pago',
        'otro' => 'Otro',
    ];

    /** @var array<string, class-string<Model>> */
    private const DOCUMENTABLE_TYPES = [
        'case' => SurgeryCase::class,
        'inventory_lot' => InventoryLot::class,
        'failure' => Failure::class,
        'return' => CaseReturn::class,
        'billing' => BillingRecord::class,
        'approval' => Approval::class,
    ];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', DocumentEvidence::class);

        $status = $request->string('status')->toString();
        $documentType = $request->string('document_type')->toString();
        $targetType = $request->string('documentable_type')->toString();
        $targetClass = self::DOCUMENTABLE_TYPES[$targetType] ?? null;

        $documents = DocumentEvidence::query()
            ->with(['uploadedBy', 'validatedBy', 'documentable'])
            ->when(in_array($status, ['pendiente', 'cargado', 'validado', 'observado', 'rechazado'], true), fn (Builder $query) => $query->where('status', $status))
            ->when(array_key_exists($documentType, self::DOCUMENT_TYPES), fn (Builder $query) => $query->where('document_type', $documentType))
            ->when($targetClass !== null, fn (Builder $query) => $query->where('documentable_type', $targetClass))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('documents.index', [
            'documents' => $documents,
            'documentTypes' => self::DOCUMENT_TYPES,
            'targetTypes' => self::DOCUMENTABLE_TYPES,
            'targetOptions' => $this->targetOptions(),
            'filters' => $request->only(['status', 'document_type', 'documentable_type']),
        ]);
    }

    public function show(DocumentEvidence $document): View
    {
        $this->authorize('view', $document);
        $document->load(['uploadedBy', 'validatedBy', 'documentable']);
        $canAudit = auth()->user()?->can('audit.view') ?? false;
        $auditLogs = $canAudit
            ? AuditLog::query()
                ->with('user')
                ->where('auditable_type', DocumentEvidence::class)
                ->where('auditable_id', $document->id)
                ->latest('created_at')
                ->get()
            : collect();

        $slaAlerts = OperationalAlert::query()
            ->where('alertable_type', DocumentEvidence::class)
            ->where('alertable_id', $document->id)
            ->whereIn('status', array_merge(OperationalAlert::OPEN_STATUSES, ['expired']))
            ->latest('detected_at')
            ->get();

        return view('documents.show', [
            'document' => $document,
            'documentTypes' => self::DOCUMENT_TYPES,
            'canAudit' => $canAudit,
            'auditLogs' => $auditLogs,
            'slaAlerts' => $slaAlerts,
        ]);
    }

    public function download(DocumentEvidence $document): BinaryFileResponse
    {
        $this->authorize('view', $document);

        abort_unless($document->file_path !== null && Storage::disk('private')->exists($document->file_path), 404);

        return response()->download(
            Storage::disk('private')->path($document->file_path),
            basename($document->file_path),
            ['Content-Type' => $document->mime_type ?: 'application/octet-stream'],
        );
    }

    public function caseIndex(SurgeryCase $case): View
    {
        $this->authorize('view', $case);
        $this->authorize('viewAny', DocumentEvidence::class);
        $case->load(['institution', 'doctor', 'patient', 'surgeryType', 'documents.uploadedBy', 'documents.validatedBy']);

        return view('documents.case', [
            'case' => $case,
            'documents' => $case->documents,
            'documentTypes' => self::DOCUMENT_TYPES,
        ]);
    }

    public function store(StoreDocumentEvidenceRequest $request, DocumentEvidenceService $service): RedirectResponse
    {
        $this->authorize('create', DocumentEvidence::class);
        $data = $request->validated();

        try {
            $documentable = $this->resolveDocumentable($data);
            $document = $service->upload($data, $documentable, $request->user());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['document' => $exception->getMessage()]);
        }

        return redirect()->route('documents.show', $document)->with('status', 'Documento cargado y auditado.');
    }

    public function storeForCase(
        StoreDocumentEvidenceRequest $request,
        SurgeryCase $case,
        DocumentEvidenceService $service,
    ): RedirectResponse {
        $this->authorize('view', $case);
        $this->authorize('create', DocumentEvidence::class);

        try {
            $document = $service->upload($request->validated(), $case, $request->user());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['document' => $exception->getMessage()]);
        }

        return redirect()->route('cases.documents.index', $case)->with('status', 'Documento cargado y auditado.');
    }

    public function validateDocument(
        ValidateDocumentEvidenceRequest $request,
        DocumentEvidence $document,
        DocumentEvidenceService $service,
    ): RedirectResponse {
        $this->authorize('validate', $document);
        $service->validateDocument($document, $request->validated(), $request->user());

        return redirect()->route('documents.show', $document)->with('status', 'Estado documental actualizado y auditado.');
    }

    public function destroy(DocumentEvidence $document, DocumentEvidenceService $service): RedirectResponse
    {
        $this->authorize('delete', $document);
        $service->delete($document, request()->user());

        return redirect()->route('documents.index')->with('status', 'Documento enviado a papelera logica; el archivo privado fue conservado.');
    }

    /** @return array<string, Collection<int, Model>> */
    private function targetOptions(): array
    {
        return [
            'case' => SurgeryCase::query()->with(['institution', 'doctor'])->latest('id')->limit(100)->get(),
            'inventory_lot' => InventoryLot::query()->with(['product', 'warehouse'])->latest('id')->limit(100)->get(),
            'failure' => Failure::query()->with(['product', 'inventoryLot.product'])->latest('id')->limit(100)->get(),
            'return' => CaseReturn::query()->with(['case', 'inventoryLot.product'])->latest('id')->limit(100)->get(),
            'billing' => BillingRecord::query()->with('case')->latest('id')->limit(100)->get(),
            'approval' => Approval::query()->with('case')->latest('id')->limit(100)->get(),
        ];
    }

    /** @param array<string, mixed> $data */
    private function resolveDocumentable(array $data): Model
    {
        $type = (string) ($data['documentable_type'] ?? '');
        $modelClass = self::DOCUMENTABLE_TYPES[$type] ?? null;

        abort_unless($modelClass !== null, 422, 'Tipo de entidad documental no valido.');

        return $modelClass::query()->findOrFail((int) $data['documentable_id']);
    }
}
