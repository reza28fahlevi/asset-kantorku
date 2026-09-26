<?php

namespace App\Http\Controllers\Master;

use App\Models\AssetCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AssetCategoryController extends MasterDataController
{
    protected function modelClass(): string
    {
        return AssetCategory::class;
    }

    protected function key(): string
    {
        return 'categories';
    }

    protected function title(): string
    {
        return 'Kategori Aset';
    }

    protected function rules(?Model $model): array
    {
        return [
            'code' => ['required', 'string', 'max:10', 'alpha_num', $this->uniqueCode('asset_categories', $model)],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'useful_life_months' => ['nullable', 'integer', 'min:1', 'max:600'],
        ];
    }

    protected function prepare(array $data, Request $request): array
    {
        $data = parent::prepare($data, $request);
        $data['requires_serial'] = $request->boolean('requires_serial');

        return $data;
    }
}
