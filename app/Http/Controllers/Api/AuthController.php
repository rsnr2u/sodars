<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use RespondsWithApi;

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status !== 'Active') {
            return $this->failure('This account is not active.', [], 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $user->tokens()->where('name', $credentials['device_name'] ?? 'api')->delete();
        $token = $user->createToken($credentials['device_name'] ?? 'api')->plainTextToken;

        $user->load('roles.permissions');

        return $this->success([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'status' => $user->status,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            ],
        ], 'Logged in successfully.');
    }

    public function profile(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles.permissions');

        return $this->success([
            'user' => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'status' => $user->status,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            ],
        ], 'Profile fetched successfully.');
    }

    public function changePassword(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($payload['current_password'], $request->user()->password)) {
            return $this->failure('Current password is incorrect.', [
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $request->user()->forceFill([
            'password' => Hash::make($payload['password']),
        ])->save();

        return $this->success(message: 'Password changed successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->success(message: 'Logged out successfully.');
    }
}
