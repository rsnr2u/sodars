<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFranchiseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:120'],
            'code'            => ['required', 'string', 'max:30', 'unique:franchises,code'],
            'commission_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'status'          => ['required', 'in:Active,Inactive,Suspended'],
        ];
    }
}
