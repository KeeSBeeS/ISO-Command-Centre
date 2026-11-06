<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Support\InstallsIsoSchema;
use Tests\TestCase;

/**
 * Opens every page a signed-in person can reach, the way the live site is
 * set up (installer plus every /updates/vX-Y-Z step), and fails on any error.
 * It also checks each page uses the shared design-system stylesheet.
 */
class EveryPageTest extends TestCase
{
    use InstallsIsoSchema;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->useStoragePath(sys_get_temp_dir().'/iso-every-page-'.bin2hex(random_bytes(4)));
        foreach (['app', 'framework/cache', 'framework/sessions', 'framework/views', 'logs'] as $dir) {
            mkdir(storage_path($dir), 0755, true);
        }
        $this->installIsoSchema();
        $this->admin = $this->userWithRole('system-administrator');

        // Bring the database up to date the way older sites did, one update page at a time.
        foreach (Route::getRoutes() as $route) {
            if (in_array('POST', $route->methods(), true) && preg_match('#^updates/v[0-9-]+$#', $route->uri())) {
                $this->actingAs($this->admin)->post('/'.$route->uri());
            }
        }
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg(storage_path()));
        parent::tearDown();
    }

    /**
     * Records the parameterised pages can open.
     *
     * @return array<string, int|string>
     */
    private function sampleRecords(): array
    {
        $ids = [
            'employee' => $this->admin->id,
            'role' => Role::value('id'),
            'department' => Department::create(['name' => 'Workshop', 'slug' => 'workshop', 'is_active' => true])->id,
        ];
        if (Schema::hasTable('vehicles')) {
            $ids['vehicle'] = DB::table('vehicles')->insertGetId(array_filter([
                'make' => 'Toyota', 'model' => 'Hilux', 'odo' => 120000, 'status' => 'active',
                'registration_number' => Schema::hasColumn('vehicles', 'registration_number') ? 'CA 123-456' : null,
                'created_at' => now(), 'updated_at' => now(),
            ], fn ($value) => $value !== null));
        }
        if (Schema::hasTable('leave_types')) {
            $ids['leaveType'] = DB::table('leave_types')->value('id');
        }
        if (Schema::hasTable('customers')) {
            $ids['customer'] = DB::table('customers')->insertGetId([
                'company_name' => 'Acme Mining', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return array_filter($ids);
    }

    public function test_every_page_opens_without_errors_in_the_new_design(): void
    {
        $ids = $this->sampleRecords();
        $skip = '#(\{key\}|\{token\}|\{path\}|download|^up$|^_|^install|^login$|sanctum|livewire)#';
        $opened = [];
        $failures = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! in_array('GET', $route->methods(), true) || preg_match($skip, $uri)) {
                continue;
            }
            $path = preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => (string) ($ids[$m[1]] ?? '__missing__'), $uri);
            if (str_contains($path, '__missing__')) {
                continue;
            }

            $response = $this->actingAs($this->admin)->get('/'.ltrim($path, '/'));
            $opened[] = $path;
            if ($response->getStatusCode() >= 500) {
                $failures[] = "/$path returned {$response->getStatusCode()}: ".mb_substr(strip_tags((string) $response->getContent()), 0, 300);
            } elseif ($response->getStatusCode() === 200 && str_contains((string) $response->headers->get('Content-Type'), 'html')
                && ! str_contains((string) $response->getContent(), 'css/iso.css') && ! str_contains($path, 'finish')) {
                $failures[] = "/$path does not use the design-system stylesheet.";
            }
        }

        $this->assertGreaterThan(60, count($opened), 'Expected to open most pages.');
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_an_employee_sees_only_their_menu_and_a_working_dashboard(): void
    {
        $employee = $this->userWithRole('employee');

        $page = $this->actingAs($employee)->get('/')->assertOk();

        $page->assertSee('Dashboard')->assertSee('My profile')
            ->assertDontSee('Permissions')->assertDontSee('System settings')->assertDontSee('Import attendance');
    }

    public function test_the_dashboard_shows_the_main_actions_and_what_needs_attention(): void
    {
        $page = $this->actingAs($this->admin)->get('/')->assertOk();

        $page->assertSee('Welcome back')->assertSee('Customise');
        if (Schema::hasTable('attendance_days')) {
            $page->assertSee('Import attendance');
        }
    }
}
