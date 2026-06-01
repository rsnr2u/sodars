<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Models\FranchiseStaff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class FranchiseAuthController extends Controller
{
    use RespondsWithApi;

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'       => ['required', 'email'],
            'password'    => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $staff = FranchiseStaff::where('email', $credentials['email'])->first();

        if (! $staff || ! Hash::check($credentials['password'], $staff->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($staff->status !== 'Active') {
            return $this->failure('This franchise account is not active.', [], 403);
        }

        $staff->load('franchise');

        if (! $staff->franchise) {
            return $this->failure('No associated franchise found.', [], 403);
        }

        if ($staff->franchise->status !== 'Active') {
            return $this->failure(
                "Your franchise account status is '{$staff->franchise->status}'. Access is restricted until activated.",
                [],
                403
            );
        }

        $staff->tokens()->where('name', $credentials['device_name'] ?? 'franchise-api')->delete();
        $token = $staff->createToken($credentials['device_name'] ?? 'franchise-api')->plainTextToken;

        return $this->success([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'staff'        => [
                'id'        => $staff->id,
                'name'      => $staff->name,
                'email'     => $staff->email,
                'mobile'    => $staff->mobile,
                'status'    => $staff->status,
                'franchise' => [
                    'id'              => $staff->franchise->id,
                    'code'            => $staff->franchise->code,
                    'name'            => $staff->franchise->name,
                    'commission_rate' => $staff->franchise->commission_rate,
                    'status'          => $staff->franchise->status,
                ],
            ],
        ], 'Logged in successfully.');
    }

    public function profile(Request $request): JsonResponse
    {
        /** @var FranchiseStaff $staff */
        $staff = $request->user();
        $staff->load('franchise', 'roles.permissions');

        return $this->success([
            'staff' => [
                'id'          => $staff->id,
                'name'        => $staff->name,
                'email'       => $staff->email,
                'mobile'      => $staff->mobile,
                'status'      => $staff->status,
                'franchise'   => $staff->franchise,
                'roles'       => $staff->roles->map(fn ($r) => [
                    'id'          => $r->id,
                    'name'        => $r->name,
                    'permissions' => $r->permissions->pluck('name'),
                ]),
            ],
        ], 'Profile fetched successfully.');
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password'     => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        /** @var FranchiseStaff $staff */
        $staff = $request->user();

        if (! Hash::check($validated['current_password'], $staff->password)) {
            return $this->failure('Current password is incorrect.', [], 422);
        }

        $staff->update(['password' => $validated['new_password']]);

        return $this->success(message: 'Password changed successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->success(message: 'Logged out successfully.');
    }
}
