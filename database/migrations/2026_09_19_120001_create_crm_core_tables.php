<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM core: unified contacts, companies, configurable lead sources & pipelines,
 * and polymorphic tags. All additive — nothing existing is dropped or altered.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Companies (B2B / corporate) ---------------------------------------
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('gst_number', 30)->nullable();
            $table->string('tax_number', 40)->nullable();
            $table->enum('type', ['b2b', 'corporate', 'partner', 'other'])->default('corporate');
            $table->foreignId('assigned_user_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        // Contacts (unified customer/prospect profile) ----------------------
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // link to registered customer
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('name');
            $table->string('phone', 30)->nullable();          // normalized (digits, E.164-ish)
            $table->string('phone_raw', 40)->nullable();       // as entered
            $table->string('alternate_phone', 30)->nullable();
            $table->string('email')->nullable();               // normalized (lowercased, trimmed)
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('preferred_language', 10)->nullable();
            $table->string('preferred_contact_channel', 20)->nullable(); // whatsapp|email|sms|phone
            $table->string('timezone', 40)->nullable();
            $table->boolean('marketing_opt_in')->default(false);
            $table->boolean('whatsapp_opt_in')->default(false);
            $table->boolean('email_opt_in')->default(true);
            $table->boolean('sms_opt_in')->default(true);
            $table->enum('lifecycle_stage', ['lead', 'prospect', 'qualified', 'customer', 'repeat', 'vip', 'inactive'])->default('lead');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('phone');
            $table->index('email');
            $table->index('lifecycle_stage');
            $table->index('assigned_user_id');
        });

        // Configurable lead sources -----------------------------------------
        Schema::create('lead_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('source')->nullable();   // utm_source equivalent
            $table->string('medium')->nullable();
            $table->string('platform')->nullable();  // meta|google|website|whatsapp|...
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Configurable pipelines & stages -----------------------------------
        Schema::create('pipelines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('key')->nullable(); // package|flight|hotel|cab|b2b|corporate|default
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained('pipelines')->cascadeOnDelete();
            $table->string('name');
            $table->string('key')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('probability')->default(0); // 0-100
            $table->boolean('is_won')->default(false);
            $table->boolean('is_lost')->default(false);
            $table->string('color', 20)->nullable();
            $table->json('requirements')->nullable();
            $table->json('automation')->nullable();
            $table->timestamps();

            $table->index(['pipeline_id', 'sort_order']);
        });

        // Polymorphic tags ---------------------------------------------------
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 20)->nullable();
            $table->string('category')->nullable();
            $table->timestamps();
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->string('taggable_type');
            $table->unsignedBigInteger('taggable_id');
            $table->timestamps();

            $table->unique(['tag_id', 'taggable_type', 'taggable_id'], 'taggables_unique');
            $table->index(['taggable_type', 'taggable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('pipeline_stages');
        Schema::dropIfExists('pipelines');
        Schema::dropIfExists('lead_sources');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('companies');
    }
};
