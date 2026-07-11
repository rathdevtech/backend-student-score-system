<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index()
    {
        return response()->json(Role::with('permissions')->get());
    }

    public function store(Request $request)
    {
        $fields = $request->validate([
            'name' => 'required|string|unique:roles,name|max:255',
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,slug',
        ]);

        $role = Role::create([
            'name' => strtolower($fields['name']),
            'description' => $fields['description'] ?? null,
            'is_system' => false, // User created roles are never system roles
        ]);

        if (isset($fields['permissions'])) {
            $permissionIds = Permission::whereIn('slug', $fields['permissions'])->pluck('id');
            $role->permissions()->sync($permissionIds);
        }

        return response()->json([
            'message' => 'Role created successfully.',
            'role' => $role->load('permissions')
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $role = Role::find($id);
        if (!$role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        $fields = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($role->id)
            ],
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,slug',
        ]);

        // Don't allow changing name of system roles
        if ($role->is_system && isset($fields['name']) && strtolower($fields['name']) !== $role->name) {
            return response()->json(['message' => 'Cannot change the name of a system role.'], 400);
        }

        if (isset($fields['name'])) {
            $role->name = strtolower($fields['name']);
        }

        if (array_key_exists('description', $fields)) {
            $role->description = $fields['description'];
        }

        $role->save();

        if (isset($fields['permissions'])) {
            $permissionIds = Permission::whereIn('slug', $fields['permissions'])->pluck('id');
            $role->permissions()->sync($permissionIds);
        }

        return response()->json([
            'message' => 'Role updated successfully.',
            'role' => $role->load('permissions')
        ]);
    }

    public function destroy($id)
    {
        $role = Role::find($id);
        if (!$role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        if ($role->is_system) {
            return response()->json(['message' => 'System roles cannot be deleted.'], 400);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted successfully.']);
    }

    public function permissions()
    {
        return response()->json(Permission::all());
    }
}
