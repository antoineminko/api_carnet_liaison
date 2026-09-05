<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreCahierTexteRequest extends FormRequest
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
            'classe_id' => 'required|integer',
            'enseignant_id' => 'required|integer',
            'titre' => 'required|string|max:255',
            'matiere' => 'required|string',
            'date_cours' => 'required|date',
            'contenu_realise' => 'required|string',
            'resume_cours' => 'nullable|string',
            'exercices_donnes' => 'nullable|string',
        ];
    }
}
