<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. B2B Agents
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('agency_name');
            $table->string('agency_code')->unique();
            $table->string('contact_person');
            $table->string('email')->unique();
            $table->string('phone', 25);
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->string('pan_number', 20)->nullable();
            $table->string('gst_number', 20)->nullable();
            $table->string('iata_code', 20)->nullable();
            $table->enum('status', ['pending', 'approved', 'suspended', 'rejected'])->default('pending');
            $table->decimal('wallet_balance', 12, 2)->default(0.00);
            $table->decimal('credit_limit', 12, 2)->default(0.00);
            $table->decimal('credit_balance', 12, 2)->default(0.00);
            $table->decimal('commission_rate', 5, 2)->default(0.00);
            $table->decimal('markup_rate', 5, 2)->default(0.00);
            $table->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        // 2. Agent Transactions (Financial Wallet & Credit Ledger)
        Schema::create('agent_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->enum('type', ['deposit', 'booking_debit', 'booking_credit', 'refund', 'credit_adjustment', 'commission']);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['agent_id', 'created_at']);
        });

        // 3. CRM Leads
        Schema::create('crm_leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 25);
            $table->string('destination')->nullable();
            $table->enum('product_type', ['package', 'flight', 'hotel', 'cab', 'custom'])->default('package');
            $table->decimal('budget', 12, 2)->nullable();
            $table->unsignedInteger('travellers_count')->default(1);
            $table->date('travel_date')->nullable();
            $table->enum('source', ['website', 'referral', 'phone', 'social', 'campaign'])->default('website');
            $table->enum('status', ['new', 'contacted', 'quotation_sent', 'negotiating', 'converted', 'lost'])->default('new');
            $table->foreignId('assigned_to')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('tags')->nullable();
            $table->foreignId('converted_booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('assigned_to');
            $table->index('created_at');
        });

        // 4. CRM Follow-ups
        Schema::create('crm_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('note');
            $table->timestamp('scheduled_at')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'scheduled_at']);
        });

        // 5. CRM Quotations
        Schema::create('crm_quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->string('quotation_number')->unique();
            $table->string('title');
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->json('items')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('tax_amount', 12, 2)->default(0.00);
            $table->decimal('total_amount', 12, 2)->default(0.00);
            $table->date('valid_until')->nullable();
            $table->enum('status', ['draft', 'sent', 'accepted', 'rejected', 'expired'])->default('draft');
            $table->timestamps();

            $table->index('lead_id');
            $table->index('status');
        });

        // 6. Affiliates
        Schema::create('affiliates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('affiliate_code')->unique();
            $table->decimal('commission_percent', 5, 2)->default(5.00);
            $table->enum('status', ['pending', 'active', 'suspended'])->default('active');
            $table->text('payout_details')->nullable();
            $table->decimal('total_earnings', 12, 2)->default(0.00);
            $table->decimal('paid_earnings', 12, 2)->default(0.00);
            $table->timestamps();

            $table->index('status');
        });

        // 7. Affiliate Clicks
        Schema::create('affiliate_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('landing_page')->nullable();
            $table->boolean('converted')->default(false);
            $table->timestamps();

            $table->index(['affiliate_id', 'created_at']);
        });

        // 8. Affiliate Commissions
        Schema::create('affiliate_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->decimal('booking_amount', 12, 2);
            $table->decimal('commission_amount', 12, 2);
            $table->enum('status', ['pending', 'approved', 'paid', 'cancelled'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['affiliate_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_commissions');
        Schema::dropIfExists('affiliate_clicks');
        Schema::dropIfExists('affiliates');
        Schema::dropIfExists('crm_quotations');
        Schema::dropIfExists('crm_follow_ups');
        Schema::dropIfExists('crm_leads');
        Schema::dropIfExists('agent_transactions');
        Schema::dropIfExists('agents');
    }
};
