<?php

namespace Tests\Feature;

use App\Models\BillingRecord;
use App\Models\Doctor;
use App\Models\DocumentEvidence;
use App\Models\Institution;
use App\Models\Patient;
use App\Models\SurgeryCase;
use App\Models\SurgeryType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocumentEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_upload_document_to_case_and_audit_it(): void
    {
        Storage::fake('private');
        $user = $this->userWithPermissions(['cases.view', 'documents.view', 'documents.upload']);
        $case = $this->case($user);
        $file = UploadedFile::fake()->create('hoja-consumo.pdf', 100, 'application/pdf');

        $this->actingAs($user)
            ->post(route('cases.documents.store', $case), [
                'document_type' => 'hoja_consumo',
                'title' => 'Hoja de consumo firmada',
                'description' => 'Evidencia de cierre de la cirugia.',
                'file' => $file,
            ])
            ->assertRedirect(route('cases.documents.index', $case));

        $document = DocumentEvidence::query()->firstOrFail();
        $this->assertSame(SurgeryCase::class, $document->documentable_type);
        $this->assertNotNull($document->file_path);
        Storage::disk('private')->assertExists($document->file_path);
        $this->actingAs($user)
            ->get(route('documents.download', $document))
            ->assertOk();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.uploaded',
            'auditable_type' => DocumentEvidence::class,
            'auditable_id' => $document->id,
        ]);
    }

    public function test_can_register_text_reference_as_evidence(): void
    {
        $user = $this->userWithPermissions(['cases.view', 'documents.view', 'documents.upload']);
        $case = $this->case($user);

        $this->actingAs($user)
            ->post(route('cases.documents.store', $case), [
                'document_type' => 'evidencia_consumo',
                'title' => 'Referencia de consumo',
                'link_url' => 'Acta física archivada en almacén, folio 2026-018.',
            ])
            ->assertRedirect(route('cases.documents.index', $case));

        $this->assertDatabaseHas('document_evidences', [
            'documentable_type' => SurgeryCase::class,
            'documentable_id' => $case->id,
            'link_url' => 'Acta física archivada en almacén, folio 2026-018.',
            'file_path' => null,
        ]);
    }

    public function test_generic_document_upload_requires_access_to_the_target_record(): void
    {
        $case = $this->case($this->userWithPermissions(['documents.upload']));
        $payload = [
            'documentable_type' => 'case',
            'documentable_id' => $case->id,
            'document_type' => 'solicitud',
            'title' => 'Solicitud de prueba',
            'link_url' => 'Referencia del expediente.',
        ];

        $this->actingAs($this->userWithPermissions(['documents.upload']))
            ->post(route('documents.store'), $payload)
            ->assertForbidden();

        $this->assertDatabaseCount('document_evidences', 0);

        $authorizedUser = $this->userWithPermissions(['documents.upload', 'cases.view']);

        $this->actingAs($authorizedUser)
            ->post(route('documents.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('document_evidences', [
            'documentable_type' => SurgeryCase::class,
            'documentable_id' => $case->id,
            'title' => 'Solicitud de prueba',
        ]);
    }

    public function test_case_control_only_renders_http_links_as_clickable(): void
    {
        $user = $this->userWithPermissions(['cases.view', 'documents.view']);
        $case = $this->case($user);

        foreach ([
            ['title' => 'Referencia no confiable', 'link_url' => 'javascript:alert(1)'],
            ['title' => 'Referencia segura', 'link_url' => 'https://evidence.example.test/record'],
        ] as $attributes) {
            DocumentEvidence::create([
                'documentable_type' => SurgeryCase::class,
                'documentable_id' => $case->id,
                'document_type' => 'solicitud',
                'title' => $attributes['title'],
                'link_url' => $attributes['link_url'],
                'uploaded_by' => $user->id,
                'status' => 'cargado',
            ]);
        }

        $this->actingAs($user)
            ->get(route('cases.control', $case))
            ->assertOk()
            ->assertDontSee('href="javascript:alert(1)"', false)
            ->assertSee('href="https://evidence.example.test/record"', false)
            ->assertSee('Referencia registrada');
    }

    public function test_rejects_unsupported_file_type_and_oversized_file(): void
    {
        Storage::fake('private');
        $user = $this->userWithPermissions(['cases.view', 'documents.view', 'documents.upload']);
        $case = $this->case($user);

        $this->actingAs($user)
            ->post(route('cases.documents.store', $case), [
                'document_type' => 'otro',
                'title' => 'Archivo no permitido',
                'file' => UploadedFile::fake()->create('script.exe', 100, 'application/octet-stream'),
            ])
            ->assertSessionHasErrors('file');

        $this->actingAs($user)
            ->post(route('cases.documents.store', $case), [
                'document_type' => 'otro',
                'title' => 'Archivo demasiado grande',
                'file' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('document_evidences', 0);
    }

    public function test_document_is_visible_on_case_detail(): void
    {
        $user = $this->userWithPermissions(['dashboard.view', 'cases.view', 'documents.view']);
        $case = $this->case($user);
        DocumentEvidence::create([
            'documentable_type' => SurgeryCase::class,
            'documentable_id' => $case->id,
            'document_type' => 'solicitud',
            'title' => 'Solicitud firmada',
            'link_url' => 'Archivo físico, expediente MR8.',
            'uploaded_by' => $user->id,
            'status' => 'cargado',
        ]);

        $this->actingAs($user)
            ->get(route('cases.show', $case))
            ->assertOk()
            ->assertSee('Documentos y evidencias')
            ->assertSee('Solicitud firmada');
    }

    public function test_observed_document_appears_on_dashboard(): void
    {
        $user = $this->userWithPermissions(['dashboard.view', 'cases.view', 'documents.view']);
        $case = $this->case($user);
        DocumentEvidence::create([
            'documentable_type' => SurgeryCase::class,
            'documentable_id' => $case->id,
            'document_type' => 'hoja_consumo',
            'title' => 'Hoja observada',
            'link_url' => 'Pendiente de firma.',
            'uploaded_by' => $user->id,
            'status' => 'observado',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard.ops'))
            ->assertOk()
            ->assertSee('Trazabilidad documental')
            ->assertSee('Documentos observados');
    }

    public function test_user_without_delete_permission_cannot_delete_document(): void
    {
        $user = $this->userWithPermissions(['documents.view']);
        $case = $this->case($user);
        $document = DocumentEvidence::create([
            'documentable_type' => SurgeryCase::class,
            'documentable_id' => $case->id,
            'document_type' => 'solicitud',
            'title' => 'Documento protegido',
            'link_url' => 'Referencia interna.',
            'uploaded_by' => $user->id,
            'status' => 'cargado',
        ]);

        $this->actingAs($user)
            ->delete(route('documents.destroy', $document))
            ->assertForbidden();

        $this->assertDatabaseHas('document_evidences', ['id' => $document->id, 'deleted_at' => null]);
    }

    public function test_document_validation_updates_status_and_audits_it(): void
    {
        $user = $this->userWithPermissions(['documents.view', 'documents.validate']);
        $case = $this->case($user);
        $document = DocumentEvidence::create([
            'documentable_type' => SurgeryCase::class,
            'documentable_id' => $case->id,
            'document_type' => 'inspeccion',
            'title' => 'Acta de inspeccion',
            'link_url' => 'Referencia técnica.',
            'uploaded_by' => $user->id,
            'status' => 'cargado',
        ]);

        $this->actingAs($user)
            ->post(route('documents.validate', $document), [
                'status' => 'observado',
                'validation_observations' => 'Falta firma del responsable.',
            ])
            ->assertRedirect(route('documents.show', $document));

        $this->assertDatabaseHas('document_evidences', [
            'id' => $document->id,
            'status' => 'observado',
            'validated_by' => $user->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.validated',
            'auditable_id' => $document->id,
        ]);
    }

    public function test_soft_delete_keeps_private_file_and_audit_trace(): void
    {
        Storage::fake('private');
        $user = $this->userWithPermissions(['cases.view', 'documents.view', 'documents.upload', 'documents.delete']);
        $case = $this->case($user);
        $this->actingAs($user)->post(route('cases.documents.store', $case), [
            'document_type' => 'solicitud',
            'title' => 'Documento para eliminar',
            'file' => UploadedFile::fake()->create('solicitud.pdf', 20, 'application/pdf'),
        ])->assertRedirect();
        $document = DocumentEvidence::query()->firstOrFail();
        $path = $document->file_path;

        $this->actingAs($user)
            ->delete(route('documents.destroy', $document))
            ->assertRedirect(route('documents.index'));

        $this->assertSoftDeleted('document_evidences', ['id' => $document->id]);
        Storage::disk('private')->assertExists($path);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.deleted',
            'auditable_id' => $document->id,
        ]);
    }

    public function test_commercial_cannot_view_financial_documents_through_repository_case_or_billing_pages(): void
    {
        Storage::fake('private');
        $case = $this->case($this->userWithPermissions(['cases.view']));
        $billing = BillingRecord::create([
            'case_id' => $case->id,
            'amount' => 1250,
            'amount_paid' => 0,
            'invoice_status' => 'pendiente_factura',
            'payment_status' => 'pendiente',
        ]);
        $path = 'evidence/private-invoice.pdf';
        Storage::disk('private')->put($path, 'synthetic invoice');
        $financialDocument = DocumentEvidence::create([
            'documentable_type' => BillingRecord::class,
            'documentable_id' => $billing->id,
            'document_type' => 'factura',
            'title' => 'Factura confidencial de prueba',
            'file_path' => $path,
            'uploaded_by' => User::factory()->create()->id,
            'status' => 'cargado',
        ]);

        $commercial = $this->userWithPermissions(['cases.view', 'documents.view', 'commercial.view']);

        $this->actingAs($commercial)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertDontSee('Factura confidencial de prueba');
        $this->get(route('cases.documents.index', $case))
            ->assertOk()
            ->assertDontSee('Factura confidencial de prueba');
        $this->get(route('billing.index'))
            ->assertOk()
            ->assertDontSee('Pagado')
            ->assertDontSee('Total valorizado');
        $this->get(route('billing.show', $case))
            ->assertOk()
            ->assertDontSee('Factura confidencial de prueba')
            ->assertDontSee('1,250.00')
            ->assertSee('Restringido');
        $this->get(route('documents.show', $financialDocument))->assertForbidden();
        $this->get(route('documents.download', $financialDocument))->assertForbidden();

        $billingUser = $this->userWithPermissions(['documents.view', 'billing.view']);
        $this->actingAs($billingUser)
            ->get(route('documents.show', $financialDocument))
            ->assertOk()
            ->assertSee('Factura confidencial de prueba');
    }

    public function test_user_without_billing_permission_cannot_upload_financial_evidence_to_case(): void
    {
        $commercial = $this->userWithPermissions(['cases.view', 'documents.view', 'documents.upload', 'commercial.view']);
        $case = $this->case($commercial);

        $this->actingAs($commercial)
            ->post(route('cases.documents.store', $case), [
                'document_type' => 'factura',
                'title' => 'Factura no autorizada',
                'link_url' => 'Referencia de prueba.',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('document_evidences', ['title' => 'Factura no autorizada']);
    }

    /** @param list<string> $permissions */
    private function userWithPermissions(array $permissions): User
    {
        $role = Role::create(['name' => 'documents-test-'.uniqid()]);
        $role->syncPermissions(collect($permissions)->map(fn (string $permission): Permission => Permission::findOrCreate($permission)));
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function case(User $user): SurgeryCase
    {
        $institution = Institution::create(['name' => 'Institucion documental '.uniqid(), 'active' => true]);
        $doctor = Doctor::create(['name' => 'Dr. Documental '.uniqid(), 'institution_id' => $institution->id, 'active' => true]);
        $patient = Patient::create(['code' => 'PAT-'.uniqid(), 'full_name' => 'Paciente Documental']);
        $surgeryType = SurgeryType::create(['code' => 'DOC-'.uniqid(), 'name' => 'Cirugia documental '.uniqid(), 'active' => true]);

        return SurgeryCase::create([
            'case_code' => 'MR8-DOC-'.uniqid(),
            'status' => 'solicitud_registrada',
            'institution_id' => $institution->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'surgery_type_id' => $surgeryType->id,
            'scheduled_at' => now()->addDay(),
            'priority' => 'normal',
            'procedure_name' => 'Material documental',
            'request_origin' => 'otro',
            'notes' => 'Caso para pruebas documentales.',
            'created_by' => $user->id,
        ]);
    }
}
