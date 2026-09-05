<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreIncidentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'eleve_id' => 'nullable|exists:eleves,id',
            'eleves_ids' => 'nullable|array',
            'eleves_ids.*' => 'exists:eleves,id',
            'enseignant_id' => 'required|exists:enseignants,id',
            'classe_id' => 'nullable|exists:classes,id',
            'type' => 'required|in:retard_repete,absence_injustifiee,indiscipline,violence,insolence,non_respect,devoirs_non_faits,telephone,degradation,perturbation,autre',
            'description' => 'nullable|string|max:500',
            'date' => 'required|date'
        ];
    }
}
