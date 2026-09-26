<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Support\Like;

/**
 * CRUD generik untuk master referensi (departemen, lokasi, kategori, vendor).
 * Master yang sudah dipakai transaksi tidak dihapus — cukup dinonaktifkan.
 */
abstract class MasterDataController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    /** Kunci view & route, mis. "departments". */
    abstract protected function key(): string;

    abstract protected function title(): string;

    abstract protected function rules(?Model $model): array;

    protected function searchColumns(): array
    {
        return ['code', 'name'];
    }

    /** Data tambahan untuk form (dropdown, dsb). */
    protected function formData(?Model $model): array
    {
        return [];
    }

    protected function withRelations(): array
    {
        return [];
    }

    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q'));
        $items = $this->modelClass()::query()
            ->with($this->withRelations())
            ->when($term !== '', function ($query) use ($term) {
                $like = Like::contains($term);
                $query->where(function ($q) use ($like) {
                    foreach ($this->searchColumns() as $column) {
                        $q->orWhere($column, 'ilike', $like);
                    }
                });
            })
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        return view('masters.index', [
            'items' => $items,
            'key' => $this->key(),
            'title' => $this->title(),
        ]);
    }

    public function create(): View
    {
        return $this->form(null);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->prepare($request->validate($this->rules(null)), $request);
        $this->modelClass()::create($data);

        return redirect()->route("masters.{$this->key()}.index")->with('success', "{$this->title()} berhasil ditambahkan.");
    }

    public function edit(string $id): View
    {
        return $this->form($this->modelClass()::findOrFail($id));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $model = $this->modelClass()::findOrFail($id);
        $model->update($this->prepare($request->validate($this->rules($model)), $request));

        return redirect()->route("masters.{$this->key()}.index")->with('success', "{$this->title()} berhasil diperbarui.");
    }

    public function destroy(string $id): RedirectResponse
    {
        $model = $this->modelClass()::findOrFail($id);

        if ($model->isInUse()) {
            return back()->with('error', "{$this->title()} sudah dipakai pada data/transaksi sehingga tidak dapat dihapus. Nonaktifkan saja.");
        }

        $model->delete();

        return back()->with('success', "{$this->title()} berhasil dihapus.");
    }

    protected function form(?Model $model): View
    {
        return view('masters.form', array_merge([
            'model' => $model,
            'key' => $this->key(),
            'title' => $this->title(),
        ], $this->formData($model)));
    }

    /** Normalisasi input (checkbox boolean, kode kapital). */
    protected function prepare(array $data, Request $request): array
    {
        $data['is_active'] = $request->boolean('is_active');
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        return $data;
    }

    /** Rule unik kode yang mengabaikan record soft-deleted. */
    protected function uniqueCode(string $table, ?Model $model): string
    {
        return "unique:{$table},code,".($model?->getKey() ?? 'NULL').',id,deleted_at,NULL';
    }
}
