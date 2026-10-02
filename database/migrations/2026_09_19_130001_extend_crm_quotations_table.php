<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrade the lightweight `crm_quotations` into a full quotation: secure public
 * token, richer pricing (discount + currency), payment schedule, terms,
 * lifecycle tracking timestamps, and conversion link. Additive + backward
 * compatible; the status enum is WIDENED (never narrowed).
 *
 * Idempotent: every column/index is guarded so a partially-applied run (MySQL
 * auto-commits DDL, so a mid-migration failure leaves earlier columns behind)
 * can be safely re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_quotations', 'uuid')) {
                $table->uuid('uuid')->nullable()->after('id');
            }
            if (! Schema::hasColumn('crm_quotations', 'public_token')) {
                $table->string('public_token', 64)->nullable()->unique()->after('quotation_number');
            }
            if (! Schema::hasColumn('crm_quotations', 'contact_id')) {
                $table->unsignedBigInteger('contact_id')->nullable()->after('lead_id');
            }
            if (! Schema::hasColumn('crm_quotations', 'currency')) {
                $table->string('currency', 3)->default('INR')->after('total_amount');
            }
            if (! Schema::hasColumn('crm_quotations', 'discount_amount')) {
                $table->decimal('discount_amount', 12, 2)->default(0)->after('tax_amount');
            }
            if (! Schema::hasColumn('crm_quotations', 'payment_schedule')) {
                $table->json('payment_schedule')->nullable()->after('items');
            }
            if (! Schema::hasColumn('crm_quotations', 'terms')) {
                $table->text('terms')->nullable()->after('payment_schedule');
            }
            if (! Schema::hasColumn('crm_quotations', 'cancellation_policy')) {
                $table->text('cancellation_policy')->nullable()->after('terms');
            }
            if (! Schema::hasColumn('crm_quotations', 'notes')) {
                $table->text('notes')->nullable()->after('cancellation_policy');
            }
            if (! Schema::hasColumn('crm_quotations', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('status')->constrained('admins')->nullOnDelete();
            }
            if (! Schema::hasColumn('crm_quotations', 'converted_booking_id')) {
                $table->unsignedBigInteger('converted_booking_id')->nullable()->after('created_by');
            }
            if (! Schema::hasColumn('crm_quotations', 'sent_at')) {
                $table->timestamp('sent_at')->nullable();
            }
            if (! Schema::hasColumn('crm_quotations', 'viewed_at')) {
                $table->timestamp('viewed_at')->nullable();
            }
            if (! Schema::hasColumn('crm_quotations', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable();
            }
            if (! Schema::hasColumn('crm_quotations', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable();
            }
        });

        // Indexes — only add when missing (the base table may already index status).
        if (! $this->indexExists('crm_quotations', 'crm_quotations_contact_id_index')) {
            Schema::table('crm_quotations', fn (Blueprint $t) => $t->index('contact_id'));
        }
        if (! $this->indexExists('crm_quotations', 'crm_quotations_status_index')) {
            Schema::table('crm_quotations', fn (Blueprint $t) => $t->index('status'));
        }

        // Widen the status enum (add viewed / negotiation / converted). MySQL-safe, additive.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE crm_quotations MODIFY COLUMN status ENUM('draft','sent','viewed','negotiation','accepted','rejected','expired','converted') NOT NULL DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        Schema::table('crm_quotations', function (Blueprint $table) {
            if (Schema::hasColumn('crm_quotations', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
            foreach ([
                'uuid', 'public_token', 'contact_id', 'currency', 'discount_amount',
                'payment_schedule', 'terms', 'cancellation_policy', 'notes',
                'converted_booking_id', 'sent_at', 'viewed_at', 'accepted_at', 'rejected_at',
            ] as $col) {
                if (Schema::hasColumn('crm_quotations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE crm_quotations MODIFY COLUMN status ENUM('draft','sent','accepted','rejected','expired') NOT NULL DEFAULT 'draft'");
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        // Cross-driver (MySQL, SQLite, Postgres): Laravel's schema introspection.
        foreach (Schema::getIndexes($table) as $existing) {
            $name = is_array($existing) ? ($existing['name'] ?? null) : ($existing->name ?? null);
            if ($name !== null && strtolower($name) === strtolower($index)) {
                return true;
            }
        }

        return false;
    }
};
