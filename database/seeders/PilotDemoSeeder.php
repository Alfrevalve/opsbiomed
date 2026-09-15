<?php

namespace Database\Seeders;

use App\Enums\CaseStatus;
use App\Enums\InventoryStatus;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\BillingRecord;
use App\Models\CaseMaterialUsed;
use App\Models\CaseReconciliation;
use App\Models\CaseReturn;
use App\Models\CaseValuation;
use App\Models\CaseValuationLine;
use App\Models\Doctor;
use App\Models\DocumentEvidence;
use App\Models\Failure;
use App\Models\Institution;
use App\Models\InventoryLot;
use App\Models\Patient;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Reservation;
use App\Models\SurgeryCase;
use App\Models\SurgeryType;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PilotDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            WarehouseSeeder::class,
            SurgeryTypeSeeder::class,
            KitRuleSeeder::class,
        ]);

        DB::transaction(function (): void {
            $institution = $this->seedInstitution();
            $users = $this->seedUsers($institution);
            $doctor = $this->seedDoctor($institution, $users['comercial']);
            $patient = $this->seedPatient();
            $warehouses = $this->warehouses();
            $products = $this->seedProducts();
            $lots = $this->seedInventory($products, $warehouses);

            foreach ($products as $product) {
                $this->seedPrice($product, $users['admin']);
            }

            $cervical = SurgeryType::query()->where('code', 'cervical')->firstOrFail();
            $mainCase = $this->seedMainCase($institution, $doctor, $patient, $cervical, $users['admin']);
            $mainReservations = [
                $this->seedReservation($mainCase, $lots['green_9_3'], 1, $users['almacen']),
                $this->seedReservation($mainCase, $lots['yellow_9_3_d'], 1, $users['almacen']),
            ];
            $mainCase->update(['status' => CaseStatus::Reservado]);

            foreach ($mainReservations as $reservation) {
                $this->auditOnce('reservation.created', $reservation, $users['almacen']->id, [
                    'case_id' => $reservation->case_id,
                    'inventory_lot_id' => $reservation->inventory_lot_id,
                    'quantity' => $reservation->quantity,
                    'status' => $reservation->status,
                ]);
            }

            $this->seedMainDocument($mainCase, $users['instrumentista']);
            $this->seedMainBilling($mainCase, $users['cobranza']);
            $this->seedFailureScenario($lots['blocked_10_3_d'], $mainCase, $users['dt']);
            $this->seedReturnScenario($lots['quarantine_return'], $mainCase);

            $closureCase = $this->seedClosureCase($institution, $doctor, $patient, $cervical, $users['instrumentista']);
            $closureReservation = $this->seedReservation($closureCase, $lots['green_10_2_d'], 1, $users['almacen']);
            $closureReservation->update(['status' => 'consumed']);
            $this->seedCostZeroScenario($closureCase, $closureReservation, $lots['green_10_2_d'], $users['instrumentista']);
            $this->seedReconciliation($closureCase, $users['instrumentista']);

            $this->auditOnce('pilot.demo.seeded', $mainCase, $users['admin']->id, [
                'main_case_code' => $mainCase->case_code,
                'closure_case_code' => $closureCase->case_code,
                'products' => count($products),
                'pilot_inventory_lots' => count($lots),
            ]);
        });
    }

    private function seedInstitution(): Institution
    {
        $name = "Cl\u{00ed}nica Piloto OPS";
        $ruc = '20100000001';
        $institutionByName = Institution::query()->where('name', $name)->first();
        $institution = $institutionByName
            ?? Institution::query()->where('ruc', $ruc)->first()
            ?? new Institution;
        $attributes = [
            'name' => $name,
            'institution_type' => 'clinica_privada',
            'billing_policy' => 'regular',
            'debt_status' => 'al_dia',
            'agreement_type' => 'convenio',
            'address' => 'Av. Piloto 100, Lima',
            'sop_contact' => 'Coordinacion SOP Piloto',
            'pharmacy_contact' => 'Farmacia Piloto',
            'billing_contact' => 'Facturacion Piloto',
            'phone' => '01 555 0101',
            'email' => 'facturacion@clinica-piloto.ops.test',
            'active' => true,
            'observations' => 'Registro controlado para el piloto operativo MR8.',
        ];
        $otherInstitutionHasPilotRuc = Institution::query()
            ->where('ruc', $ruc)
            ->when($institution->exists, fn ($query) => $query->where('id', '!=', $institution->id))
            ->exists();
        if (! $otherInstitutionHasPilotRuc) {
            $attributes['ruc'] = $ruc;
        }
        $institution->fill($attributes);
        $institution->save();

        return $institution;
    }

    /** @return array<string, User> */
    private function seedUsers(Institution $institution): array
    {
        $password = (string) env('PILOT_DEMO_PASSWORD');

        if ($password === '') {
            throw new \RuntimeException('Define PILOT_DEMO_PASSWORD antes de ejecutar PilotDemoSeeder.');
        }

        $definitions = [
            'admin' => ['admin@ops.test', 'Administrador piloto', 'Administrador', 'Administracion', 'Administrador'],
            'jefe_linea' => ['jefe.linea@ops.test', 'Jefe de Linea piloto', 'Jefe de Linea', 'Operacion', 'Jefe de Linea'],
            'dt' => ['dt@ops.test', 'Direccion Tecnica piloto', 'Direccion Tecnica', 'Calidad tecnica', 'Director tecnico'],
            'almacen' => ['almacen@ops.test', 'Almacen piloto', 'Almacen', 'Inventario', 'Responsable de almacen'],
            'instrumentista' => ['instrumentista@ops.test', 'Instrumentista piloto', 'Instrumentista', 'Operacion', 'Instrumentista'],
            'comercial' => ['comercial@ops.test', 'Comercial piloto', 'Comercial', 'Comercial', 'Ejecutivo comercial'],
            'cobranza' => ['cobranza@ops.test', 'Cobranza piloto', 'Cobranza', 'Administracion', 'Analista de cobranza'],
            'gerencia' => ['gerencia@ops.test', 'Gerencia piloto', 'Gerencia', 'Direccion', 'Gerente'],
        ];

        $users = [];
        foreach ($definitions as $key => [$email, $name, $role, $area, $jobTitle]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => $password,
                    'email_verified_at' => now(),
                    'active' => true,
                    'area' => $area,
                    'job_title' => $jobTitle,
                    'phone' => '999 000 '.str_pad((string) (count($users) + 1), 3, '0', STR_PAD_LEFT),
                    'institution_id' => $institution->id,
                ],
            );
            $user->syncRoles($role);
            $users[$key] = $user;
            $this->auditOnce('user.created', $user, $user->id, [
                'email' => $user->email,
                'role' => $role,
                'active' => true,
            ]);
        }

        return $users;
    }

    private function seedDoctor(Institution $institution, User $commercialOwner): Doctor
    {
        $name = "Dr. Piloto Neurocirug\u{00ed}a";
        $doctor = Doctor::query()
            ->where('name', $name)
            ->where('institution_id', $institution->id)
            ->first()
            ?? Doctor::query()->where('cmp', 'CMP-PILOT-001')->first()
            ?? new Doctor;
        $doctor->fill([
            'cmp' => 'CMP-PILOT-001',
            'name' => $name,
            'institution_id' => $institution->id,
            'specialty' => 'neurocirugia',
            'commercial_owner_id' => $commercialOwner->id,
            'phone' => '999 100 001',
            'email' => 'dr.piloto@ops.test',
            'commercial_profile' => 'clave',
            'potential' => 'alto',
            'active' => true,
            'observations' => 'Medico principal del escenario demo MR8.',
        ]);
        $doctor->save();

        return $doctor;
    }

    private function seedPatient(): Patient
    {
        return Patient::updateOrCreate(
            ['code' => 'PAT-PILOT-MR8-001'],
            [
                'full_name' => 'Paciente Demo MR8',
                'document_number' => '00000001',
                'phone' => '999 200 001',
                'notes' => 'Paciente ficticio para demostracion. No usar en atencion real.',
            ],
        );
    }

    /** @return array<string, Warehouse> */
    private function warehouses(): array
    {
        return [
            'principal' => Warehouse::query()->where('name', 'ALMACEN PRINCIPAL')->firstOrFail(),
            'consignacion' => Warehouse::query()->where('name', 'ALMACEN CONSIGNACION')->firstOrFail(),
            'ysan' => Warehouse::query()->where('name', 'ALMACEN YSAN')->firstOrFail(),
            'desvalorizado' => Warehouse::query()->where('name', 'ALMACEN DESVALORIZADO')->firstOrFail(),
        ];
    }

    /** @return array<string, Product> */
    private function seedProducts(): array
    {
        $definitions = [
            '9_3' => ['MR8-9BA30', 'Fresa MR8 9 cm 3 mm cortante', 9, 3, 'cortante', 'fresa'],
            '9_3_d' => ['MR8-9BA30D', 'Fresa MR8 9 cm 3 mm diamantada', 9, 3, 'diamantada', 'fresa'],
            '10_2' => ['MR8-10BA20', 'Fresa MR8 10 cm 2 mm cortante', 10, 2, 'cortante', 'fresa'],
            '10_2_d' => ['MR8-10BA20D', 'Fresa MR8 10 cm 2 mm diamantada', 10, 2, 'diamantada', 'fresa'],
            '10_3' => ['MR8-10BA30', 'Fresa MR8 10 cm 3 mm cortante', 10, 3, 'cortante', 'fresa'],
            '10_3_d' => ['MR8-10BA30D', 'Fresa MR8 10 cm 3 mm diamantada', 10, 3, 'diamantada', 'fresa'],
            'f2' => ['MR8-F2/7TA23', 'Cuchilla MR8 F2 7TA23', null, null, 'F2', 'cuchilla'],
            'f3' => ['MR8-F3/9TA30', 'Cuchilla MR8 F3 9TA30', null, null, 'F3', 'cuchilla'],
            'ird300' => ['IRD300', 'Fresa convencional IRD300 14 cm 3 mm', 14, 3, 'cortante', 'fresa'],
        ];

        $products = [];
        foreach ($definitions as $key => [$code, $name, $length, $diameter, $cutType, $componentType]) {
            $products[$key] = Product::updateOrCreate(
                ['product_code' => $code],
                [
                    'product_line' => 'MR8',
                    'family' => $componentType === 'cuchilla' ? 'Cuchillas' : 'Fresas',
                    'subfamily' => 'Piloto MR8',
                    'normalized_code' => strtoupper(str_replace('/', '-', $code)),
                    'name' => $name,
                    'regulatory_record' => 'REG-PILOT-MR8',
                    'regulatory_expiry' => now()->addYears(2)->toDateString(),
                    'length_cm' => $length,
                    'diameter_mm' => $diameter,
                    'cut_type' => $cutType,
                    'component_type' => $componentType,
                    'classification' => 'consumible',
                    'expiry_required' => true,
                    'tracking_type' => 'lot',
                    'active' => true,
                ],
            );
        }

        return $products;
    }

    /** @param array<string, Product> $products
     * @param  array<string, Warehouse>  $warehouses
     * @return array<string, InventoryLot>
     */
    private function seedInventory(array $products, array $warehouses): array
    {
        $futureExpiry = now()->addYears(2)->toDateString();
        $expiringExpiry = now()->addDays(20)->toDateString();
        $expiredExpiry = now()->subDay()->toDateString();
        $definitions = [
            'green_9_3' => ['9_3', 'PILOT-VERDE-9-3', 'principal', 6, $futureExpiry, InventoryStatus::Apto, true, null],
            'ysan_9_3' => ['9_3', 'PILOT-YSAN-9-3', 'ysan', 8, $futureExpiry, InventoryStatus::Apto, true, 'Respaldo externo YSAN; lead time minimo 48 horas.'],
            'expiring_9_3' => ['9_3', 'PILOT-POR-VENCER-9-3', 'principal', 1, $expiringExpiry, InventoryStatus::Apto, true, 'Lote proximo a vencer para validacion del tablero.'],
            'yellow_9_3_d' => ['9_3_d', 'PILOT-AMARILLO-9-3-D', 'principal', 4, $futureExpiry, InventoryStatus::Apto, true, null],
            'quarantine_return' => ['9_3_d', 'PILOT-DEVOLUCION-CUARENTENA', 'principal', 1, $futureExpiry, InventoryStatus::Cuarentena, false, 'Devolucion pendiente de inspeccion.'],
            'red_10_2' => ['10_2', 'PILOT-ROJO-10-2', 'principal', 2, $futureExpiry, InventoryStatus::Apto, true, null],
            'expired_10_2' => ['10_2', 'PILOT-VENCIDO-10-2', 'principal', 4, $expiredExpiry, InventoryStatus::Vencido, false, 'Lote vencido para validacion del tablero.'],
            'green_10_2_d' => ['10_2_d', 'PILOT-VERDE-10-2-D', 'principal', 5, $futureExpiry, InventoryStatus::Apto, true, null],
            'yellow_10_3' => ['10_3', 'PILOT-AMARILLO-10-3', 'principal', 3, $futureExpiry, InventoryStatus::Apto, true, null],
            'devalued_10_3' => ['10_3', 'PILOT-DESVALORIZADO-10-3', 'desvalorizado', 10, $futureExpiry, InventoryStatus::Desvalorizado, false, 'Almacen desvalorizado; no elegible.'],
            'blocked_10_3_d' => ['10_3_d', 'PILOT-BLOQUEADO-10-3-D', 'principal', 2, $futureExpiry, InventoryStatus::Bloqueado, false, 'Bloqueo preventivo por falla tecnica.'],
            'quarantine_f3' => ['f3', 'PILOT-CUARENTENA-F3', 'principal', 1, $futureExpiry, InventoryStatus::Cuarentena, false, 'Lote en cuarentena para validacion del tablero.'],
            'f2' => ['f2', 'PILOT-F2-ACTIVO', 'principal', 1, $futureExpiry, InventoryStatus::Apto, true, null],
            'ird300' => ['ird300', 'PILOT-IRD300-ACTIVO', 'principal', 4, $futureExpiry, InventoryStatus::Apto, true, null],
        ];

        $lots = [];
        foreach ($definitions as $key => [$productKey, $lotCode, $warehouseKey, $quantity, $expiry, $status, $eligible, $observations]) {
            $lots[$key] = InventoryLot::updateOrCreate(
                ['product_id' => $products[$productKey]->id, 'lot' => $lotCode],
                [
                    'serial' => null,
                    'expiry' => $expiry,
                    'warehouse_id' => $warehouses[$warehouseKey]->id,
                    'location' => 'PILOT-'.$warehouseKey,
                    'quantity' => $quantity,
                    'status' => $status,
                    'block_reason' => $status === InventoryStatus::Bloqueado ? 'Falla tecnica demo pendiente de revision.' : null,
                    'eligible_flag' => $eligible,
                    'observations' => $observations,
                ],
            );
        }

        return $lots;
    }

    private function seedPrice(Product $product, User $admin): ProductPrice
    {
        $prices = [
            'MR8-9BA30' => [180, 150],
            'MR8-9BA30D' => [190, 160],
            'MR8-10BA20' => [200, 170],
            'MR8-10BA20D' => [210, 175],
            'MR8-10BA30' => [220, 185],
            'MR8-10BA30D' => [230, 195],
            'MR8-F2/7TA23' => [250, 210],
            'MR8-F3/9TA30' => [250, 210],
            'IRD300' => [300, 250],
        ];
        [$unitPrice, $minimumPrice] = $prices[$product->product_code];

        return ProductPrice::updateOrCreate(
            [
                'product_id' => $product->id,
                'institution_id' => null,
                'doctor_id' => null,
                'price_type' => 'lista',
                'valid_from' => today()->toDateString(),
            ],
            [
                'unit_price' => $unitPrice,
                'minimum_price' => $minimumPrice,
                'currency' => 'PEN',
                'active' => true,
                'valid_until' => null,
                'created_by' => $admin->id,
                'observations' => 'Precio general vigente para el piloto MR8.',
            ],
        );
    }

    private function seedMainCase(Institution $institution, Doctor $doctor, Patient $patient, SurgeryType $surgeryType, User $admin): SurgeryCase
    {
        return SurgeryCase::updateOrCreate(
            ['case_code' => 'MR8-PILOT-001'],
            [
                'status' => CaseStatus::Programada,
                'institution_id' => $institution->id,
                'doctor_id' => $doctor->id,
                'patient_id' => $patient->id,
                'surgery_type_id' => $surgeryType->id,
                'scheduled_at' => now()->addDay()->setTime(8, 0),
                'priority' => 'normal',
                'procedure_name' => 'Cirugia cervical MR8 - caso piloto',
                'request_origin' => 'whatsapp',
                'commercial_condition' => 'convenio',
                'notes' => 'Fresas 9 y 10 cm, 1/2/3 mm, cortantes y diamantadas. Caso demo para validar cobertura, reserva y cierre.',
                'created_by' => $admin->id,
            ],
        );
    }

    private function seedReservation(SurgeryCase $case, InventoryLot $lot, int $quantity, User $reservedBy): Reservation
    {
        return Reservation::updateOrCreate(
            ['case_id' => $case->id, 'inventory_lot_id' => $lot->id],
            [
                'quantity' => $quantity,
                'status' => 'active',
                'reserved_by' => $reservedBy->id,
                'expires_at' => $case->scheduled_at?->copy()->addDay(),
            ],
        );
    }

    private function seedMainDocument(SurgeryCase $case, User $uploadedBy): DocumentEvidence
    {
        $document = DocumentEvidence::updateOrCreate(
            [
                'documentable_type' => SurgeryCase::class,
                'documentable_id' => $case->id,
                'document_type' => 'solicitud',
                'title' => 'Solicitud quirurgica piloto',
            ],
            [
                'description' => 'Referencia de solicitud recibida para el caso demo.',
                'file_path' => null,
                'link_url' => 'https://ops-biomed.local/demo/solicitud/MR8-PILOT-001',
                'mime_type' => 'text/uri-list',
                'file_size' => null,
                'uploaded_by' => $uploadedBy->id,
                'document_date' => today(),
                'is_required' => true,
                'status' => 'cargado',
            ],
        );
        $this->auditOnce('document.uploaded', $document, $uploadedBy->id, [
            'document_type' => $document->document_type,
            'status' => $document->status,
            'link_url' => $document->link_url,
        ]);

        return $document;
    }

    private function seedMainBilling(SurgeryCase $case, User $updatedBy): BillingRecord
    {
        $billing = BillingRecord::updateOrCreate(
            ['case_id' => $case->id],
            [
                'amount' => 780,
                'amount_paid' => 0,
                'currency' => 'PEN',
                'invoice_status' => 'pendiente_oc',
                'purchase_order' => null,
                'invoice_number' => null,
                'invoice_date' => null,
                'due_date' => now()->addDays(30)->toDateString(),
                'payment_status' => 'pendiente',
                'debt_days' => 0,
                'no_billing_reason' => null,
                'observations' => 'Registro demo pendiente de orden de compra.',
                'updated_by' => $updatedBy->id,
            ],
        );
        $this->auditOnce('billing.updated', $billing, $updatedBy->id, [
            'invoice_status' => $billing->invoice_status,
            'amount' => $billing->amount,
        ]);

        return $billing;
    }

    private function seedFailureScenario(InventoryLot $lot, SurgeryCase $case, User $reportedBy): Failure
    {
        $failure = Failure::updateOrCreate(
            [
                'case_id' => $case->id,
                'inventory_lot_id' => $lot->id,
                'description' => 'Falla tecnica demo: vibracion en prueba de funcionamiento.',
            ],
            [
                'product_id' => $lot->product_id,
                'failure_type' => 'vibracion',
                'occurrence_moment' => 'inventario',
                'severity' => 'alta',
                'status' => 'bloqueada',
                'preventive_block' => true,
                'action_taken' => 'Lote aislado preventivamente para revision tecnica.',
                'evidence_reference' => null,
                'reported_by' => $reportedBy->id,
                'responsible_technical_id' => $reportedBy->id,
                'requires_supplier' => true,
                'requires_replacement' => true,
            ],
        );
        $this->auditOnce('failure.reported', $failure, $reportedBy->id, [
            'severity' => $failure->severity,
            'status' => $failure->status,
            'preventive_block' => $failure->preventive_block,
        ]);
        $this->auditOnce('inventory.blocked', $lot, $reportedBy->id, [
            'failure_id' => $failure->id,
            'status' => $lot->status->value,
            'eligible_flag' => $lot->eligible_flag,
        ]);

        return $failure;
    }

    private function seedReturnScenario(InventoryLot $lot, SurgeryCase $case): CaseReturn
    {
        $return = CaseReturn::updateOrCreate(
            ['case_id' => $case->id, 'inventory_lot_id' => $lot->id],
            [
                'returned_qty' => 1,
                'condition' => 'pendiente_inspeccion',
                'inspected_by' => null,
                'inspected_at' => null,
                'inspection_result' => null,
                'inspection_observations' => null,
                'inspection_evidence_reference' => null,
                'inspection_responsible_id' => null,
                'inspection_date' => null,
                'technical_failure_id' => null,
                'non_reusable_reason' => null,
            ],
        );
        $this->auditOnce('return.pending_inspection', $return, null, [
            'case_id' => $return->case_id,
            'inventory_lot_id' => $return->inventory_lot_id,
            'returned_qty' => $return->returned_qty,
            'condition' => $return->condition,
        ]);

        return $return;
    }

    private function seedClosureCase(Institution $institution, Doctor $doctor, Patient $patient, SurgeryType $surgeryType, User $createdBy): SurgeryCase
    {
        return SurgeryCase::updateOrCreate(
            ['case_code' => 'MR8-PILOT-CIERRE-001'],
            [
                'status' => CaseStatus::Cerrado,
                'institution_id' => $institution->id,
                'doctor_id' => $doctor->id,
                'patient_id' => $patient->id,
                'surgery_type_id' => $surgeryType->id,
                'scheduled_at' => now()->subDays(3)->setTime(9, 0),
                'priority' => 'normal',
                'procedure_name' => 'Cirugia cervical MR8 - cierre demo',
                'request_origin' => 'correo',
                'commercial_condition' => 'costo_cero_autorizado',
                'notes' => 'Caso cerrado demo para conciliacion, valorizacion y aprobacion de costo cero.',
                'created_by' => $createdBy->id,
            ],
        );
    }

    private function seedReconciliation(SurgeryCase $case, User $completedBy): CaseReconciliation
    {
        $reconciliation = CaseReconciliation::updateOrCreate(
            ['case_id' => $case->id],
            [
                'status' => 'conciliado',
                'total_reserved' => 1,
                'total_used' => 1,
                'total_returned' => 0,
                'total_unused_opened' => 0,
                'total_failure' => 0,
                'total_difference' => 0,
                'observations' => 'Conciliacion demo completa para validar el cierre.',
                'completed_by' => $completedBy->id,
                'completed_at' => now()->subDays(2),
            ],
        );
        $this->auditOnce('reconciliation.completed', $reconciliation, $completedBy->id, [
            'case_id' => $case->id,
            'status' => $reconciliation->status,
            'total_difference' => $reconciliation->total_difference,
        ]);

        return $reconciliation;
    }

    private function seedCostZeroScenario(SurgeryCase $case, Reservation $reservation, InventoryLot $lot, User $reportedBy): void
    {
        $material = CaseMaterialUsed::updateOrCreate(
            ['case_id' => $case->id, 'reservation_id' => $reservation->id],
            [
                'inventory_lot_id' => $lot->id,
                'reserved_qty' => 1,
                'opened_qty' => 1,
                'used_qty' => 1,
                'unused_opened_qty' => 0,
                'returned_qty' => 0,
                'failure_qty' => 0,
                'difference_qty' => 0,
                'difference_reason' => null,
                'evidence_description' => 'Hoja de consumo demo registrada.',
                'evidence_reference' => 'https://ops-biomed.local/demo/consumo/MR8-PILOT-CIERRE-001',
                'failure_description' => null,
                'unit_price' => 0,
                'minimum_unit_price' => 175,
                'price_below_minimum' => false,
                'subtotal' => 0,
                'cost_zero' => true,
                'cost_zero_reason' => 'Demostracion piloto autorizable para capacitacion comercial.',
                'requires_approval' => true,
                'notes' => 'Caso demo de costo cero pendiente de aprobacion.',
                'reported_by' => $reportedBy->id,
            ],
        );

        $valuation = CaseValuation::updateOrCreate(
            ['case_id' => $case->id],
            [
                'subtotal' => 0,
                'total' => 0,
                'currency' => 'PEN',
                'status' => 'costo_cero_pendiente_aprobacion',
                'created_by' => $reportedBy->id,
            ],
        );
        $line = CaseValuationLine::updateOrCreate(
            ['case_valuation_id' => $valuation->id, 'reservation_id' => $reservation->id],
            [
                'case_id' => $case->id,
                'product_id' => $lot->product_id,
                'inventory_lot_id' => $lot->id,
                'quantity_used' => 1,
                'unit_price' => 0,
                'minimum_unit_price' => 175,
                'price_below_minimum' => false,
                'subtotal' => 0,
                'cost_zero' => true,
                'cost_zero_reason' => 'Demostracion piloto autorizable para capacitacion comercial.',
                'requires_approval' => true,
            ],
        );
        $approval = Approval::updateOrCreate(
            ['valuation_id' => $valuation->id, 'type' => 'cost_zero'],
            [
                'case_id' => $case->id,
                'status' => 'pendiente_aprobacion',
                'requested_by' => $reportedBy->id,
                'approved_by' => null,
                'approved_at' => null,
                'evidence' => 'Referencia demo de costo cero.',
                'reason' => 'Capacitacion piloto y validacion de flujo de aprobacion.',
            ],
        );
        BillingRecord::updateOrCreate(
            ['case_id' => $case->id],
            [
                'amount' => 0,
                'amount_paid' => 0,
                'currency' => 'PEN',
                'invoice_status' => 'costo_cero_pendiente_aprobacion',
                'payment_status' => 'pendiente',
                'debt_days' => 0,
                'no_billing_reason' => null,
                'observations' => 'Pendiente de aprobacion de costo cero.',
                'updated_by' => $reportedBy->id,
            ],
        );
        $this->auditOnce('consumption.recorded', $material, $reportedBy->id, [
            'case_id' => $case->id,
            'used_qty' => 1,
            'cost_zero' => true,
        ]);
        $this->auditOnce('valuation.created', $valuation, $reportedBy->id, [
            'case_id' => $case->id,
            'total' => 0,
            'status' => $valuation->status,
            'line_id' => $line->id,
        ]);
        $this->auditOnce('cost_zero.requested', $approval, $reportedBy->id, [
            'case_id' => $case->id,
            'valuation_id' => $valuation->id,
            'status' => $approval->status,
        ]);
    }

    /** @param array<string, mixed> $after */
    private function auditOnce(string $action, ?Model $model, ?int $userId, array $after): AuditLog
    {
        return AuditLog::query()->firstOrCreate(
            [
                'action' => $action,
                'auditable_type' => $model ? $model::class : null,
                'auditable_id' => $model?->getKey(),
            ],
            [
                'user_id' => $userId,
                'before' => [],
                'after' => $after,
                'created_at' => now(),
            ],
        );
    }
}
