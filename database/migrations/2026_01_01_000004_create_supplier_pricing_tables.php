<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('type', ['flight', 'hotel', 'cab', 'package', 'bus', 'generic'])->default('generic');
            $table->string('adapter', 100)->nullable(); // adapter class alias e.g. demo_flight, amadeus, tbo
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->enum('environment', ['test', 'production'])->default('test');
            $table->unsignedInteger('priority')->default(0); // lower = higher priority
            $table->unsignedInteger('timeout_seconds')->default(30);
            $table->unsignedInteger('retry_attempts')->default(2);
            $table->decimal('default_markup_percent', 5, 2)->default(0);
            $table->decimal('default_commission_percent', 5, 2)->default(0);
            $table->decimal('default_service_fee', 10, 2)->default(0);
            $table->json('settings')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('supplier_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->enum('environment', ['test', 'production'])->default('test');
            $table->text('credentials'); // encrypted JSON
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['supplier_id', 'environment']);
        });

        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('product_type', 30)->default('all'); // flight|hotel|cab|package|all
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->enum('rule_type', ['markup', 'commission', 'service_fee', 'convenience_fee', 'discount']);
            $table->enum('calculation', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('value', 10, 2);
            $table->decimal('min_amount', 10, 2)->nullable();
            $table->decimal('max_amount', 10, 2)->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('product_type', 30)->default('all'); // flight|hotel|cab|package|all
            $table->enum('calculation', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('value', 8, 2);
            $table->boolean('is_inclusive')->default(false);
            $table->string('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxes');
        Schema::dropIfExists('pricing_rules');
        Schema::dropIfExists('supplier_credentials');
        Schema::dropIfExists('suppliers');
    }
};
