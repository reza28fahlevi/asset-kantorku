<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Smoke test: semua halaman GET dapat dirender (tanpa error 500) untuk setiap role demo.
 * Halaman tanpa izin harus 403, bukan error.
 */
class PageSmokeTest extends TestCase
{
    use DatabaseTransactions;

    public const PAGES = [
        '/', '/profile', '/notifications',
        '/assets', '/assets/create', '/assets/search?q=a', '/assets/search?scope=disposable',
        '/approvals', '/approvals?tab=history',
        '/procurements', '/procurements?status=DRAFT&q=x&from=2026-01-01&to=2026-12-31', '/procurements/create',
        '/assignments', '/assignments?status=APPROVED', '/assignments/active', '/assignments/create',
        '/loans', '/loans/active', '/loans/active?filter=overdue', '/loans/create',
        '/disposals', '/disposals/create',
        '/reports',
        '/masters/employees', '/masters/employees/create',
        '/masters/departments', '/masters/departments/create',
        '/masters/locations', '/masters/locations/create',
        '/masters/categories', '/masters/categories/create',
        '/masters/vendors', '/masters/vendors/create',
        '/admin/users', '/admin/users/create', '/admin/roles', '/admin/roles/create',
        '/admin/access-matrix', '/admin/settings', '/admin/audit-logs',
        '/dashboard/widgets/kpi', '/dashboard/widgets/queue', '/dashboard/widgets/overdue',
        '/dashboard/widgets/categories', '/dashboard/widgets/my-assets', '/dashboard/widgets/approvals',
        '/dashboard/widgets/lifecycle', '/dashboard/widgets/activity',
    ];

    public static function users(): array
    {
        return collect(['direktur', 'auditor', 'manager.it', 'admin.aset', 'staff', 'sysadmin'])
            ->mapWithKeys(fn ($u) => [$u => ["{$u}@kantorku.test"]])->all();
    }

    #[DataProvider('users')]
    public function test_all_pages_render_for_role(string $email): void
    {
        $user = User::where('email', $email)->firstOrFail();
        $failures = [];

        foreach (self::PAGES as $url) {
            $response = $this->actingAs($user)->get($url);
            $status = $response->getStatusCode();
            if (! in_array($status, [200, 403, 404], true)) {
                $failures[] = "{$url} => {$status}: ".mb_substr(strip_tags((string) ($response->exception?->getMessage() ?? $response->getContent())), 0, 300);
            }
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_guest_is_redirected_to_login_and_login_page_renders(): void
    {
        $this->get('/assets')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('password');
    }
}
