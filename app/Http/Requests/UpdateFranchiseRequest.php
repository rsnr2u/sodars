<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFranchiseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $franchiseId = $this->route('franchise') ?? $this->route('id');

        return [
            'name'            => ['sometimes', 'required', 'string', 'max:120'],
            'code'            => ['sometimes', 'required', 'string', 'max:30', Rule::unique('franchises', 'code')->ignore($franchiseId)],
            'commission_rate' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'status'          => ['sometimes', 'required', 'in:Active,Inactive,Suspended'],
        ];
    }
}
