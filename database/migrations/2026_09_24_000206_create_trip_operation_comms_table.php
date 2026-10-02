<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trip_operation_comms')) {
            return;
        }

        Schema::create('trip_operation_comms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->nullable()->constrained('trips')->cascadeOnDelete();
            $table->unsignedBigInteger('driver_assignment_id')->nullable();
            $table->string('channel', 16);          // whatsapp|email|sms
            $table->string('recipient_type', 16);   // customer|driver
            $table->string('recipient');            // phone or email
            $table->string('event_key');            // idempotency key, e.g. driver_assigned:12
            $table->string('status', 16)->default('sent'); // sent|failed|skipped
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // Guarantees a given trigger reaches a given recipient on a given channel only once.
            $table->unique(['event_key', 'channel', 'recipient'], 'trip_comms_idempotency');
            $table->index(['trip_id', 'recipient_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_operation_comms');
    }
};
