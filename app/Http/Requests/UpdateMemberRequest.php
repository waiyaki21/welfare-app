<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $name = trim((string) preg_replace('/\s+/', ' ', (string) $this->input('name', '')));
        $name = $name !== '' ? Str::of($name)->lower()->title()->toString() : $name;
        $phone = preg_replace('/[^0-9+]/', '', (string) $this->input('phone', ''));

        $this->merge([
            'name' => $name,
            'phone' => strlen($phone) >= 9 ? $phone : null,
        ]);
    }

    public function rules(): array
    {
        $memberId = $this->route('member')?->id;

        return [
            'name'        => 'required|string|max:255',
            'phone'       => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('members', 'phone')
                    ->where(fn ($q) => $q->where('name', $this->input('name')))
                    ->ignore($memberId),
            ],
            'joined_year' => 'nullable|integer|min:2000|max:2100',
            'is_active'   => 'nullable|boolean',
            'notes'       => 'nullable|string|max:1000',
        ];
    }
}
