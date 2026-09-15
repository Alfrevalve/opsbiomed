<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\InventoryStatus;
use App\Models\Institution;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\SurgeryCase;
use App\Models\SurgeryType;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SurgeryCaseClosingTest extends TestCase
{
    use RefreshDatabase;

    public function test_close_screen_and_button_are_available_for_authorized_case(): void
    {
        [$user, $case] = $this->fixture();

        $this->actingAs($user)
            ->get(route('cases.show', $case))
            ->assertOk()
            ->assertSee('Cerrar cirugia');

        $this->actingAs($user)
            ->get(route('cases.close.create', $case))
            ->assertOk()
            ->assertSee('Estado actual:')
            ->assertSee('Reservado')
            ->assertSee('Al guardar correctamente pasara a: Cerrado')
            ->assertDontSee('Estado final:')
            ->assertSee('Material reservado')
            ->assertSee('Evidencia de consumo')
            ->assertSee('Cantidad reservada')
            ->assertSee('Cantidad conciliada')
            ->assertSee('La suma de usada + abierta no usada + devuelta + falla debe igualar lo reservado.')
            ->assertSee('data-material-row', false)
            ->assertSee('data-reconciliation-field="used_qty"', false)
            ->assertSee('data-reconciliation-field="unused_opened_qty"', false)
            ->assertSee('data-reconciliation-field="returned_qty"', false)
            ->assertSee('data-reconciliation-field="failure_qty"', false)
            // Legacy aliases must not return to the rendered closure form.
            ->assertDontSee('opened_unused_qty', false)
            ->assertDontSee('failed_qty', false)
            ->assertSee('step="1"', false)
            ->assertSee('name="materials[0][used_qty]" type="number" min="0" step="1" value="0" data-reconciliation-field="used_qty"', false)
            ->assertSee('data-difference-reason', false)
            ->assertSee('data-failure-description', false);
    }

    public function test_close_normalizes_blank_quantities_to_zero(): void
    {
        [$user, $case, $lot, $reservation] = $this->fixture(['reservation_quantity' => 3]);

        $this->actingAs($user)
            ->post(route('cases.close', $case), [
                'materials' => [$this->materialLine($reservation, [
                    'used_qty' => '3',
                    'unused_opened_qty' => '',
                    'returned_qty' => null,
                    'failure_qty' => ' ',
                    'unit_price' => '10',
                ])],
                'evidence_description' => 'Evidencia de cierre con cantidades vacias.',
            ])
            ->assertRedirect(route('cases.show', $case));

        $this->assertSame(CaseStatus::Cerrado, $case->refresh()->status);
        $this->assertSame(7, (int) $lot->refresh()->quantity);
        $this->assertDatabaseHas('case_materials_used', [
            'reservation_id' => $reservation->id,
            'used_qty' => 3,
            'unused_opened_qty' => 0,
            'returned_qty' => 0,
            'failure_qty' => 0,
            'difference_qty' => 0,
        ]);
    }

    public function test_close_rejects_decimal_quantities_with_an_operational_message(): void
    {
        [$user, $case, , $reservation] = $this->fixture(['reservation_quantity' => 1]);

        $this->actingAs($user)
            ->post(route('cases.close', $case), [
                'materials' => [$this->materialLine($reservation, [
                    'used_qty' => 0,
                    'returned_qty' => '1.5',
                ])],
                'evidence_description' => 'Evidencia para validar decimales.',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'materials.0.returned_qty' => "La cantidad devuelta debe ser un n\u{00fa}mero entero.",
            ]);

        $this->assertSame(CaseStatus::Reservado, $case->refresh()->status);
        $this->assertDatabaseCount('case_materials_used', 0);
    }

    public function test_close_rejects_negative_quantities(): void
    {
        [$user, $case, , $reservation] = $this->fixture(['reservation_quantity' => 1]);

        $this->actingAs($user)
            ->post(route('cases.close', $case), [
                'materials' => [$this->materialLine($reservation, [
                    'used_qty' => '-1',
                    'returned_qty' => 1,
                ])],
                'evidence_description' => 'Evidencia para validar cantidades negativas.',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('materials.0.used_qty');

        $this->assertSame(CaseStatus::Reservado, $case->refresh()->status);
        $this->assertDatabaseCount('case_materials_used', 0);
    }

    public function test_close_requires_a_difference_reason_after_normalizing_blank_quantities(): void
    {
        [$user, $case, , $reservation] = $this->fixture(['reservation_quantity' => 3]);

        $this->actingAs($user)
            ->post(route('cases.close', $case), [
                'materials' => [$this->materialLine($reservation, [
                    'used_qty' => 2,
                    'unused_opened_qty' => '',
                    'returned_qty' => '',
                    'failure_qty' => null,
                    'unit_price' => '10',
                ])],
                'evidence_description' => 'Evidencia con diferencia sin justificar.',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('close');

        $this->assertSame(CaseStatus::Reservado, $case->refresh()->status);
        $this->assertDatabaseCount('case_materials_used', 0);
    }

    public function test_case_cannot_close_without_consumption_evidence(): void
    {
        [$user, $case, , $reservation] = $this->fixture(['reservation_quantity' => 2]);

        $this->actingAs($user)
            ->post(route('cases.close', $case), [
                'materials' => [$this->materialLine($reservation, ['used_qty' => 2])],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('evidence_description');

        $this->assertSame(CaseStatus::Reservado, $case->refresh()->status);
    }

    public function test_case_cannot_close_without_accounting_for_each_reserved_material(): void
    {
        [$user, $case, , $reservation] = $this->fixture(['reservation_quantity' => 3]);

        $this->actingAs($user)
            ->post(route('cases.close', $case), [
                'materials' => [$this->materialLine($reservation)],
                'evidence_description' => 'Acta de consumo sin conciliacion completa.',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('close');

        $this->assertSame(CaseStatus::Reservado, $case->refresh()->status);
        $this->assertDatabaseCount('case_materials_used', 0);
    }

    public function test_consumption_reduces_stock_and_creates_preliminary_valuation(): void
    {
        [$user, $case, $lot, $reservation] = $this->fixture([
            'lot_quantity' => 10,
            'reservation_quantity' => 3,
        ]);

        $response = $this->actingAs($user)
            ->post(route('cases.close', $case), [
                'materials' => [$this->materialLine($reservation, [
                    'used_qty' => 2,
                    'returned_qty' => 1,
                    'unit_price' => '12.50',
                ])],
                'evidence_description' => 'Acta de consumo firmada por instrumentista.',
                'evidence_reference' => 'ACTA-MR8-001',
            ]);

        $response->assertRedirect(route('cases.show', $case));
        $this->assertSame(CaseStatus::Cerrado, $case->refresh()->status);
        $this->assertSame(8, (int) $lot->refresh()->quantity);
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'partially_consumed',
        ]);
        $this->assertDatabaseHas('case_materials_used', [
            'reservation_id' => $reservation->id,
            'reserved_qty' => 3,
            'used_qty' => 2,
            'returned_qty' => 1,
            'difference_qty' => 0,
            'subtotal' => 25,
        ]);
        $this->assertDatabaseHas('case_returns', [
            'case_id' => $case->id,
            'returned_qty' => 1,
            'condition' => 'pendiente_inspeccion',
        ]);
        $this->assertDatabaseHas('billing_records', [
            'case_id' => $case->id,
            'amount' => 25,
            'invoice_status' => 'valorizado',
        ]);
        $this->assertDatabaseHas('case_valuations', [
            'case_id' => $case->id,
            'total' => 25,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'case.closed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'consumption.recorded']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'valuation.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'billing.status.updated']);
    }

    public function test_difference_is_recorded_and_visible_on_dashboard(): void
    {
        [$user, $case, , $reservation] = $this->fixture(['reservation_quantity' => 3]);

        $this->actingAs($user)->post(route('cases.close', $case), [
            'materials' => [$this->materialLine($reservation, [
                'used_qty' => 2,
                'difference_reason' => 'Una unidad no fue localizada durante conciliacion.',
                'unit_price' => '10',
            ])],
            'evidence_description' => 'Acta con diferencia reportada.',
        ])->assertRedirect(route('cases.show', $case));

        $this->assertDatabaseHas('case_materials_used', [
            'case_id' => $case->id,
            'difference_qty' => 1,
            'difference_reason' => 'Una unidad no fue localizada durante conciliacion.',
        ]);
        $this->actingAs($user)
            ->get(route('dashboard.ops'))
            ->assertOk()
            ->assertSee('Diferencias de consumo');
    }

    public function test_cost_zero_requires_reason_and_sets_pending_approval(): void
    {
        [$user, $case, , $reservation] = $this->fixture(['reservation_quantity' => 1]);
        $line = $this->materialLine($reservation, ['used_qty' => 1, 'cost_zero' => 1]);

        $this->actingAs($user)
            ->post(route('cases.close', $case), [
                'materials' => [$line],
                'evidence_description' => 'Evidencia de costo cero.',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('materials.0.cost_zero_reason');

        $this->assertSame(CaseStatus::Reservado, $case->refresh()->status);

        $this->actingAs($user)
            ->post(route('cases.close', $case), [
                'materials' => [$line + ['cost_zero_reason' => 'Muestra autorizada para evaluacion.']],
                'evidence_description' => 'Evidencia de costo cero autorizable.',
            ])
            ->assertRedirect(route('cases.show', $case));

        $this->assertDatabaseHas('billing_records', [
            'case_id' => $case->id,
            'invoice_status' => 'costo_cero_pendiente_aprobacion',
        ]);
        $this->assertDatabaseHas('case_materials_used', [
            'case_id' => $case->id,
            'cost_zero' => true,
            'requires_approval' => true,
        ]);
    }

    public function test_failure_creates_alert_and_preventive_block(): void
    {
        [$user, $case, $lot, $reservation] = $this->fixture(['reservation_quantity' => 2]);

        $this->actingAs($user)->post(route('cases.close', $case), [
            'materials' => [$this->materialLine($reservation, [
                'returned_qty' => 1,
                'failure_qty' => 1,
                'failure_description' => 'Fresa con vibracion anormal.',
            ])],
            'evidence_description' => 'Reporte de incidencia en sala.',
        ])->assertRedirect(route('cases.show', $case));

        $this->assertSame(InventoryStatus::FallaPreventiva, $lot->refresh()->status);
        $this->assertFalse($lot->eligible_flag);
        $this->assertDatabaseHas('failures', [
            'case_id' => $case->id,
            'inventory_lot_id' => $lot->id,
            'preventive_block' => true,
            'status' => 'bloqueada',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'failure.detected']);
    }

    public function test_dashboard_shows_closure_operational_metrics(): void
    {
        [$user, $case, , $reservation] = $this->fixture(['reservation_quantity' => 2]);
        $user->givePermissionTo(Permission::findOrCreate('billing.view'));
        $this->actingAs($user)->post(route('cases.close', $case), [
            'materials' => [$this->materialLine($reservation, [
                'used_qty' => 1,
                'difference_reason' => 'Diferencia para tablero.',
                'cost_zero' => 1,
                'cost_zero_reason' => 'Cortesia institucional.',
            ])],
            'evidence_description' => 'Evidencia de cierre para tablero.',
        ])->assertRedirect();

        $this->actingAs($user)
            ->get(route('dashboard.ops'))
            ->assertOk()
            ->assertSee('Pendientes de cierre')
            ->assertSee('Solicitudes sin valorizacion')
            ->assertSee('Diferencias de consumo')
            ->assertSee('Costos cero pendientes')
            ->assertSee('Devoluciones pendientes de inspeccion');
    }

    public function test_comercial_cannot_see_valuation_details_without_billing_permission(): void
    {
        [$user, $case, , $reservation] = $this->fixture(['reservation_quantity' => 1]);
        $this->actingAs($user)->post(route('cases.close', $case), [
            'materials' => [$this->materialLine($reservation, [
                'used_qty' => 1,
                'unit_price' => '8',
            ])],
            'evidence_description' => 'Evidencia de cierre para control de acceso.',
        ])->assertRedirect();

        $permissions = collect(['dashboard.view', 'cases.view'])
            ->map(fn (string $permission) => Permission::findOrCreate($permission));
        $role = Role::findOrCreate('Comercial');
        $role->syncPermissions($permissions);
        $commercial = User::factory()->create();
        $commercial->assignRole($role);

        $this->actingAs($commercial)
            ->get(route('cases.show', $case))
            ->assertOk()
            ->assertDontSee('Valorizacion preliminar')
            ->assertDontSee('S/ 8.00');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1: SurgeryCase, 2: InventoryLot, 3: Reservation}
     */
    private function fixture(array $overrides = []): array
    {
        $user = $this->closingManager();
        $institution = Institution::create(['name' => 'Institucion cierre '.uniqid(), 'active' => true]);
        $type = SurgeryType::create([
            'code' => 'cierre_'.uniqid(),
            'name' => 'Cirugia cierre '.uniqid(),
            'active' => true,
        ]);
        $case = SurgeryCase::create([
            'case_code' => 'MR8-CLOSE-'.uniqid(),
            'status' => CaseStatus::Reservado,
            'institution_id' => $institution->id,
            'surgery_type_id' => $type->id,
            'scheduled_at' => now()->addDay(),
            'priority' => 'normal',
            'procedure_name' => 'Kit de cierre MR8',
            'request_origin' => 'whatsapp',
            'created_by' => $user->id,
        ]);
        $warehouse = Warehouse::create([
            'name' => 'Principal cierre '.uniqid(),
            'type' => 'principal',
            'lead_time_hours' => 0,
            'counts_as_immediate' => true,
            'active' => true,
        ]);
        $product = Product::create([
            'product_line' => 'MR8',
            'family' => 'Fresas',
            'subfamily' => 'Cierre',
            'product_code' => 'MR8-CLOSE-'.uniqid(),
            'name' => 'Producto cierre',
            'classification' => 'consumible',
            'expiry_required' => false,
            'tracking_type' => 'lot',
            'active' => true,
        ]);
        $lot = InventoryLot::create([
            'product_id' => $product->id,
            'lot' => 'CLOSE-LOT-'.uniqid(),
            'warehouse_id' => $warehouse->id,
            'quantity' => $overrides['lot_quantity'] ?? 10,
            'expiry' => now()->addYear(),
            'status' => InventoryStatus::Apto,
            'eligible_flag' => true,
        ]);
        $reservation = Reservation::create([
            'case_id' => $case->id,
            'inventory_lot_id' => $lot->id,
            'quantity' => $overrides['reservation_quantity'] ?? 3,
            'status' => 'active',
            'reserved_by' => $user->id,
        ]);

        return [$user, $case, $lot, $reservation];
    }

    private function closingManager(): User
    {
        $permissions = collect(['dashboard.view', 'cases.view', 'cases.close', 'inventory.view', 'failures.create'])
            ->map(fn (string $permission) => Permission::findOrCreate($permission));
        $role = Role::findOrCreate('Almacen');
        $role->syncPermissions($permissions);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function materialLine(Reservation $reservation, array $overrides = []): array
    {
        return array_merge([
            'reservation_id' => $reservation->id,
            'used_qty' => 0,
            'unused_opened_qty' => 0,
            'returned_qty' => $overrides['returned_qty'] ?? 0,
            'failure_qty' => 0,
            'cost_zero' => 0,
        ], $overrides);
    }
}
