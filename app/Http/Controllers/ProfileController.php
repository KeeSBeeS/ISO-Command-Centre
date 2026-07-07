<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $relations = ['profile', 'departments', 'roles.permissions'];
        if (Schema::hasTable('permission_user')) {
            $relations[] = 'directPermissions';
        }

        if (Schema::hasTable('employee_documents')) {
            $relations[] = 'documents.uploader';
        }
        if (Schema::hasTable('vehicle_assignments')) {
            $relations[] = 'currentVehicleAssignments.vehicle';
        }

        $user->load($relations);

        return view('profile.show', ['user' => $user]);
    }
}
