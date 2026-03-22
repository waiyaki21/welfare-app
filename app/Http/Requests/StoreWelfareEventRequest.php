<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWelfareEventRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'member_id'         => 'required|exists:members,id',
            'financial_year_id' => 'required|exists:financial_years,id',
            'amount'            => 'required|numeric|min:0.01',
            'reason'            => 'required|in:bereavement,illness,emergency,general',
            'event_date'        => 'nullable|date',
            'notes'             => 'nullable|string|max:1000',
        ];
    }
}
