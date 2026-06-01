<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'           => ['sometimes', 'required', 'string', 'max:255'],
            'advertiser_name' => ['sometimes', 'required', 'string', 'max:255'],
            'agency_name'     => ['nullable', 'string', 'max:255'],
            'customer_name'   => ['sometimes', 'required', 'string', 'max:255'],
            'customer_mobile' => ['sometimes', 'required', 'string', 'max:20'],
            'customer_email'  => ['sometimes', 'required', 'email', 'max:255'],
            'budget'          => ['sometimes', 'required', 'numeric', 'min:1'],
            'end_date'        => ['sometimes', 'required', 'date'],
            'notes'           => ['nullable', 'string', 'max:2000'],
            'status'          => ['sometimes', 'required', Rule::in([
                'Draft', 'Pending', 'Processing', 'Approval Pending',
                'Confirmed', 'Active', 'Completed', 'Cancelled', 'Expired',
            ])],
            'locations'          => ['sometimes', 'array', 'min:1'],
            'locations.*.country_id'  => ['required_with:locations', 'integer', 'exists:countries,id'],
            'locations.*.state_id'    => ['required_with:locations', 'integer', 'exists:states,id'],
            'locations.*.district_id' => ['required_with:locations', 'integer', 'exists:districts,id'],
            'locations.*.city_id'     => ['required_with:locations', 'integer', 'exists:cities,id'],
            'locations.*.area_id'     => ['nullable', 'integer', 'exists:areas,id'],
        ];
    }
}
