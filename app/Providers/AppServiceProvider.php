<?php

namespace App\Providers;

use App\Models\ApprovalStep;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetLoan;
use App\Models\AssetLoanRequest;
use App\Models\AssignmentRequest;
use App\Models\DisposalRequest;
use App\Models\LoanExtensionRequest;
use App\Models\ProcurementReceipt;
use App\Models\ProcurementRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Paginator::useBootstrapFive();

        // Alias stabil untuk kolom polymorphic (attachments, audit_logs, notifications)
        Relation::morphMap([
            'user' => User::class,
            'asset' => Asset::class,
            'procurement_request' => ProcurementRequest::class,
            'procurement_receipt' => ProcurementReceipt::class,
            'assignment_request' => AssignmentRequest::class,
            'asset_assignment' => AssetAssignment::class,
            'asset_loan_request' => AssetLoanRequest::class,
            'asset_loan' => AssetLoan::class,
            'loan_extension_request' => LoanExtensionRequest::class,
            'disposal_request' => DisposalRequest::class,
        ]);

        // RBAC: ability berformat "modul.aksi" tanpa argumen model dicek terhadap permission user.
        // Ability lain (mis. 'view', 'decide') diteruskan ke Policy untuk aturan per objek.
        Gate::before(function (User $user, string $ability, array $arguments) {
            if ($arguments === [] && str_contains($ability, '.')) {
                return $user->hasPermission($ability);
            }

            return null;
        });

        Blade::if('permission', fn (string ...$permissions) => auth()->user()?->hasAnyPermission($permissions) ?? false);

        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            $view->with('pendingApprovalCount', $user?->employee_id
                ? ApprovalStep::query()->where('approver_employee_id', $user->employee_id)->where('status', 'PENDING')->count()
                : 0);
            $view->with('unreadNotifications', $user ? $user->unreadNotifications()->limit(8)->get() : collect());
            $view->with('unreadNotificationCount', $user ? $user->unreadNotifications()->count() : 0);
        });
    }
}
