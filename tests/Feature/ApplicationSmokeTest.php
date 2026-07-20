<?php

namespace Tests\Feature;

use App\Models\AttendanceDay;
use App\Models\Customer;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApplicationSmokeTest extends TestCase
{
    /**
     * Update apply routes in release order. Each one builds part of the schema,
     * exactly as a real install would by clicking through the update pages.
     */
    private const UPDATE_APPLY_ROUTES = [
        'updates.v1_1.apply',
        'updates.v1_2.apply',
        'updates.v1_3.apply',
        'updates.v1_4.apply',
        'updates.v1_5.apply',
        'updates.v1_6.apply',
        'updates.v1_7.apply',
        'updates.v2_5.apply',
        'updates.v2_5_2.apply',
        'updates.v2_5_3.apply',
        'updates.v2_6.apply',
        'updates.v2_6_1.apply',
        'updates.v2_6_2.apply',
        'updates.v2_6_3.apply',
        'updates.v2_6_4.apply',
        'updates.v2_6_5.apply',
        'updates.v2_6_6.apply',
        'updates.v2_6_7.apply',
        'updates.v2_6_8.apply',
        'updates.v2_6_9.apply',
        'updates.v2_6_10.apply',
        'updates.v2_6_11.apply',
        'updates.v2_8_6.apply',
        'updates.v2_8_8.apply',
        'updates.v2_9_0.apply',
    ];

    /**
     * Install the application and apply every update, returning the
     * System Administrator account created by the installer.
     */
    private function installApplication(): User
    {
        @unlink(storage_path('app/isoadmin_installed.lock'));

        $install = $this->post('/install', [
            'installer_key' => 'testing-installer-key',
            'name' => 'Smoke Admin',
            'email' => 'smoke-admin@example.com',
            'password' => 'smoke-password-1',
            'password_confirmation' => 'smoke-password-1',
        ]);
        $install->assertRedirect(route('login'));

        $admin = User::where('email', 'smoke-admin@example.com')->firstOrFail();

        $failures = [];
        foreach (self::UPDATE_APPLY_ROUTES as $name) {
            $response = $this->actingAs($admin)->post(route($name));

            if ($response->exception) {
                $failures[$name] = get_class($response->exception) . ': ' . $response->exception->getMessage();
            } elseif ($response->status() >= 400) {
                $failures[$name] = 'HTTP ' . $response->status();
            }
        }

        $this->assertSame([], $failures, 'Update apply steps failed: ' . json_encode($failures, JSON_PRETTY_PRINT));

        return $admin;
    }

    public function test_every_page_renders_after_full_install_and_updates(): void
    {
        $admin = $this->installApplication();

        $vehicle = Vehicle::create([
            'make' => 'Toyota',
            'model' => 'Hilux',
            'registration_number' => 'ABC123GP',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $customer = Customer::create([
            'company_name' => 'Smoke Test Customer',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $leaveType = LeaveType::first() ?? LeaveType::create([
            'name' => 'Annual Leave',
            'slug' => 'annual-leave',
            'is_active' => true,
        ]);

        $leaveRequest = LeaveRequest::create([
            'user_id' => $admin->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'total_days' => 2,
            'status' => 'pending',
        ]);

        $attendanceDay = AttendanceDay::create([
            'user_id' => $admin->id,
            'attendance_date' => now()->toDateString(),
            'start_time' => now()->setTime(7, 0),
            'end_time' => now()->setTime(15, 0),
            'record_count' => 2,
            'work_minutes' => 480,
            'is_late' => true,
            'late_minutes' => 60,
        ]);

        $parameters = [
            '{employee}' => (string) $admin->id,
            '{vehicle}' => (string) $vehicle->id,
            '{customer}' => (string) $customer->id,
            '{leaveRequest}' => (string) $leaveRequest->id,
            '{attendanceDay}' => (string) $attendanceDay->id,
            '{leaveType}' => (string) $leaveType->id,
        ];

        $failures = [];
        foreach (Route::getRoutes() as $route) {
            if (!in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = strtr($route->uri(), $parameters);

            // Cron endpoints require configured keys; document downloads need
            // stored files, which this smoke test does not create.
            if (str_contains($uri, '{')) {
                continue;
            }

            $response = $this->actingAs($admin)->get('/' . ltrim($uri, '/'));

            if ($response->exception) {
                $failures[$uri] = get_class($response->exception) . ': ' . $response->exception->getMessage();
            } elseif ($response->status() >= 400) {
                $failures[$uri] = 'HTTP ' . $response->status();
            }
        }

        $this->assertSame([], $failures, 'Pages failed to render: ' . json_encode($failures, JSON_PRETTY_PRINT));
    }

    public function test_core_write_flows_work(): void
    {
        $admin = $this->installApplication();

        $this->actingAs($admin)->post(route('departments.store'), [
            'name' => 'Operations',
            'description' => 'Smoke test department',
            'is_active' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNotNull(Department::where('name', 'Operations')->first());

        $this->actingAs($admin)->post(route('roles.store'), [
            'name' => 'Smoke Role',
            'level' => 20,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNotNull(Role::where('name', 'Smoke Role')->first());

        $this->actingAs($admin)->post(route('employees.store'), [
            'name' => 'Smoke Employee',
            'email' => 'smoke-employee@example.com',
            'status' => 'active',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNotNull(User::where('email', 'smoke-employee@example.com')->first());

        $this->actingAs($admin)->post(route('vehicles.store'), [
            'make' => 'Ford',
            'model' => 'Ranger',
            'odo' => 120000,
            'status' => 'active',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $vehicle = Vehicle::where('model', 'Ranger')->first();
        $this->assertNotNull($vehicle);

        $this->actingAs($admin)->post(route('customers.store'), [
            'company_name' => 'Smoke Customer Ltd',
            'status' => 'active',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNotNull(Customer::where('company_name', 'Smoke Customer Ltd')->first());

        $leaveType = LeaveType::first() ?? LeaveType::create([
            'name' => 'Annual Leave',
            'slug' => 'annual-leave',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('leave.store'), [
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->addDay()->toDateString(),
            'reason' => 'Smoke test leave',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNotNull(LeaveRequest::where('reason', 'Smoke test leave')->first());

        $employee = User::where('email', 'smoke-employee@example.com')->first();
        $this->actingAs($admin)->post(route('vehicles.assign', $vehicle), [
            'user_id' => $employee->id,
        ])->assertSessionHasNoErrors()->assertRedirect();
    }
}
