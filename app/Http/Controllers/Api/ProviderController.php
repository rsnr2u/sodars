<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\ProviderBankAccount;
use App\Models\ProviderDocument;
use App\Models\ProviderStaff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProviderController extends Controller
{
    use RespondsWithApi;

    public function register(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:provider_staff,email', 'max:255'],
            'mobile' => ['required', 'string', 'max:20'],
            'gst_number' => ['required', 'string', 'max:100'],
            'pan_number' => ['required', 'string', 'max:100'],
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'district_id' => ['required', 'integer', 'exists:districts,id'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'address' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        return DB::transaction(function () use ($payload) {
            $yearMonthDay = now()->format('Ymd');
            $count = Provider::whereDate('created_at', now()->toDateString())->count() + 1;
            $providerCode = sprintf('PROV-%s-%04d', $yearMonthDay, $count);

            $provider = Provider::create([
                'provider_code' => $providerCode,
                'company_name' => $payload['company_name'],
                'owner_name' => $payload['owner_name'],
                'email' => $payload['email'],
                'mobile' => $payload['mobile'],
                'gst_number' => $payload['gst_number'],
                'pan_number' => $payload['pan_number'],
                'country_id' => $payload['country_id'],
                'state_id' => $payload['state_id'],
                'district_id' => $payload['district_id'],
                'city_id' => $payload['city_id'],
                'address' => $payload['address'],
                'marketplace_enabled' => false,
                'status' => 'Pending',
            ]);

            $staff = ProviderStaff::create([
                'provider_id' => $provider->id,
                'name' => $payload['owner_name'],
                'email' => $payload['email'],
                'mobile' => $payload['mobile'],
                'role' => 'Owner',
                'password' => Hash::make($payload['password']),
                'status' => 'Active',
            ]);

            return $this->success([
                'provider' => $provider,
                'staff' => [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'email' => $staff->email,
                    'role' => $staff->role,
                ],
            ], 'Provider registered successfully.', 211); // Standard created status
        });
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var ProviderStaff $staff */
        $staff = $request->user();
        $provider = $staff->provider;

        if (! $provider) {
            return $this->failure('Provider not found for user.', [], 404);
        }

        $payload = $request->validate([
            'company_name' => ['sometimes', 'string', 'max:255'],
            'owner_name' => ['sometimes', 'string', 'max:255'],
            'mobile' => ['sometimes', 'string', 'max:20'],
            'address' => ['sometimes', 'string'],
            'logo' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $provider->update($payload);

        return $this->success(['provider' => $provider], 'Provider profile updated successfully.');
    }

    public function uploadDocument(Request $request): JsonResponse
    {
        /** @var ProviderStaff $staff */
        $staff = $request->user();
        $provider = $staff->provider;

        if (! $provider) {
            return $this->failure('Provider not found for user.', [], 404);
        }

        $payload = $request->validate([
            'document_type' => ['required', 'string', 'max:100'],
            'document_file' => ['required', 'file', 'max:10240'], // max 10MB
        ]);

        $path = $request->file('document_file')->store('kyc', 'local');

        $document = ProviderDocument::create([
            'provider_id' => $provider->id,
            'document_type' => $payload['document_type'],
            'document_file' => $path,
            'verification_status' => 'Pending',
        ]);

        return $this->success(['document' => $document], 'KYC Document uploaded successfully.');
    }

    public function getDocuments(Request $request): JsonResponse
    {
        /** @var ProviderStaff $staff */
        $staff = $request->user();
        return $this->success([
            'documents' => ProviderDocument::where('provider_id', $staff->provider_id)->get(),
        ], 'KYC Documents fetched successfully.');
    }

    public function getBankAccounts(Request $request): JsonResponse
    {
        /** @var ProviderStaff $staff */
        $staff = $request->user();
        return $this->success([
            'bank_accounts' => ProviderBankAccount::where('provider_id', $staff->provider_id)->get(),
        ], 'Bank accounts fetched successfully.');
    }

    public function storeBankAccount(Request $request): JsonResponse
    {
        /** @var ProviderStaff $staff */
        $staff = $request->user();
        $provider = $staff->provider;

        $payload = $request->validate([
            'bank_name' => ['required', 'string', 'max:255'],
            'account_holder_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:100'],
            'ifsc_code' => ['required', 'string', 'max:50'],
            'upi_id' => ['nullable', 'string', 'max:100'],
            'is_primary' => ['boolean'],
        ]);

        return DB::transaction(function () use ($provider, $payload) {
            $isPrimary = $payload['is_primary'] ?? false;

            if ($isPrimary) {
                ProviderBankAccount::where('provider_id', $provider->id)->update(['is_primary' => false]);
            }

            $bankAccount = ProviderBankAccount::create(array_merge($payload, [
                'provider_id' => $provider->id,
                'is_primary' => $isPrimary,
            ]));

            return $this->success(['bank_account' => $bankAccount], 'Bank account added successfully.');
        });
    }

    public function updateBankAccount(Request $request, int $id): JsonResponse
    {
        /** @var ProviderStaff $staff */
        $staff = $request->user();
        $provider = $staff->provider;

        $bankAccount = ProviderBankAccount::where('provider_id', $provider->id)->where('id', $id)->firstOrFail();

        $payload = $request->validate([
            'bank_name' => ['sometimes', 'string', 'max:255'],
            'account_holder_name' => ['sometimes', 'string', 'max:255'],
            'account_number' => ['sometimes', 'string', 'max:100'],
            'ifsc_code' => ['sometimes', 'string', 'max:50'],
            'upi_id' => ['nullable', 'string', 'max:100'],
            'is_primary' => ['boolean'],
        ]);

        return DB::transaction(function () use ($provider, $bankAccount, $payload) {
            if (isset($payload['is_primary']) && $payload['is_primary']) {
                ProviderBankAccount::where('provider_id', $provider->id)->update(['is_primary' => false]);
            }

            $bankAccount->update($payload);

            return $this->success(['bank_account' => $bankAccount], 'Bank account updated successfully.');
        });
    }

    public function deleteBankAccount(Request $request, int $id): JsonResponse
    {
        /** @var ProviderStaff $staff */
        $staff = $request->user();
        $bankAccount = ProviderBankAccount::where('provider_id', $staff->provider_id)->where('id', $id)->firstOrFail();
        $bankAccount->delete();

        return $this->success(message: 'Bank account deleted successfully.');
    }

    public function getStaff(Request $request): JsonResponse
    {
        /** @var ProviderStaff $staff */
        $staff = $request->user();
        return $this->success([
            'staff' => ProviderStaff::where('provider_id', $staff->provider_id)->get(),
        ], 'Provider staff fetched successfully.');
    }

    public function storeStaff(Request $request): JsonResponse
    {
        /** @var ProviderStaff $staff */
        $staff = $request->user();
        $provider = $staff->provider;

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:provider_staff,email', 'max:255'],
            'mobile' => ['required', 'string', 'max:20'],
            'role' => ['required', 'string', Rule::in(['Owner', 'Manager', 'Staff'])],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $newStaff = ProviderStaff::create([
            'provider_id' => $provider->id,
            'name' => $payload['name'],
            'email' => $payload['email'],
            'mobile' => $payload['mobile'],
            'role' => $payload['role'],
            'password' => Hash::make($payload['password']),
            'status' => 'Active',
        ]);

        return $this->success(['staff' => $newStaff], 'Provider staff account created successfully.');
    }

    public function updateStaff(Request $request, int $id): JsonResponse
    {
        /** @var ProviderStaff $staff */
        $staff = $request->user();
        $targetStaff = ProviderStaff::where('provider_id', $staff->provider_id)->where('id', $id)->firstOrFail();

        $payload = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'mobile' => ['sometimes', 'string', 'max:20'],
            'role' => ['sometimes', 'string', Rule::in(['Owner', 'Manager', 'Staff'])],
            'status' => ['sometimes', Rule::in(['Active', 'Inactive'])],
        ]);

        $targetStaff->update($payload);

        return $this->success(['staff' => $targetStaff], 'Provider staff updated successfully.');
    }

    // ==========================================
    // ADMIN ONLY ENDPOINTS
    // ==========================================

    private function ensureAdmin(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user instanceof \App\Models\User && $user->hasAnyRole(['Super Admin', 'Branch Manager']),
            403,
            'This action is unauthorized for your role.'
        );
    }

    public function index(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $query = Provider::query();

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return $this->success([
            'providers' => $query->orderBy('company_name')->paginate($request->integer('per_page', 25)),
        ], 'Providers fetched successfully.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);

        $provider = Provider::with(['staff', 'documents', 'bankAccounts'])->findOrFail($id);

        return $this->success(['provider' => $provider], 'Provider fetched successfully.');
    }

    public function verifyStatus(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);

        $provider = Provider::findOrFail($id);

        $payload = $request->validate([
            'status' => ['required', Rule::in(['Approved', 'Rejected', 'Suspended', 'Inactive'])],
        ]);

        $provider->update([
            'status' => $payload['status'],
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return $this->success(['provider' => $provider], "Provider status updated to '{$payload['status']}' successfully.");
    }

    public function verifyDocument(Request $request, int $id, int $docId): JsonResponse
    {
        $this->ensureAdmin($request);

        $document = ProviderDocument::where('provider_id', $id)->where('id', $docId)->firstOrFail();

        $payload = $request->validate([
            'status' => ['required', Rule::in(['Approved', 'Rejected'])],
        ]);

        $document->update([
            'verification_status' => $payload['status'],
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        return $this->success(['document' => $document], "Document verification status updated to '{$payload['status']}' successfully.");
    }

    public function toggleMarketplace(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);

        $provider = Provider::findOrFail($id);

        $payload = $request->validate([
            'marketplace_enabled' => ['required', 'boolean'],
        ]);

        $provider->update([
            'marketplace_enabled' => $payload['marketplace_enabled'],
        ]);

        $state = $payload['marketplace_enabled'] ? 'enabled' : 'disabled';
        return $this->success(['provider' => $provider], "Provider marketplace listing successfully {$state}.");
    }
}
