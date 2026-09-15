<?php

namespace App\Http\Controllers;

use App\Enums\CaseStatus;
use App\Http\Requests\StoreInstitutionRequest;
use App\Http\Requests\UpdateInstitutionRequest;
use App\Models\Institution;
use App\Services\Audit\AuditLogger;
use App\Support\MasterOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InstitutionMasterController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeView();

        $institutions = Institution::query()
            ->withCount('doctors')
            ->when($request->filled('active'), function (Builder $query) use ($request): void {
                $query->where('active', $request->string('active')->value() === 'active');
            })
            ->when($request->filled('debt_status'), fn (Builder $query): Builder => $query->where('debt_status', $request->string('debt_status')->value()))
            ->when($request->filled('billing_policy'), fn (Builder $query): Builder => $query->where('billing_policy', $request->string('billing_policy')->value()))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('masters.institutions.index', [
            'institutions' => $institutions,
            'commercialConditions' => MasterOptions::COMMERCIAL_CONDITIONS,
            'debtStatuses' => MasterOptions::DEBT_STATUSES,
        ]);
    }

    public function create(): View
    {
        $this->authorizeManage();

        return view('masters.institutions.create', $this->formOptions());
    }

    public function store(StoreInstitutionRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $institution = DB::transaction(fn (): Institution => Institution::create($request->validated()));
        $auditLogger->record('institution.created', $institution, [], $this->snapshot($institution));

        return redirect()->route('masters.institutions.show', $institution)
            ->with('status', 'Institucion creada correctamente.');
    }

    public function show(Institution $institution): View
    {
        $this->authorizeView();

        $institution->load('doctors');
        $recentCases = $institution->surgeryCases()
            ->with(['doctor', 'surgeryType'])
            ->latest('scheduled_at')
            ->limit(20)
            ->get();

        return view('masters.institutions.show', compact('institution', 'recentCases'));
    }

    public function edit(Institution $institution): View
    {
        $this->authorizeManage();

        return view('masters.institutions.edit', $this->formOptions($institution));
    }

    public function update(UpdateInstitutionRequest $request, Institution $institution, AuditLogger $auditLogger): RedirectResponse
    {
        $before = $this->snapshot($institution);
        $data = $request->validated();

        if ($institution->active && ! $data['active'] && $this->hasFutureActiveCases($institution)) {
            return back()->withInput()->withErrors([
                'institution' => 'No puedes desactivar una institucion con cirugias futuras activas.',
            ]);
        }

        $institution->update($data);
        $institution->refresh();
        $after = $this->snapshot($institution);
        $auditLogger->record('institution.updated', $institution, $before, $after);

        if ($before['active'] !== $after['active']) {
            $auditLogger->record($after['active'] ? 'institution.enabled' : 'institution.disabled', $institution, $before, $after);
        }

        return redirect()->route('masters.institutions.show', $institution)
            ->with('status', 'Institucion actualizada correctamente.');
    }

    /** @return array<string, mixed> */
    private function formOptions(?Institution $institution = null): array
    {
        return [
            'institution' => $institution,
            'institutionTypes' => MasterOptions::INSTITUTION_TYPES,
            'commercialConditions' => MasterOptions::COMMERCIAL_CONDITIONS,
            'debtStatuses' => MasterOptions::DEBT_STATUSES,
        ];
    }

    private function authorizeView(): void
    {
        abort_unless(auth()->user()?->can('masters.view') || auth()->user()?->can('masters.manage'), 403);
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()?->can('masters.manage'), 403);
    }

    private function hasFutureActiveCases(Institution $institution): bool
    {
        return $institution->surgeryCases()
            ->where('scheduled_at', '>', now())
            ->whereNotIn('status', [
                CaseStatus::Cerrado->value,
                CaseStatus::Cerrada->value,
                CaseStatus::Facturada->value,
                CaseStatus::Cancelado->value,
            ])
            ->exists();
    }

    /** @return array<string, mixed> */
    private function snapshot(Institution $institution): array
    {
        return [
            'name' => $institution->name,
            'ruc' => $institution->ruc,
            'institution_type' => $institution->institution_type,
            'billing_policy' => $institution->billing_policy,
            'debt_status' => $institution->debt_status,
            'active' => (bool) $institution->active,
        ];
    }
}
