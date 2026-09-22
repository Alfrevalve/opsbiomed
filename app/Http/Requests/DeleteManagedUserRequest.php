<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteManagedUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Administrador') ?? false;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'current_password'],
            'confirmation' => ['required', 'in:ELIMINAR'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'Ingresa tu password actual para confirmar.',
            'password.current_password' => 'El password actual no es correcto.',
            'confirmation.required' => 'Escribe ELIMINAR para confirmar la baja.',
            'confirmation.in' => 'La confirmacion debe ser exactamente ELIMINAR.',
        ];
    }
}
