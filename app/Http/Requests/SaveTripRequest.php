<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'customer_id' => ['required', 'integer', Rule::exists(Customer::class, 'id')],
            'destination' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'participant_count' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'tour_leader_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')
                ->where('role', UserRole::TourLeader->value)->where('is_active', true)],
        ];
    }
}
