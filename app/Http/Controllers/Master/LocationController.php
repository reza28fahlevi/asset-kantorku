<?php

namespace App\Http\Controllers\Master;

use App\Models\Location;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class LocationController extends MasterDataController
{
    protected function modelClass(): string
    {
        return Location::class;
    }

    protected function key(): string
    {
        return 'locations';
    }

    protected function title(): string
    {
        return 'Lokasi';
    }

    protected function withRelations(): array
    {
        return ['parent'];
    }

    protected function rules(?Model $model): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash', $this->uniqueCode('locations', $model)],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:1000'],
            'parent_id' => ['nullable', Rule::exists('locations', 'id')->whereNull('deleted_at'), Rule::notIn([$model?->id])],
        ];
    }

    protected function formData(?Model $model): array
    {
        return [
            'parents' => Location::query()->active()->when($model, fn ($q) => $q->whereKeyNot($model->id))->orderBy('name')->get(),
        ];
    }
}
