<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'member_id'        => 'required|exists:members,id',
            'financial_year_id'=> 'required|exists:financial_years,id',
            'month'            => 'required|integer|min:1|max:12',
            'amount'           => 'required|numeric|min:1',
            'payment_type'     => 'required|in:contribution,arrears,lump_sum',
            'notes'            => 'nullable|string|max:500',
        ];
    }
}
