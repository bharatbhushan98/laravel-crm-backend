<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // 🔹 LOGIN user
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Purane tokens delete kar do (security)
        $user->tokens()->delete();

        // Naya token generate karo
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'roles' => $user->getRoleNames(), // 🔥 Added
            'permissions' => $user->getAllPermissions()->pluck('name'), // 🔥 Added
            'token' => $token,
            'message' => 'Login successful'
        ]);
    }

    // 🔹 LOGOUT user
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    // 🔹 AUTHENTICATED USER DETAILS (with roles & permissions)
    public function user(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => $user,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }

    // 🔹 THEME: Get
    public function getTheme(Request $request)
    {
        return response()->json(['theme' => $request->user()->theme]);
    }

    // 🔹 THEME: Update
    public function updateTheme(Request $request)
    {
        $request->validate(['theme' => 'required|in:light,dark']);
        $user = $request->user();
        $user->theme = $request->theme;
        $user->save();

        return response()->json([
            'message' => 'Theme updated successfully',
            'theme' => $user->theme
        ]);
    }
}