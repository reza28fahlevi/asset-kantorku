@if ($myAssets->isNotEmpty())
    @php $canAsset = auth()->user()->hasPermission('asset.view'); @endphp
    <x-card title="Aset yang Saya Pegang" icon="devices" :padding="false">
        <table class="table">
            <thead><tr><th>Asset Tag</th><th>Nama</th><th>Kategori</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($myAssets as $asset)
                    <tr>
                        <td class="tag">{{ $asset->asset_tag }}</td>
                        <td>@if ($canAsset)<a href="{{ route('assets.show', $asset) }}" class="hover:underline">{{ $asset->name }}</a>@else{{ $asset->name }}@endif</td>
                        <td>{{ $asset->category?->name ?? '-' }}</td>
                        <td><x-badge :enum="$asset->status" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-card>
@endif
