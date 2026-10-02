<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50)->default('general');
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('text'); // text|textarea|number|boolean|image|json|encrypt
            $table->boolean('is_public')->default(false); // safe to expose to frontend
            $table->timestamps();
            $table->index('group');
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('file_name');
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size')->default(0);
            $table->string('alt_text')->nullable();
            $table->string('folder')->default('/');
            $table->string('dimension')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index('folder');
        });

        Schema::create('seo_metadata', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 50)->default('page'); // page|destination|package|hotel|blog|guide
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('page_key')->nullable(); // home, flights, hotels ...
            $table->string('seo_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image')->nullable();
            $table->text('schema_json')->nullable();
            $table->string('robots', 50)->default('index,follow');
            $table->timestamps();
            $table->unique(['entity_type', 'entity_id', 'page_key']);
        });

        Schema::create('homepage_sections', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('name');
            $table->string('type', 50);
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->json('content')->nullable();
            $table->string('background')->nullable();
            $table->string('cta_text')->nullable();
            $table->string('cta_url')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_sections');
        Schema::dropIfExists('seo_metadata');
        Schema::dropIfExists('media');
        Schema::dropIfExists('settings');
    }
};
