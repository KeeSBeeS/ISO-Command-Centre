<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\DashboardWidgetPreference;
use App\Models\LeaveRequest;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'attendance_name',
        'email',
        'password',
        'employee_code',
        'phone',
        'position',
        'status',
        'must_change_password',
        'password_changed_at',
        'credentials_emailed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'must_change_password' => 'boolean',
        'password_changed_at' => 'datetime',
        'credentials_emailed_at' => 'datetime',
    ];

    public function profile()
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function departments()
    {
        return $this->belongsToMany(Department::class)->withTimestamps();
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function directPermissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_user')->withTimestamps();
    }

    public function attendanceDays()
    {
        return $this->hasMany(AttendanceDay::class);
    }

    public function attendanceRawRecords()
    {
        return $this->hasMany(AttendanceRawRecord::class);
    }

    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function vehicleAssignments()
    {
        return $this->hasMany(VehicleAssignment::class);
    }

    public function currentVehicleAssignments()
    {
        return $this->hasMany(VehicleAssignment::class)->where('status', 'active');
    }

    public function assignedVehicles()
    {
        return $this->belongsToMany(Vehicle::class, 'vehicle_assignments')
            ->withPivot(['assigned_at', 'unassigned_at', 'status', 'policy_warning', 'notes'])
            ->withTimestamps();
    }

    public function dashboardWidgetPreferences()
    {
        return $this->hasMany(DashboardWidgetPreference::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function hasRole(string $slug): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains('slug', $slug);
        }

        return $this->roles()->where('slug', $slug)->exists();
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->hasRole('system-administrator')) {
            return true;
        }

        if (Schema::hasTable('permission_user') && $this->directPermissions()->where('slug', $permission)->exists()) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permission) {
                $query->where('slug', $permission);
            })
            ->exists();
    }

    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function effectivePermissions()
    {
        if ($this->hasRole('system-administrator') && Schema::hasTable('permissions')) {
            return Permission::orderBy('module')->orderBy('name')->get();
        }

        $rolePermissions = $this->roles()->with('permissions')->get()->flatMap->permissions;
        $directPermissions = Schema::hasTable('permission_user') ? $this->directPermissions()->get() : collect();

        return $rolePermissions
            ->merge($directPermissions)
            ->unique('slug')
            ->sortBy(['module', 'name'])
            ->values();
    }
}
