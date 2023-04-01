<?php

namespace App\Http\Requests;

use App\Enums\WalletTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Spatie\Enum\Laravel\Rules\EnumRule;

class WalletRequest extends FormRequest
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
            'name' => 'required',
            'icon' => 'nullable',
            'description' => 'nullable',
            'balance' => 'required|numeric',
            'type' => [
                'required',
                new EnumRule(WalletTypeEnum::class),
            ],
        ];
    }
}
