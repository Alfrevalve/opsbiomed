<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelCaseWithReservationsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('reservations.release') ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'idempotency_key' => ['required', 'uuid'],
            'confirmation' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Indique el motivo de la cancelacion.',
            'reason.min' => 'El motivo debe tener al menos 5 caracteres.',
            'reason.max' => 'El motivo no puede exceder 2000 caracteres.',
            'idempotency_key.required' => 'Actualice la pantalla e intente nuevamente.',
            'idempotency_key.uuid' => 'La solicitud de cancelacion no es valida.',
            'confirmation.accepted' => 'Confirme que entiende que el caso y las reservas elegibles se cancelaran.',
        ];
    }
}
