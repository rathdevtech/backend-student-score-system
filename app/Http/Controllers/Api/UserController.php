<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'admin',
        ];
    }

    public function index()
    {
        return response()->json(User::all());
    }

    public function store(Request $request)
    {
        $fields = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'required|string|in:admin,teacher',
        ]);

        $user = User::create([
            'name' => $fields['name'],
            'email' => $fields['email'],
            'password' => Hash::make($fields['password']),
            'role' => $fields['role'],
        ]);

        return response()->json([
            'message' => 'User created successfully.',
            'user' => $user
        ], 201);
    }

    public function show($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        return response()->json($user);
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $fields = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id)
            ],
            'password' => 'nullable|string|min:6',
            'role' => 'sometimes|required|string|in:admin,teacher',
        ]);

        if (isset($fields['name'])) {
            $user->name = $fields['name'];
        }

        if (isset($fields['email'])) {
            $user->email = $fields['email'];
        }

        if (isset($fields['role'])) {
            // Prevent the active admin from accidentally demoting themselves
            if ($user->id === $request->user()->id && $fields['role'] !== 'admin') {
                return response()->json(['message' => 'You cannot change your own administrator role.'], 400);
            }
            $user->role = $fields['role'];
        }

        if (!empty($fields['password'])) {
            $user->password = Hash::make($fields['password']);
        }

        $user->save();

        return response()->json([
            'message' => 'User updated successfully.',
            'user' => $user
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Prevent self deletion
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own active administrator account.'], 400);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }
}
