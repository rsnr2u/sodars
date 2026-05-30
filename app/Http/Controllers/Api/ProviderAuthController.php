<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Models\ProviderStaff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProviderAuthController extends Controller
{
    use RespondsWithApi;

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $staff = ProviderStaff::where('email', $credentials['email'])->first();

        if (! $staff || ! Hash::check($credentials['password'], $staff->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($staff->status !== 'Active') {
            return $this->failure('This account is not active.', [], 403);
        }

        $staff->load('provider');

        if (! $staff->provider) {
            return $this->failure('No associated provider found.', [], 403);
        }

        if ($staff->provider->status !== 'Approved') {
            return $this->failure(
                "Your provider company account status is '{$staff->provider->status}'. Access is restricted until approved.",
                [],
                403
            );
        }

        $staff->tokens()->where('name', $credentials['device_name'] ?? 'provider-api')->delete();
        $token = $staff->createToken($credentials['device_name'] ?? 'provider-api')->plainTextToken;

        return $this->success([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'staff' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'mobile' => $staff->mobile,
                'role' => $staff->role,
                'status' => $staff->status,
                'provider' => [
                    'id' => $staff->provider->id,
                    'provider_code' => $staff->provider->provider_code,
                    'company_name' => $staff->provider->company_name,
                    'status' => $staff->provider->status,
                ],
            ],
        ], 'Logged in successfully.');
    }

    public function profile(Request $request): JsonResponse
    {
        /** @var ProviderStaff $staff */
        $staff = $request->user();
        $staff->load('provider');

        return $this->success([
            'staff' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'mobile' => $staff->mobile,
                'role' => $staff->role,
                'status' => $staff->status,
                'provider' => $staff->provider,
            ],
        ], 'Profile fetched successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->success(message: 'Logged out successfully.');
    }
}
