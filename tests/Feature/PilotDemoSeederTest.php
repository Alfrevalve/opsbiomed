<?php

namespace Tests\Feature;

use App\Models\BillingRecord;
use App\Models\CaseValuation;
use App\Models\DocumentEvidence;
use App\Models\Failure;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Reservation;
use App\Models\SurgeryCase;
use App\Models\User;
use Database\Seeders\PilotDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilotDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_pilot_demo_seeder_is_idempotent_and_loads_dashboard(): void
    {
        $this->seed(PilotDemoSeeder::class);

        $this->assertSame(8, User::query()->whereIn('email', [
            'admin@ops.test',
            'jefe.linea@ops.test',
            'dt@ops.test',
            'almacen@ops.test',
            'instrumentista@ops.test',
            'comercial@ops.test',
            'cobranza@ops.test',
            'gerencia@ops.test',
        ])->count());
        $this->assertDatabaseHas('roles', ['name' => 'Administrador']);
        $this->assertTrue(User::query()->where('email', 'admin@ops.test')->firstOrFail()->hasRole('Administrador'));
        $this->assertDatabaseHas('institutions', ['name' => "Cl\u{00ed}nica Piloto OPS"]);
        $this->assertDatabaseHas('doctors', ['name' => "Dr. Piloto Neurocirug\u{00ed}a"]);
        $this->assertDatabaseHas('patients', ['code' => 'PAT-PILOT-MR8-001']);
        $this->assertSame(9, Product::query()->where('product_line', 'MR8')->where('subfamily', 'Piloto MR8')->count());
        $this->assertSame(9, ProductPrice::query()->whereHas('product', fn ($query) => $query->where('subfamily', 'Piloto MR8'))->count());
        $this->assertGreaterThanOrEqual(13, InventoryLot::query()->where('location', 'like', 'PILOT-%')->count());

        $case = SurgeryCase::query()->where('case_code', 'MR8-PILOT-001')->firstOrFail();
        $this->assertSame('reservado', $case->status->value);
        $this->assertSame(2, Reservation::query()->where('case_id', $case->id)->count());
        $this->assertDatabaseHas('document_evidences', [
            'documentable_type' => SurgeryCase::class,
            'documentable_id' => $case->id,
            'document_type' => 'solicitud',
            'status' => 'cargado',
        ]);
        $this->assertSame(1, Failure::query()->where('description', 'Falla tecnica demo: vibracion en prueba de funcionamiento.')->count());
        $this->assertDatabaseHas('billing_records', ['case_id' => $case->id, 'invoice_status' => 'pendiente_oc']);
        $this->assertDatabaseHas('case_returns', ['case_id' => $case->id, 'condition' => 'pendiente_inspeccion']);
        $this->assertDatabaseHas('case_valuations', [
            'case_id' => SurgeryCase::query()->where('case_code', 'MR8-PILOT-CIERRE-001')->value('id'),
            'status' => 'costo_cero_pendiente_aprobacion',
        ]);
        $this->assertDatabaseHas('case_reconciliations', [
            'case_id' => SurgeryCase::query()->where('case_code', 'MR8-PILOT-CIERRE-001')->value('id'),
            'status' => 'conciliado',
        ]);
        $this->assertInstanceOf(CaseValuation::class, SurgeryCase::query()->where('case_code', 'MR8-PILOT-CIERRE-001')->firstOrFail()->valuation);
        $this->assertSame(1, DocumentEvidence::query()->where('documentable_type', SurgeryCase::class)->where('documentable_id', $case->id)->count());

        $counts = [
            User::query()->where('email', 'admin@ops.test')->count(),
            Product::query()->where('product_line', 'MR8')->where('subfamily', 'Piloto MR8')->count(),
            InventoryLot::query()->where('location', 'like', 'PILOT-%')->count(),
            SurgeryCase::query()->where('case_code', 'like', 'MR8-PILOT-%')->count(),
            DocumentEvidence::query()->where('title', 'Solicitud quirurgica piloto')->count(),
            BillingRecord::query()->whereHas('case', fn ($query) => $query->where('case_code', 'like', 'MR8-PILOT-%'))->count(),
        ];

        $this->seed(PilotDemoSeeder::class);

        $this->assertSame($counts, [
            User::query()->where('email', 'admin@ops.test')->count(),
            Product::query()->where('product_line', 'MR8')->where('subfamily', 'Piloto MR8')->count(),
            InventoryLot::query()->where('location', 'like', 'PILOT-%')->count(),
            SurgeryCase::query()->where('case_code', 'like', 'MR8-PILOT-%')->count(),
            DocumentEvidence::query()->where('title', 'Solicitud quirurgica piloto')->count(),
            BillingRecord::query()->whereHas('case', fn ($query) => $query->where('case_code', 'like', 'MR8-PILOT-%'))->count(),
        ]);

        $admin = User::query()->where('email', 'admin@ops.test')->firstOrFail();
        $this->actingAs($admin)
            ->get(route('dashboard.ops'))
            ->assertOk()
            ->assertSee('Torre de Control Quirurgica')
            ->assertSee('MR8-PILOT-001');

        $this->assertDatabaseHas('audit_logs', ['action' => 'pilot.demo.seeded']);
    }
}
