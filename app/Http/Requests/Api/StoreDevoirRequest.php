<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreDevoirRequest extends FormRequest
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
            'matiere' => 'required|string',
            'type' => 'required|in:maison,classe,exercice,recherche,revision,autre',
            'titre' => 'required|string',
            'description' => 'required|string',
            'date_remise' => 'nullable|date',
            'date_realisation' => 'nullable|date',
            'cahier_texte_id' => 'nullable|integer|exists:cahier_textes,id',
            'eleves' => 'nullable|array',
            'eleves.*' => 'integer|exists:eleves,id',
        ];
    }
}
