<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFranchiseStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $staffId = $this->route('staffId') ?? $this->route('id');

        return [
            'name'     => ['sometimes', 'required', 'string', 'max:100'],
            'email'    => ['sometimes', 'required', 'email', Rule::unique('franchise_staff', 'email')->ignore($staffId)],
            'mobile'   => ['sometimes', 'required', 'string', 'max:20'],
            'password' => ['sometimes', 'required', 'string', 'min:8', 'confirmed'],
            'status'   => ['sometimes', 'in:Active,Inactive'],
        ];
    }
}
