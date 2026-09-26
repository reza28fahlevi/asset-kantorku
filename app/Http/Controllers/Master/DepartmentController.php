<?php

namespace App\Http\Controllers\Master;

use App\Models\Department;
use Illuminate\Database\Eloquent\Model;

class DepartmentController extends MasterDataController
{
    protected function modelClass(): string
    {
        return Department::class;
    }

    protected function key(): string
    {
        return 'departments';
    }

    protected function title(): string
    {
        return 'Departemen';
    }

    protected function rules(?Model $model): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash', $this->uniqueCode('departments', $model)],
            'name' => ['required', 'string', 'max:150'],
        ];
    }
}
