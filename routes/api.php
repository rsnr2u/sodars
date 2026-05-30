<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\ProviderAuthController;
use App\Http\Controllers\Api\ProviderController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\MarketplaceController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\FinanceController;
use Illuminate\Support\Facades\Route;

// Public health check
Route::get('/health', HealthController::class);

// Public provider registration
Route::post('/providers/register', [ProviderController::class, 'register']);

// User (Admin/Agent) Auth
Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// Provider Staff Auth
Route::prefix('provider/auth')->group(function (): void {
    Route::post('/login', [ProviderAuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/profile', [ProviderAuthController::class, 'profile']);
        Route::post('/logout', [ProviderAuthController::class, 'logout']);
    });
});

// Location master endpoints
Route::prefix('locations')->group(function (): void {
    Route::get('/{resource}', [LocationController::class, 'index'])
        ->whereIn('resource', ['countries', 'states', 'districts', 'cities', 'areas', 'landmarks', 'roads']);

    Route::get('/states-by-country/{parentId}', [LocationController::class, 'children'])
        ->defaults('resource', 'states')
        ->defaults('parentColumn', 'country_id');

    Route::get('/districts-by-state/{parentId}', [LocationController::class, 'children'])
        ->defaults('resource', 'districts')
        ->defaults('parentColumn', 'state_id');

    Route::get('/cities-by-district/{parentId}', [LocationController::class, 'children'])
        ->defaults('resource', 'cities')
        ->defaults('parentColumn', 'district_id');

    Route::get('/areas-by-city/{parentId}', [LocationController::class, 'children'])
        ->defaults('resource', 'areas')
        ->defaults('parentColumn', 'city_id');

    Route::get('/landmarks-by-area/{parentId}', [LocationController::class, 'children'])
        ->defaults('resource', 'landmarks')
        ->defaults('parentColumn', 'area_id');

    Route::get('/roads-by-area/{parentId}', [LocationController::class, 'children'])
        ->defaults('resource', 'roads')
        ->defaults('parentColumn', 'area_id');
});

// User (Admin/Agent/Advertiser) Scoped Portal Endpoints
Route::middleware('auth:sanctum')->group(function (): void {
    // Campaign CRUD
    Route::apiResource('campaigns', CampaignController::class);

    // Booking Hold locks and creation
    Route::post('/bookings/hold', [BookingController::class, 'store']);
    Route::post('/bookings/{id}/artwork', [BookingController::class, 'uploadArtwork']);
    Route::post('/bookings/{id}/artwork/{artId}/approve', [BookingController::class, 'approveArtwork']);
    Route::get('/bookings/{id}/logs', [BookingController::class, 'logs']);

    // CRM Leads
    Route::get('/crm/leads', [LeadController::class, 'index']);
    Route::post('/crm/leads', [LeadController::class, 'store']);
    Route::get('/crm/leads/{id}', [LeadController::class, 'show']);
    Route::put('/crm/leads/{id}', [LeadController::class, 'update']);
    Route::put('/crm/leads/{id}/assign', [LeadController::class, 'assign']);
    Route::post('/crm/leads/{id}/followups', [LeadController::class, 'addFollowup']);

    // Finance & Settlements
    Route::get('/finance/invoices', [FinanceController::class, 'getInvoices']);
    Route::get('/finance/invoices/{id}', [FinanceController::class, 'getInvoice']);
    Route::post('/finance/invoices/{id}/pay', [FinanceController::class, 'processCheckout']);
    Route::get('/finance/payouts', [FinanceController::class, 'getPayouts']);
    Route::get('/finance/commissions', [FinanceController::class, 'getCommissions']);
    Route::post('/bookings/{id}/proofs', [FinanceController::class, 'uploadProof']);
    Route::post('/bookings/{id}/verify-settlements', [FinanceController::class, 'forceReleaseEscrow']);
});

// Provider Scoped Portal Endpoints
Route::prefix('provider')->middleware('auth:sanctum')->group(function (): void {
    Route::put('/profile', [ProviderController::class, 'updateProfile']);
    Route::post('/documents', [ProviderController::class, 'uploadDocument']);
    Route::get('/documents', [ProviderController::class, 'getDocuments']);

    // Bank Accounts
    Route::get('/bank-accounts', [ProviderController::class, 'getBankAccounts']);
    Route::post('/bank-accounts', [ProviderController::class, 'storeBankAccount']);
    Route::put('/bank-accounts/{id}', [ProviderController::class, 'updateBankAccount']);
    Route::delete('/bank-accounts/{id}', [ProviderController::class, 'deleteBankAccount']);

    // Staff
    Route::get('/staff', [ProviderController::class, 'getStaff']);
    Route::post('/staff', [ProviderController::class, 'storeStaff']);
    Route::put('/staff/{id}', [ProviderController::class, 'updateStaff']);

    // Inventory CRUD
    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::post('/inventory', [InventoryController::class, 'store']);
    Route::get('/inventory/{id}', [InventoryController::class, 'show']);
    Route::put('/inventory/{id}', [InventoryController::class, 'update']);
    Route::delete('/inventory/{id}', [InventoryController::class, 'destroy']);

    // Gallery
    Route::post('/inventory/{id}/gallery', [InventoryController::class, 'uploadMedia']);
    Route::delete('/inventory/{id}/gallery/{mediaId}', [InventoryController::class, 'deleteMedia']);
    Route::put('/inventory/{id}/gallery/{mediaId}/primary', [InventoryController::class, 'setPrimaryMedia']);

    // Pricing Rules
    Route::get('/inventory/{id}/pricing', [InventoryController::class, 'getPricing']);
    Route::post('/inventory/{id}/pricing', [InventoryController::class, 'storePricing']);
    Route::delete('/inventory/{id}/pricing/{pricingId}', [InventoryController::class, 'deletePricing']);

    // Maintenance Blocks
    Route::get('/inventory/{id}/maintenance', [InventoryController::class, 'getMaintenance']);
    Route::post('/inventory/{id}/maintenance', [InventoryController::class, 'storeMaintenance']);
    Route::delete('/inventory/{id}/maintenance/{maintenanceId}', [InventoryController::class, 'deleteMaintenance']);

    // Provider booking approvals/rejections
    Route::post('/bookings/{id}/provider-approve', [BookingController::class, 'providerApprove']);
    Route::post('/bookings/{id}/provider-reject', [BookingController::class, 'providerReject']);
    Route::get('/bookings/{id}/logs', [BookingController::class, 'logs']);
});

// Admin Scoped Portal Endpoints
Route::prefix('admin')->middleware('auth:sanctum')->group(function (): void {
    Route::get('/providers', [ProviderController::class, 'index']);
    Route::get('/providers/{id}', [ProviderController::class, 'show']);
    Route::post('/providers/{id}/status', [ProviderController::class, 'verifyStatus']);
    Route::post('/providers/{id}/documents/{docId}/verify', [ProviderController::class, 'verifyDocument']);
    Route::post('/providers/{id}/marketplace-enable', [ProviderController::class, 'toggleMarketplace']);
});

// Public Marketplace
Route::prefix('marketplace')->group(function (): void {
    Route::get('/inventory', [MarketplaceController::class, 'index']);
    Route::get('/inventory/{id}', [MarketplaceController::class, 'show']);
    Route::get('/featured', [MarketplaceController::class, 'featured']);
    Route::get('/providers', [MarketplaceController::class, 'providers']);
    Route::get('/cities', [MarketplaceController::class, 'cities']);
    Route::post('/inquiries', [MarketplaceController::class, 'inquire']);
});
