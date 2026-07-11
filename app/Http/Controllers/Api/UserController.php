<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{

    public function index()
    {
        return response()->json(User::all());
    }

    public function store(Request $request)
    {
        $fields = $request->validate([
            'name' => 'required|string|max:255',
            'name_kh' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'required|string|exists:roles,name',
            'student_id' => 'nullable|exists:students,id',
            'avatar' => 'nullable|file|max:10240',
        ]);

        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            try {
                $path = $request->file('avatar')->store('avatars', 'public');
                $avatarPath = '/storage/' . $path;
            } catch (\Exception $e) {
                // Skip avatar upload if it fails (e.g., due to filesystem limitations)
                // Continue with user creation
            }
        }

        $user = User::create([
            'name' => $fields['name'],
            'name_kh' => $fields['name_kh'] ?? null,
            'email' => $fields['email'],
            'password' => Hash::make($fields['password']),
            'role' => $fields['role'],
            'avatar' => $avatarPath,
            'is_active' => true,
        ]);

        if (!empty($fields['student_id'])) {
            \App\Models\Student::where('user_id', $user->id)->update(['user_id' => null]);
            \App\Models\Student::where('id', $fields['student_id'])->update(['user_id' => $user->id]);
        }

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
            'name'      => 'sometimes|required|string|max:255',
            'name_kh'   => 'nullable|string|max:255',
            'email'     => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id)
            ],
            'password'  => 'nullable|string|min:6',
            'role'      => 'sometimes|required|string|exists:roles,name',
            'student_id' => 'nullable|exists:students,id',
            'is_active' => 'sometimes|boolean',
            'avatar'    => 'nullable|file|max:10240',
        ]);

        if ($request->hasFile('avatar')) {
            try {
                // Delete old avatar if exists
                if ($user->avatar) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete(str_replace('/storage/', '', $user->avatar));
                }

                $path = $request->file('avatar')->store('avatars', 'public');
                $user->avatar = '/storage/' . $path;
            } catch (\Exception $e) {
                // Skip avatar upload if it fails (e.g., due to filesystem limitations)
                // Continue with other updates
            }
        }

        if (isset($fields['name'])) {
            $user->name = $fields['name'];
        }

        if (array_key_exists('name_kh', $fields)) {
            $user->name_kh = $fields['name_kh'];
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

        if (array_key_exists('student_id', $fields)) {
            \App\Models\Student::where('user_id', $user->id)->update(['user_id' => null]);
            if (!empty($fields['student_id'])) {
                \App\Models\Student::where('id', $fields['student_id'])->update(['user_id' => $user->id]);
            }
        }

        if (isset($fields['is_active'])) {
            // Prevent admin from deactivating their own account
            if ($user->id === $request->user()->id && !$fields['is_active']) {
                return response()->json(['message' => 'You cannot deactivate your own account.'], 400);
            }
            $user->is_active = $fields['is_active'];
        }

        if (!empty($fields['password'])) {
            $user->password = Hash::make($fields['password']);
        }

        $user->save();

        return response()->json([
            'message' => 'User updated successfully.',
            'user'    => $user
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
