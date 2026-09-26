<?php

namespace App\Http\Controllers\Master;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;

class VendorController extends MasterDataController
{
    protected function modelClass(): string
    {
        return Vendor::class;
    }

    protected function key(): string
    {
        return 'vendors';
    }

    protected function title(): string
    {
        return 'Vendor';
    }

    protected function searchColumns(): array
    {
        return ['code', 'name', 'contact_person', 'email'];
    }

    protected function rules(?Model $model): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash', $this->uniqueCode('vendors', $model)],
            'name' => ['required', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:1000'],
            'tax_number' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
