<?php

namespace App\Http\Requests;

use App\Enums\TypeStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Spatie\Enum\Laravel\Rules\EnumRule;

class TransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'wallet_id' => 'required|exists:wallets,id',
            'amount' => 'required|numeric',
            'note' => 'required',
            'category_id' => 'required|exists:categories,id',
            'type' => [
                'required',
                new EnumRule(TypeStatusEnum::class),
            ],
        ];
    }
}
