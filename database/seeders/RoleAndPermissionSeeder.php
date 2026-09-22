<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'dashboard.view',
            'cases.view',
            'cases.create',
            'cases.update',
            'cases.close',
            'reservations.create',
            'reservations.release',
            'schedule.view',
            'schedule.manage',
            'trace.view',
            'trace.scan',
            'trace.print',
            'inventory.view',
            'inventory.update',
            'inventory.adjust',
            'inventory.release',
            'inventory.audit',
            'catalog.import',
            'catalog.commit',
            'failures.view',
            'failures.create',
            'failures.update',
            'failures.release',
            'failures.close',
            'returns.view',
            'returns.inspect',
            'returns.release',
            'returns.audit',
            'approvals.request',
            'approvals.approve',
            'billing.view',
            'billing.update',
            'commercial.view',
            'commercial.update',
            'audit.view',
            'compliance.view',
            'compliance.manage',
            'alerts.view',
            'alerts.manage',
            'alerts.resolve',
            'documents.view',
            'documents.upload',
            'documents.validate',
            'documents.delete',
            'reports.view',
            'reports.operations',
            'reports.commercial',
            'reports.billing',
            'reports.inventory',
            'reports.export',
            'users.manage',
            'masters.view',
            'masters.manage',
            'prices.view',
            'prices.manage',
            'manual.view',
            'manual.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $roles = [
            'Administrador' => $permissions,
            'Gerencia' => ['dashboard.view', 'cases.view', 'schedule.view', 'trace.view', 'inventory.view', 'inventory.audit', 'returns.view', 'returns.audit', 'approvals.approve', 'billing.view', 'commercial.view', 'audit.view', 'compliance.view', 'compliance.manage', 'alerts.view', 'alerts.resolve', 'documents.view', 'documents.validate', 'failures.view', 'reports.view', 'reports.operations', 'reports.commercial', 'reports.billing', 'reports.inventory', 'reports.export', 'masters.view', 'prices.view', 'prices.manage'],
            'Jefe de Linea' => ['dashboard.view', 'cases.view', 'cases.update', 'cases.close', 'schedule.view', 'schedule.manage', 'trace.view', 'trace.scan', 'trace.print', 'inventory.view', 'inventory.audit', 'returns.view', 'returns.audit', 'reservations.create', 'reservations.release', 'approvals.request', 'approvals.approve', 'failures.view', 'failures.create', 'failures.update', 'billing.view', 'commercial.view', 'commercial.update', 'compliance.view', 'alerts.view', 'alerts.manage', 'alerts.resolve', 'documents.view', 'documents.upload', 'documents.validate', 'reports.view', 'reports.operations', 'reports.commercial', 'reports.billing', 'reports.inventory', 'reports.export', 'masters.view', 'masters.manage', 'prices.view', 'prices.manage'],
            'Direccion Tecnica' => ['dashboard.view', 'cases.view', 'cases.close', 'schedule.view', 'trace.view', 'trace.scan', 'inventory.view', 'inventory.update', 'inventory.release', 'inventory.audit', 'returns.view', 'returns.inspect', 'returns.release', 'returns.audit', 'failures.view', 'failures.create', 'failures.update', 'failures.release', 'failures.close', 'approvals.approve', 'audit.view', 'compliance.view', 'alerts.view', 'alerts.manage', 'alerts.resolve', 'documents.view', 'documents.upload', 'documents.validate', 'reports.view', 'reports.inventory', 'reports.export'],
            'Almacen' => ['dashboard.view', 'cases.view', 'cases.close', 'schedule.view', 'trace.view', 'trace.scan', 'trace.print', 'inventory.view', 'inventory.update', 'inventory.adjust', 'catalog.import', 'reservations.create', 'reservations.release', 'returns.view', 'returns.inspect', 'failures.view', 'failures.create', 'compliance.view', 'alerts.view', 'alerts.manage', 'documents.view', 'documents.upload', 'reports.view', 'reports.inventory', 'reports.export'],
            'Programador Quirurgico' => ['dashboard.view', 'cases.view', 'cases.create', 'cases.update', 'reservations.create', 'compliance.view', 'alerts.view', 'alerts.manage', 'masters.view', 'masters.manage'],
            'Instrumentista' => ['dashboard.view', 'cases.view', 'cases.update', 'cases.close', 'schedule.view', 'trace.view', 'trace.scan', 'inventory.view', 'returns.view', 'failures.view', 'failures.create', 'compliance.view', 'alerts.view', 'documents.view', 'documents.upload', 'reports.view', 'reports.operations', 'reports.export'],
            'Comercial' => ['dashboard.view', 'cases.view', 'schedule.view', 'trace.view', 'inventory.view', 'commercial.view', 'commercial.update', 'approvals.request', 'failures.view', 'compliance.view', 'alerts.view', 'documents.view', 'documents.upload', 'reports.view', 'reports.operations', 'reports.commercial', 'reports.export', 'masters.view', 'masters.manage', 'prices.view'],
            'Cobranza' => ['dashboard.view', 'cases.view', 'trace.view', 'billing.view', 'billing.update', 'compliance.view', 'alerts.view', 'alerts.manage', 'alerts.resolve', 'documents.view', 'documents.upload', 'reports.view', 'reports.billing', 'reports.export', 'prices.view'],
            'Consulta Auditoria' => ['dashboard.view', 'cases.view', 'inventory.view', 'billing.view', 'audit.view', 'compliance.view', 'alerts.view'],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName);
            $role->syncPermissions($rolePermissions);
            $role->givePermissionTo('manual.view');

            if (in_array($roleName, ['Administrador', 'Direccion Tecnica'], true)) {
                $role->givePermissionTo('manual.manage');
            }
        }
    }
}
