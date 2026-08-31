<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount('users')->orderBy('name')->get();

        return response()->json($roles);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles'],
            'description' => ['nullable', 'string'],
        ]);

        $role = Role::create($validated);

        return response()->json($role, 201);
    }

    public function show(Role $role)
    {
        return response()->json($role->load('users'));
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,' . $role->id],
            'description' => ['nullable', 'string'],
        ]);

        $role->update($validated);

        return response()->json($role);
    }

    public function destroy(Role $role)
    {
        // No permitir eliminar roles con usuarios asignados
        if ($role->users()->count() > 0) {
            return response()->json(['message' => 'No se puede eliminar un rol con usuarios asignados'], 422);
        }

        $role->delete();

        return response()->json(null, 204);
    }
}
