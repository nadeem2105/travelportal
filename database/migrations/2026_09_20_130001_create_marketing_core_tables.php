<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing foundation: OAuth connections to ad platforms (encrypted tokens) and
 * the ad accounts discovered under each connection. Credentials are stored via
 * encrypted casts on the model — never in plain text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_connections', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);            // google_ads | meta_ads
            $table->string('name')->nullable();        // label for the connection
            $table->text('credentials')->nullable();   // encrypted: {access_token, refresh_token, expires_at, scope, ...}
            $table->string('external_user_id')->nullable(); // provider user/business id
            $table->string('status', 30)->default('connected'); // connected|disconnected|needs_reauth|error
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_status', 20)->nullable();
            $table->text('last_error')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['provider', 'status']);
        });

        Schema::create('marketing_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('marketing_connections')->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('external_account_id');     // Google customer_id / Meta ad_account_id
            $table->string('account_name')->nullable();
            $table->string('manager_customer_id')->nullable(); // Google MCC
            $table->string('business_id')->nullable();          // Meta business id
            $table->string('page_id')->nullable();              // Meta page
            $table->string('currency', 8)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_status', 20)->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_account_id']);
            $table->index('connection_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_accounts');
        Schema::dropIfExists('marketing_connections');
    }
};
