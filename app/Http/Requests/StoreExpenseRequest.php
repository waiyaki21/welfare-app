<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'financial_year_id' => 'required|exists:financial_years,id',
            'month'             => 'required|integer|min:1|max:12',
            'category'          => 'required|string|max:50',
            'amount'            => 'required|numeric|min:0.01',
            'notes'             => 'nullable|string|max:500',
        ];
    }
}
