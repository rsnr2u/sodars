<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'              => ['required', 'string', 'max:255'],
            'advertiser_name'    => ['required', 'string', 'max:255'],
            'agency_name'        => ['nullable', 'string', 'max:255'],
            'customer_name'      => ['required', 'string', 'max:255'],
            'customer_mobile'    => ['required', 'string', 'max:20'],
            'customer_email'     => ['required', 'email', 'max:255'],
            'budget'             => ['required', 'numeric', 'min:1'],
            'start_date'         => ['required', 'date', 'after_or_equal:today'],
            'end_date'           => ['required', 'date', 'after_or_equal:start_date'],
            'notes'              => ['nullable', 'string', 'max:2000'],
            'locations'          => ['required', 'array', 'min:1'],
            'locations.*.country_id'  => ['required', 'integer', 'exists:countries,id'],
            'locations.*.state_id'    => ['required', 'integer', 'exists:states,id'],
            'locations.*.district_id' => ['required', 'integer', 'exists:districts,id'],
            'locations.*.city_id'     => ['required', 'integer', 'exists:cities,id'],
            'locations.*.area_id'     => ['nullable', 'integer', 'exists:areas,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'locations.required'          => 'At least one target location is required.',
            'locations.min'               => 'At least one target location is required.',
            'start_date.after_or_equal'   => 'Campaign start date cannot be in the past.',
        ];
    }
}
