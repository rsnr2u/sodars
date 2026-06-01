<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code', 10);
            $table->string('iso_code', 10)->unique();
            $table->string('currency', 20);
            $table->string('timezone', 100);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
        });

        Schema::create('states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
            $table->index(['country_id', 'status']);
        });

        Schema::create('districts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('state_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
            $table->index(['state_id', 'status']);
        });

        Schema::create('cities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('district_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
            $table->index(['district_id', 'status']);
            $table->index(['latitude', 'longitude']);
        });

        Schema::create('areas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('pincode', 20);
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
            $table->index(['city_id', 'status']);
            $table->index(['latitude', 'longitude']);
        });

        Schema::create('landmarks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 100);
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
            $table->index(['area_id', 'status']);
        });

        Schema::create('roads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('road_type', 100);
            $table->decimal('traffic_score', 10, 2)->default(0);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
            $table->index(['city_id', 'area_id', 'status']);
        });

        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code', 100)->unique();
            $table->foreignId('country_id')->constrained();
            $table->foreignId('state_id')->constrained();
            $table->foreignId('district_id')->constrained();
            $table->foreignId('city_id')->constrained();
            $table->text('address');
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
        });

        Schema::create('providers', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_code', 100)->unique();
            $table->string('company_name');
            $table->string('owner_name');
            $table->string('email');
            $table->string('mobile', 20);
            $table->string('gst_number', 100);
            $table->string('pan_number', 100);
            $table->foreignId('country_id')->constrained();
            $table->foreignId('state_id')->constrained();
            $table->foreignId('district_id')->constrained();
            $table->foreignId('city_id')->constrained();
            $table->text('address');
            $table->string('logo', 500)->nullable();
            $table->boolean('marketplace_enabled')->default(false);
            $table->enum('status', ['Pending', 'Approved', 'Rejected', 'Suspended', 'Inactive'])->default('Pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('provider_staff', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('mobile', 20);
            $table->string('role', 100);
            $table->string('password');
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->rememberToken();
            $table->timestamps();
            $table->unique(['provider_id', 'email']);
        });

        Schema::create('provider_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 100);
            $table->string('document_file', 500);
            $table->enum('verification_status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('provider_bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('account_holder_name');
            $table->string('account_number', 100);
            $table->string('ifsc_code', 50);
            $table->string('upi_id', 100)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('inventory', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('inventory_code', 100)->unique();
            $table->string('title');
            $table->enum('media_type', ['Hoarding', 'Digital Screen', 'Transit Media', 'Bus Shelter', 'Mall Media', 'Airport Media']);
            $table->string('category', 100);
            $table->longText('description')->nullable();
            $table->foreignId('country_id')->constrained();
            $table->foreignId('state_id')->constrained();
            $table->foreignId('district_id')->constrained();
            $table->foreignId('city_id')->constrained();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('landmark_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('road_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('width', 10, 2);
            $table->decimal('height', 10, 2);
            $table->string('facing_direction', 100);
            $table->string('lighting_type', 100);
            $table->string('traffic_type', 100);
            $table->decimal('visibility_score', 10, 2)->default(0);
            $table->decimal('traffic_score', 10, 2)->default(0);
            $table->decimal('monthly_price', 12, 2);
            $table->decimal('weekly_price', 12, 2);
            $table->decimal('daily_price', 12, 2);
            $table->boolean('marketplace_enabled')->default(false);
            $table->boolean('featured')->default(false);
            $table->enum('status', ['Available', 'Reserved', 'Booked', 'Maintenance', 'Inactive', 'Blocked'])->default('Available');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['city_id', 'media_type', 'status'], 'inventory_search_idx');
            $table->index(['latitude', 'longitude']);
        });

        Schema::create('inventory_gallery', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_id')->constrained('inventory')->cascadeOnDelete();
            $table->enum('file_type', ['Image', 'Video', 'Drone']);
            $table->string('file_path', 500);
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('inventory_pricing', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_id')->constrained('inventory')->cascadeOnDelete();
            $table->enum('price_type', ['Daily', 'Weekly', 'Monthly', 'Festival', 'Special']);
            $table->decimal('amount', 12, 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
        });

        Schema::create('inventory_maintenance', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_id')->constrained('inventory')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason')->nullable();
            $table->enum('status', ['Active', 'Completed'])->default('Active');
            $table->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('campaign_code', 100)->unique();
            $table->string('title');
            $table->string('advertiser_name');
            $table->string('agency_name')->nullable();
            $table->string('customer_name');
            $table->string('customer_mobile', 20);
            $table->string('customer_email');
            $table->decimal('budget', 12, 2);
            $table->decimal('campaign_total_amount', 12, 2)->default(0);
            $table->decimal('campaign_gst_amount', 12, 2)->default(0);
            $table->decimal('campaign_provider_amount', 12, 2)->default(0);
            $table->decimal('campaign_commission_amount', 12, 2)->default(0);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['Draft', 'Pending', 'Processing', 'Approval Pending', 'Confirmed', 'Active', 'Completed', 'Cancelled', 'Expired'])->default('Draft');
            $table->longText('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('campaign_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained();
            $table->foreignId('state_id')->constrained();
            $table->foreignId('district_id')->constrained();
            $table->foreignId('city_id')->constrained();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->string('booking_code', 100)->unique();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_id')->constrained('inventory')->cascadeOnDelete();
            $table->enum('booking_type', ['Static', 'Digital', 'Transit', 'Loop']);
            $table->enum('booking_source', ['Website', 'Admin', 'Agent', 'Business Portal', 'API']);
            $table->enum('priority_level', ['Low', 'Medium', 'High', 'Critical'])->default('Medium');
            $table->date('booking_start_date');
            $table->date('booking_end_date');
            $table->unsignedInteger('total_days');
            $table->dateTime('reservation_expires_at')->nullable();
            $table->time('slot_start_time')->nullable();
            $table->time('slot_end_time')->nullable();
            $table->unsignedInteger('loop_duration')->nullable();
            $table->unsignedInteger('play_frequency')->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('gst_percentage', 5, 2);
            $table->decimal('gst_amount', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('provider_amount', 12, 2);
            $table->decimal('commission_amount', 12, 2);
            $table->json('booking_inventory_snapshot')->nullable();
            $table->json('booking_pricing_snapshot')->nullable();
            $table->enum('booking_status', ['Draft', 'Pending', 'Temporary Reserved', 'Approval Pending', 'Reserved', 'Confirmed', 'Active', 'Completed', 'Cancelled', 'Rejected', 'Expired'])->default('Draft');
            $table->enum('payment_status', ['Pending', 'Partial', 'Paid', 'Failed', 'Refunded'])->default('Pending');
            $table->boolean('approved_by_provider')->default(false);
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['inventory_id', 'booking_start_date', 'booking_end_date'], 'booking_inventory_dates_idx');
        });

        Schema::create('booking_calendar', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_id')->constrained('inventory')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('status', ['Available', 'Temporary Reserved', 'Reserved', 'Booked', 'Maintenance', 'Blocked']);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['inventory_id', 'date'], 'booking_calendar_inventory_date_unique');
            $table->index(['date', 'status']);
        });

        Schema::create('booking_conflicts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->enum('conflict_type', ['Date Overlap', 'Maintenance', 'Provider Block', 'Duplicate Reservation']);
            $table->text('remarks')->nullable();
            $table->boolean('resolved')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('booking_artworks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('artwork_file', 500);
            $table->enum('artwork_status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('booking_proofs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->enum('proof_type', ['Mounting Photo', 'Night Illumination', 'Drone View', 'Completion Proof']);
            $table->string('proof_file', 500);
            $table->text('remarks')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('booking_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('old_status', 100);
            $table->string('new_status', 100);
            $table->text('remarks')->nullable();
            $table->string('portal_source', 100);
            $table->string('ip_address', 100);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('invoice_number', 100)->unique();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->date('invoice_date');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('gst_amount', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->enum('payment_status', ['Pending', 'Partial', 'Paid', 'Overdue', 'Cancelled'])->default('Pending');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->enum('payment_mode', ['UPI', 'Bank Transfer', 'Cheque', 'NEFT', 'RTGS', 'Gateway']);
            $table->string('transaction_id')->unique();
            $table->decimal('amount', 12, 2);
            $table->dateTime('payment_date');
            $table->enum('payment_status', ['Pending', 'Paid', 'Failed', 'Refunded'])->default('Pending');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('provider_payouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('gst_deduction', 12, 2);
            $table->decimal('tds_amount', 12, 2);
            $table->decimal('final_amount', 12, 2);
            $table->enum('payment_status', ['Pending', 'Processing', 'Paid'])->default('Pending');
            $table->dateTime('payment_date')->nullable();
            $table->timestamps();
        });

        Schema::create('commissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->decimal('commission_percentage', 5, 2);
            $table->decimal('commission_amount', 12, 2);
            $table->enum('status', ['Pending', 'Approved', 'Paid'])->default('Pending');
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->string('expense_type', 100);
            $table->decimal('amount', 12, 2);
            $table->text('remarks')->nullable();
            $table->date('expense_date');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->decimal('refund_amount', 12, 2);
            $table->text('refund_reason')->nullable();
            $table->enum('refund_status', ['Pending', 'Approved', 'Paid'])->default('Pending');
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('mobile', 20);
            $table->string('email');
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lead_source', 100);
            $table->enum('status', ['New', 'Contacted', 'Interested', 'Negotiation', 'Converted', 'Lost'])->default('New');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_followups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->dateTime('followup_date');
            $table->text('remarks')->nullable();
            $table->enum('status', ['Pending', 'Completed', 'Rescheduled'])->default('Pending');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('marketplace_inquiries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_id')->nullable()->constrained('inventory')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('company_name');
            $table->string('mobile', 20);
            $table->string('email');
            $table->longText('message')->nullable();
            $table->enum('status', ['Pending', 'Contacted', 'Converted', 'Closed'])->default('Pending');
            $table->timestamps();
        });

        Schema::create('marketplace_search_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('search_keyword');
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('media_type', 100)->nullable();
            $table->dateTime('searched_at');
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('message');
            $table->enum('type', ['Email', 'SMS', 'WhatsApp', 'Push']);
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('notification_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('notification_type', 100);
            $table->string('recipient');
            $table->longText('message');
            $table->enum('status', ['Sent', 'Failed'])->default('Sent');
            $table->dateTime('sent_at');
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('setting_key')->unique();
            $table->longText('setting_value');
            $table->timestamps();
        });

        Schema::create('tax_settings', function (Blueprint $table): void {
            $table->id();
            $table->decimal('gst_percentage', 5, 2);
            $table->decimal('cgst_percentage', 5, 2);
            $table->decimal('sgst_percentage', 5, 2);
            $table->decimal('igst_percentage', 5, 2);
            $table->decimal('tds_percentage', 5, 2);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('analytics_cache', function (Blueprint $table): void {
            $table->id();
            $table->string('analytics_type', 100)->unique();
            $table->longText('cache_data');
            $table->dateTime('generated_at');
        });
    }

    public function down(): void
    {
        foreach ([
            'analytics_cache', 'tax_settings', 'settings', 'notification_logs', 'notifications',
            'marketplace_search_logs', 'marketplace_inquiries', 'lead_followups', 'leads',
            'refunds', 'expenses', 'commissions', 'provider_payouts', 'payments', 'invoices',
            'booking_logs', 'booking_proofs', 'booking_artworks', 'booking_conflicts',
            'booking_calendar', 'bookings', 'campaign_locations', 'campaigns',
            'inventory_maintenance', 'inventory_pricing', 'inventory_gallery', 'inventory',
            'provider_bank_accounts', 'provider_documents', 'provider_staff', 'providers',
            'branches', 'roads', 'landmarks', 'areas', 'cities', 'districts', 'states', 'countries',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
