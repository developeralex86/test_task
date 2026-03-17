<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AssignLeadRequest extends FormRequest {

    public function authorize(): bool {
        return $this->user() !== null;
    }

    public function rules(): array {
        return [
            // Checks the user actually exists in the DB
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
