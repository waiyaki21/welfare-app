<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'phone'       => 'nullable|string|max:20',
            'joined_year' => 'nullable|integer|min:2000|max:2100',
            'is_active'   => 'nullable|boolean',
            'notes'       => 'nullable|string|max:1000',
        ];
    }
}
