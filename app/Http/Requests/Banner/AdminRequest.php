<?php

namespace App\Http\Requests\Banner;

use App\Http\Requests\BaseRequest;
use App\Models\Banner;
use Illuminate\Validation\Rule;

class AdminRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'products'         => 'array',
            'products.*'       => [
                'required',
                'integer',
                Rule::exists('products', 'id')->whereNull('deleted_at')
            ],
            'type'          => Rule::in(Banner::TYPES),
            'url'           => 'string',
            'clickable'     => 'boolean',
            'active'        => 'boolean',
            'images'        => ['array'],
            'images.*'      => ['string'],
            'title'         => ['array'],
            'title.*'       => ['string', 'max:191'],
            'description'   => ['array'],
            'description.*' => ['string'],
            'button_text'   => ['array'],
            'button_text.*' => ['string'],
        ];
    }
}
