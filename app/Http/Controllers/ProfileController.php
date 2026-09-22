<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user()->load('institution');
        $effectivePermissions = $user->getAllPermissions()->pluck('name')->all();
        $permissionLabels = [
            'dashboard.view' => 'Ver dashboard',
            'cases.view' => 'Ver solicitudes',
            'cases.create' => 'Crear solicitudes',
            'cases.update' => 'Editar solicitudes',
            'cases.close' => 'Cerrar cirugias',
            'reservations.create' => 'Crear reservas',
            'schedule.view' => 'Ver agenda quirurgica',
            'schedule.manage' => 'Gestionar agenda y recursos',
            'trace.view' => 'Ver trazabilidad',
            'trace.scan' => 'Escanear codigos',
            'trace.print' => 'Imprimir etiquetas',
            'inventory.view' => 'Ver inventario',
            'inventory.update' => 'Editar inventario',
            'inventory.adjust' => 'Ajustar inventario',
            'inventory.release' => 'Liberar inventario',
            'inventory.audit' => 'Auditar inventario',
            'catalog.import' => 'Importar catalogo',
            'catalog.commit' => 'Confirmar catalogo',
            'failures.view' => 'Ver fallas',
            'failures.create' => 'Reportar fallas',
            'failures.update' => 'Actualizar fallas',
            'failures.release' => 'Liberar fallas',
            'failures.close' => 'Cerrar fallas',
            'returns.view' => 'Ver devoluciones',
            'returns.inspect' => 'Inspeccionar devoluciones',
            'returns.release' => 'Liberar devoluciones',
            'returns.audit' => 'Auditar devoluciones',
            'billing.view' => 'Ver facturacion',
            'billing.update' => 'Actualizar cobranza',
            'commercial.view' => 'Ver informacion comercial',
            'commercial.update' => 'Actualizar informacion comercial',
            'approvals.request' => 'Solicitar aprobaciones',
            'approvals.approve' => 'Aprobar costo cero',
            'reports.view' => 'Ver reportes',
            'reports.export' => 'Exportar reportes',
            'reports.operations' => 'Ver reporte operativo',
            'reports.commercial' => 'Ver reporte comercial',
            'reports.billing' => 'Ver reporte de cobranza',
            'reports.inventory' => 'Ver reporte de inventario',
            'documents.view' => 'Ver documentos',
            'documents.upload' => 'Subir documentos',
            'documents.validate' => 'Validar documentos',
            'documents.delete' => 'Eliminar documentos',
            'masters.view' => 'Ver maestros',
            'masters.manage' => 'Gestionar maestros',
            'prices.view' => 'Ver precios',
            'prices.manage' => 'Gestionar precios',
            'users.manage' => 'Gestionar usuarios',
            'audit.view' => 'Ver auditoria',
            'compliance.view' => 'Ver politica de uso de IA',
            'compliance.manage' => 'Gestionar cumplimiento de IA',
        ];
        $permissionAreas = [
            'Operacion quirurgica' => [
                'dashboard.view',
                'cases.view',
                'cases.create',
                'cases.update',
                'cases.close',
                'reservations.create',
                'schedule.view',
                'schedule.manage',
                'trace.view',
                'trace.scan',
                'trace.print',
                'failures.view',
                'failures.create',
                'failures.update',
                'failures.release',
                'failures.close',
                'returns.view',
                'returns.inspect',
                'returns.release',
                'returns.audit',
                'documents.view',
                'documents.upload',
                'documents.validate',
                'documents.delete',
            ],
            'Inventario' => [
                'inventory.view',
                'inventory.update',
                'inventory.adjust',
                'inventory.release',
                'inventory.audit',
                'catalog.import',
                'catalog.commit',
            ],
            'Facturacion' => [
                'billing.view',
                'billing.update',
                'commercial.view',
                'commercial.update',
                'approvals.request',
                'approvals.approve',
            ],
            'Reportes' => [
                'reports.view',
                'reports.export',
                'reports.operations',
                'reports.commercial',
                'reports.billing',
                'reports.inventory',
            ],
            'Cumplimiento' => [
                'compliance.view',
                'compliance.manage',
            ],
            'Administracion' => [
                'masters.view',
                'masters.manage',
                'prices.view',
                'prices.manage',
                'users.manage',
                'audit.view',
            ],
        ];
        $permissionGroups = [];

        foreach ($permissionAreas as $area => $areaPermissions) {
            $grantedPermissions = [];

            foreach ($areaPermissions as $permission) {
                if (in_array($permission, $effectivePermissions, true)) {
                    $grantedPermissions[] = $permissionLabels[$permission] ?? $permission;
                }
            }

            if ($grantedPermissions !== []) {
                $permissionGroups[$area] = $grantedPermissions;
            }
        }

        return view('profile.edit', [
            'user' => $user,
            'role' => $user->getRoleNames()->first(),
            'permissionGroups' => $permissionGroups,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        abort(403, 'La eliminacion de cuentas solo puede realizarla un Administrador desde Administracion de usuarios.');
    }
}
