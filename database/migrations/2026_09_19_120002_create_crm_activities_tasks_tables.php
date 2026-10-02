<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM activity timeline + task/follow-up management. Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Centralised activity timeline (polymorphic subject) ---------------
        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->unsignedBigInteger('lead_id')->nullable();     // -> crm_leads (soft ref)
            $table->unsignedBigInteger('booking_id')->nullable();  // -> bookings (soft ref)
            $table->string('type', 60);   // lead_created, stage_changed, note, call, whatsapp_in/out, email, quotation_*, payment_received, booking_confirmed, task_completed, assigned, ...
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('data')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('admins')->nullOnDelete(); // null = system/customer
            $table->boolean('is_internal')->default(false); // internal notes never exposed to customer
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index('lead_id');
            $table->index('contact_id');
            $table->index('occurred_at');
            $table->index('type');
        });

        // Tasks & follow-ups -------------------------------------------------
        Schema::create('crm_tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 40)->default('follow_up'); // call|whatsapp|email|follow_up|quotation|payment_reminder|document|booking_confirmation|post_trip|other
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('quotation_id')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['assigned_user_id', 'status']);
            $table->index('due_at');
            $table->index('lead_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_tasks');
        Schema::dropIfExists('crm_activities');
    }
};
