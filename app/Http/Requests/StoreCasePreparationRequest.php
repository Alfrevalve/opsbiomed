<?php

namespace App\Http\Requests;

use App\Models\SurgeryCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCasePreparationRequest extends FormRequest
{
    public function prepareForValidation(): void
    {
        $guideNumber = $this->input('guide_number');
        $deliveryEvidenceReference = $this->input('delivery_evidence_reference');
        $materials = is_array($this->input('materials')) ? $this->input('materials') : [];

        $this->merge([
            'institution_confirmed' => $this->boolean('institution_confirmed'),
            'doctor_confirmed' => $this->boolean('doctor_confirmed'),
            'schedule_confirmed' => $this->boolean('schedule_confirmed'),
            'material_confirmed' => $this->boolean('material_confirmed'),
            'documents_confirmed' => $this->boolean('documents_confirmed'),
            'guide_number' => is_string($guideNumber) && trim($guideNumber) !== '' ? trim($guideNumber) : null,
            'delivery_evidence_reference' => is_string($deliveryEvidenceReference) && trim($deliveryEvidenceReference) !== ''
                ? trim($deliveryEvidenceReference)
                : null,
            'notes' => is_string($this->input('notes')) && trim((string) $this->input('notes')) !== ''
                ? trim((string) $this->input('notes'))
                : null,
            'materials' => collect($materials)
                ->map(function (mixed $material): array {
                    $material = is_array($material) ? $material : [];

                    return [
                        ...$material,
                        'verified' => in_array($material['verified'] ?? null, [true, 1, '1', 'true', 'on', 'yes'], true),
                    ];
                })
                ->all(),
        ]);
    }

    public function authorize(): bool
    {
        $case = $this->route('case');

        return $case instanceof SurgeryCase
            && ($this->user()?->can('prepare', $case) ?? false);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'institution_confirmed' => ['accepted'],
            'doctor_confirmed' => ['accepted'],
            'schedule_confirmed' => ['accepted'],
            'material_confirmed' => ['accepted'],
            'documents_confirmed' => ['accepted'],
            'guide_number' => ['nullable', 'string', 'max:100'],
            'delivery_evidence_reference' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'materials' => ['required', 'array', 'min:1'],
            'materials.*.reservation_id' => ['required', 'integer', 'distinct', 'exists:reservations,id'],
            'materials.*.verified' => ['accepted'],
            'materials.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['guide_number', 'delivery_evidence_reference'])) {
                    return;
                }

                if (blank($this->input('guide_number')) && blank($this->input('delivery_evidence_reference'))) {
                    $validator->errors()->add(
                        'delivery_evidence_reference',
                        'Registre el numero de guia o una referencia de evidencia de despacho.',
                    );
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'institution_confirmed.accepted' => 'Confirme la institucion antes de preparar la cirugia.',
            'doctor_confirmed.accepted' => 'Confirme el medico antes de preparar la cirugia.',
            'schedule_confirmed.accepted' => 'Confirme la fecha y hora programada.',
            'material_confirmed.accepted' => 'Confirme la verificacion fisica del material.',
            'documents_confirmed.accepted' => 'Confirme los documentos preoperatorios.',
            'materials.required' => 'Debe registrar el despacho de cada reserva activa.',
            'materials.min' => 'Debe registrar al menos un material despachado.',
            'materials.*.reservation_id.required' => 'Cada despacho debe corresponder a una reserva.',
            'materials.*.reservation_id.exists' => 'Una de las reservas seleccionadas ya no existe.',
            'materials.*.verified.accepted' => 'Confirme la verificacion fisica de cada material.',
            'materials.*.quantity.required' => 'Indique la cantidad fisica despachada.',
            'materials.*.quantity.integer' => 'La cantidad despachada debe ser un numero entero.',
            'materials.*.quantity.min' => 'La cantidad despachada debe ser mayor que cero.',
        ];
    }
}
