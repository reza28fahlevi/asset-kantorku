<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetLoan;
use App\Models\ProcurementRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        $byStatus = Asset::query()->select('status', DB::raw('count(*) as total'), DB::raw('coalesce(sum(purchase_cost),0) as value'))
            ->groupBy('status')->get()->keyBy(fn ($r) => $r->status->value);

        $byCategory = Asset::query()
            ->join('asset_categories', 'asset_categories.id', '=', 'assets.asset_category_id')
            ->where('assets.status', '!=', AssetStatus::Disposed->value)
            ->groupBy('asset_categories.name')->orderBy('asset_categories.name')
            ->get(['asset_categories.name', DB::raw('count(*) as total'), DB::raw('coalesce(sum(assets.purchase_cost),0) as value')]);

        $byLocation = Asset::query()
            ->join('locations', 'locations.id', '=', 'assets.location_id')
            ->where('assets.status', '!=', AssetStatus::Disposed->value)
            ->groupBy('locations.name')->orderBy('locations.name')
            ->get(['locations.name', DB::raw('count(*) as total')]);

        $overdueLoans = AssetLoan::query()->active()->where('due_at', '<', now())
            ->with('asset', 'borrower.department')->orderBy('due_at')->get();

        $inRepair = Asset::query()->where('status', AssetStatus::InRepair->value)->with('category', 'location')->get();

        $warranty = Asset::query()->where('status', '!=', AssetStatus::Disposed->value)
            ->whereBetween('warranty_end_date', [today(), today()->addDays(60)])
            ->with('category')->orderBy('warranty_end_date')->get();

        $procurementSummary = ProcurementRequest::query()
            ->select('status', DB::raw('count(*) as total'), DB::raw('coalesce(sum(estimated_total),0) as value'))
            ->groupBy('status')->get();

        return view('reports.index', compact(
            'byStatus', 'byCategory', 'byLocation', 'overdueLoans', 'inRepair', 'warranty', 'procurementSummary',
        ));
    }
}
