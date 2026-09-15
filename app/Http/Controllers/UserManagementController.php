<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetManagedUserPasswordRequest;
use App\Http\Requests\StoreManagedUserRequest;
use App\Http\Requests\UpdateManagedUserRequest;
use App\Models\AuditLog;
use App\Models\Institution;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    /** @var array<string, string> */
    private const JOB_TITLES = [
        'Jefe de Linea' => 'Jefe de Linea',
        'Direccion Tecnica' => 'Direccion Tecnica',
        'Almacen' => 'Almacen',
        'Instrumentista' => 'Instrumentista',
        'Comercial' => 'Comercial',
        'Cobranza' => 'Cobranza',
        'Gerencia' => 'Gerencia',
        'Administrador' => 'Administrador',
    ];

    public function index(Request $request): View
    {
        $this->authorizeManage();

        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();

        return view('admin.users.index', [
            'users' => User::query()
                ->with(['roles', 'institution'])
                ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                }))
                ->when(in_array($status, ['active', 'inactive'], true), fn (Builder $query): Builder => $query->where('active', $status === 'active'))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'filters' => ['search' => $search, 'status' => $status],
        ]);
    }

    public function create(): View
    {
        $this->authorizeManage();

        return view('admin.users.create', $this->formOptions());
    }

    public function store(StoreManagedUserRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorizeManage();
        $data = $request->validated();

        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'active' => (bool) ($data['active'] ?? true),
                'job_title' => $data['job_title'] ?? null,
                'area' => $data['area'] ?? null,
                'phone' => $data['phone'] ?? null,
                'institution_id' => $data['institution_id'] ?? null,
            ]);
            $user->assignRole($data['role']);

            return $user;
        });

        $auditLogger->record('user.created', $user, [], $this->snapshot($user));

        return redirect()->route('admin.users.show', $user)->with('status', 'Usuario creado y rol asignado correctamente.');
    }

    public function show(User $user): View
    {
        $this->authorizeManage();
        $user->load(['roles.permissions', 'institution']);

        $permissions = $user->roles
            ->flatMap(fn (Role $role): Collection => $role->permissions->pluck('name'))
            ->unique()
            ->sort()
            ->values();
        $auditLogs = AuditLog::query()
            ->with('user')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->latest('created_at')
            ->get();

        return view('admin.users.show', [
            'user' => $user,
            'permissions' => $permissions,
            'auditLogs' => $auditLogs,
        ]);
    }

    public function edit(User $user): View
    {
        $this->authorizeManage();
        $user->load(['roles', 'institution']);

        return view('admin.users.edit', $this->formOptions($user));
    }

    public function update(UpdateManagedUserRequest $request, User $user, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorizeManage();
        $data = $request->validated();

        try {
            $result = DB::transaction(function () use ($data, $user): array {
                $managedUser = User::query()->lockForUpdate()->findOrFail($user->id);
                $before = $this->snapshot($managedUser);
                $beforeRoles = $managedUser->getRoleNames()->values()->all();
                $newRole = $data['role'];
                $newActive = (bool) $data['active'];

                $this->ensureAccessChangeIsAllowed($managedUser, $newActive, $newRole);

                $managedUser->fill([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'active' => $newActive,
                    'job_title' => $data['job_title'] ?? null,
                    'area' => $data['area'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'institution_id' => $data['institution_id'] ?? null,
                ]);
                $managedUser->save();
                $managedUser->syncRoles([$newRole]);
                $managedUser->refresh();

                return [
                    'user' => $managedUser,
                    'before' => $before,
                    'after' => $this->snapshot($managedUser),
                    'before_roles' => $beforeRoles,
                    'after_roles' => $managedUser->getRoleNames()->values()->all(),
                ];
            });
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['user' => $exception->getMessage()]);
        }

        $managedUser = $result['user'];
        $auditLogger->record('user.updated', $managedUser, $result['before'], $result['after']);

        if ($result['before_roles'] !== $result['after_roles']) {
            $auditLogger->record('user.role_changed', $managedUser, ['roles' => $result['before_roles']], ['roles' => $result['after_roles']]);
        }

        if ($result['before']['active'] !== $result['after']['active']) {
            $auditLogger->record($result['after']['active'] ? 'user.enabled' : 'user.disabled', $managedUser, $result['before'], $result['after']);
        }

        return redirect()->route('admin.users.show', $managedUser)->with('status', 'Usuario actualizado correctamente.');
    }

    public function disable(User $user, AuditLogger $auditLogger): RedirectResponse
    {
        return $this->changeStatus($user, false, 'user.disabled', $auditLogger);
    }

    public function enable(User $user, AuditLogger $auditLogger): RedirectResponse
    {
        return $this->changeStatus($user, true, 'user.enabled', $auditLogger);
    }

    public function resetPassword(ResetManagedUserPasswordRequest $request, User $user, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorizeManage();
        $user->forceFill(['password' => $request->validated('password')])->save();
        $auditLogger->record('user.password_reset', $user, [], ['user_id' => $user->id]);

        return redirect()->route('admin.users.show', $user)->with('status', 'Password temporal actualizada correctamente.');
    }

    /** @return array<string, mixed> */
    private function formOptions(?User $user = null): array
    {
        return [
            'user' => $user,
            'roles' => Role::query()->orderBy('name')->get(),
            'institutions' => Institution::query()->where('active', true)->orderBy('name')->get(),
            'jobTitles' => self::JOB_TITLES,
        ];
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()?->can('users.manage'), 403);
    }

    private function activeAdministratorCount(): int
    {
        return User::query()
            ->where('active', true)
            ->whereHas('roles', fn (Builder $query): Builder => $query->where('name', 'Administrador'))
            ->count();
    }

    private function ensureAccessChangeIsAllowed(User $user, bool $willBeActive, string $willHaveRole): void
    {
        if (! $willBeActive && $user->id === auth()->id()) {
            throw new DomainException('No puedes desactivar tu propio usuario.');
        }

        if ($user->active && $user->hasRole('Administrador') && (! $willBeActive || $willHaveRole !== 'Administrador') && $this->activeAdministratorCount() <= 1) {
            throw new DomainException('No puedes quitar o desactivar al ultimo Administrador activo.');
        }
    }

    private function changeStatus(User $user, bool $active, string $action, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorizeManage();

        try {
            $result = DB::transaction(function () use ($user, $active): ?array {
                $managedUser = User::query()->lockForUpdate()->findOrFail($user->id);

                if ((bool) $managedUser->active === $active) {
                    return null;
                }

                $role = $managedUser->getRoleNames()->first() ?: '';
                $this->ensureAccessChangeIsAllowed($managedUser, $active, $role);
                $before = $this->snapshot($managedUser);
                $managedUser->forceFill(['active' => $active])->save();

                return [
                    'user' => $managedUser,
                    'before' => $before,
                    'after' => $this->snapshot($managedUser),
                ];
            });
        } catch (DomainException $exception) {
            return back()->withErrors(['user' => $exception->getMessage()]);
        }

        if ($result !== null) {
            $auditLogger->record($action, $result['user'], $result['before'], $result['after']);
        }

        $message = $active ? 'El usuario ya esta activo.' : 'El usuario ya estaba inactivo.';

        return redirect()->route('admin.users.show', $user)->with('status', $result === null ? $message : ($active ? 'Usuario activado correctamente.' : 'Usuario inactivado correctamente.'));
    }

    /** @return array<string, mixed> */
    private function snapshot(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'active' => (bool) $user->active,
            'job_title' => $user->job_title,
            'area' => $user->area,
            'phone' => $user->phone,
            'institution_id' => $user->institution_id,
            'role' => $user->getRoleNames()->first(),
        ];
    }
}
