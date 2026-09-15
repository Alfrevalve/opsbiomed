<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDoctorRequest;
use App\Http\Requests\UpdateDoctorRequest;
use App\Models\CaseValuation;
use App\Models\Doctor;
use App\Models\Institution;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\MasterOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DoctorMasterController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeView();

        $doctors = Doctor::query()
            ->with(['institution', 'commercialOwner'])
            ->when($request->filled('active'), function (Builder $query) use ($request): void {
                $query->where('active', $request->string('active')->value() === 'active');
            })
            ->when($request->filled('specialty'), fn (Builder $query): Builder => $query->where('specialty', $request->string('specialty')->value()))
            ->when($request->filled('commercial_profile'), fn (Builder $query): Builder => $query->where('commercial_profile', $request->string('commercial_profile')->value()))
            ->when($request->filled('potential'), fn (Builder $query): Builder => $query->where('potential', $request->string('potential')->value()))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('masters.doctors.index', [
            'doctors' => $doctors,
            'specialties' => MasterOptions::SPECIALTIES,
            'commercialProfiles' => MasterOptions::COMMERCIAL_PROFILES,
            'potentials' => MasterOptions::POTENTIALS,
        ]);
    }

    public function create(): View
    {
        $this->authorizeManage();

        return view('masters.doctors.create', $this->formOptions());
    }

    public function store(StoreDoctorRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $doctor = DB::transaction(fn (): Doctor => Doctor::create($request->validated()));
        $auditLogger->record('doctor.created', $doctor, [], $this->snapshot($doctor));

        return redirect()->route('masters.doctors.show', $doctor)
            ->with('status', 'Medico creado correctamente.');
    }

    public function show(Doctor $doctor): View
    {
        $this->authorizeView();

        $doctor->load(['institution', 'commercialOwner']);
        $recentCases = $doctor->surgeryCases()
            ->with(['institution', 'surgeryType'])
            ->latest('scheduled_at')
            ->limit(20)
            ->get();
        $valuedConsumption = (float) CaseValuation::query()
            ->whereHas('case', fn (Builder $query): Builder => $query->where('doctor_id', $doctor->id))
            ->sum('total');
        $commercialOpportunities = DB::table('commercial_followups')
            ->where('doctor_id', $doctor->id)
            ->latest('created_at')
            ->limit(10)
            ->get();

        return view('masters.doctors.show', compact('doctor', 'recentCases', 'valuedConsumption', 'commercialOpportunities'));
    }

    public function edit(Doctor $doctor): View
    {
        $this->authorizeManage();

        return view('masters.doctors.edit', $this->formOptions($doctor));
    }

    public function update(UpdateDoctorRequest $request, Doctor $doctor, AuditLogger $auditLogger): RedirectResponse
    {
        $before = $this->snapshot($doctor);
        $doctor->update($request->validated());
        $doctor->refresh();
        $after = $this->snapshot($doctor);
        $auditLogger->record('doctor.updated', $doctor, $before, $after);

        if ($before['active'] !== $after['active']) {
            $auditLogger->record($after['active'] ? 'doctor.enabled' : 'doctor.disabled', $doctor, $before, $after);
        }

        return redirect()->route('masters.doctors.show', $doctor)
            ->with('status', 'Medico actualizado correctamente.');
    }

    /** @return array<string, mixed> */
    private function formOptions(?Doctor $doctor = null): array
    {
        return [
            'doctor' => $doctor,
            'institutions' => Institution::query()->orderBy('name')->get(),
            'commercialOwners' => User::query()->where('active', true)->orderBy('name')->get(),
            'specialties' => MasterOptions::SPECIALTIES,
            'commercialProfiles' => MasterOptions::COMMERCIAL_PROFILES,
            'potentials' => MasterOptions::POTENTIALS,
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

    /** @return array<string, mixed> */
    private function snapshot(Doctor $doctor): array
    {
        return [
            'name' => $doctor->name,
            'cmp' => $doctor->cmp,
            'specialty' => $doctor->specialty,
            'institution_id' => $doctor->institution_id,
            'commercial_owner_id' => $doctor->commercial_owner_id,
            'commercial_profile' => $doctor->commercial_profile,
            'potential' => $doctor->potential,
            'active' => (bool) $doctor->active,
        ];
    }
}
