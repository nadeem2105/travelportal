<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ad Creative Studio — adopts the previously-orphaned marketing_creatives /
 * marketing_assets tables and extends them into a full creative record, plus
 * adds brand kits and dynamic templates. Fully ADDITIVE and idempotent
 * (hasTable / hasColumn guards) so it is safe on an existing DB and never
 * touches campaigns, bookings, WhatsApp or CRM tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Extend marketing_creatives into a full studio creative ---------
        if (Schema::hasTable('marketing_creatives')) {
            Schema::table('marketing_creatives', function (Blueprint $table) {
                if (! Schema::hasColumn('marketing_creatives', 'campaign_id')) {
                    $table->foreignId('campaign_id')->nullable()->after('id')->constrained('marketing_campaigns')->nullOnDelete();
                }
                if (! Schema::hasColumn('marketing_creatives', 'brand_kit_id')) {
                    $table->unsignedBigInteger('brand_kit_id')->nullable()->after('campaign_id');
                }
                if (! Schema::hasColumn('marketing_creatives', 'template_id')) {
                    $table->unsignedBigInteger('template_id')->nullable()->after('brand_kit_id');
                }
                if (! Schema::hasColumn('marketing_creatives', 'objective')) {
                    $table->string('objective', 40)->nullable()->after('destination');
                }
                if (! Schema::hasColumn('marketing_creatives', 'audience')) {
                    $table->string('audience', 60)->nullable()->after('objective');
                }
                if (! Schema::hasColumn('marketing_creatives', 'audience_detail')) {
                    $table->text('audience_detail')->nullable()->after('audience');
                }
                if (! Schema::hasColumn('marketing_creatives', 'platform')) {
                    $table->string('platform', 30)->nullable()->after('audience_detail'); // instagram|facebook|whatsapp|youtube|website
                }
                if (! Schema::hasColumn('marketing_creatives', 'format')) {
                    $table->string('format', 40)->nullable()->after('platform'); // ig_1x1|ig_4x5|ig_9x16|fb_1x1|wa_9x16|yt_16x9|web_hero...
                }
                if (! Schema::hasColumn('marketing_creatives', 'language')) {
                    $table->string('language', 12)->default('en')->after('format'); // en|hi|ur|hinglish
                }
                if (! Schema::hasColumn('marketing_creatives', 'style')) {
                    $table->string('style', 40)->nullable()->after('language'); // cinematic|luxury|romantic...
                }
                if (! Schema::hasColumn('marketing_creatives', 'headline')) {
                    $table->string('headline')->nullable()->after('style');
                }
                if (! Schema::hasColumn('marketing_creatives', 'render_path')) {
                    $table->string('render_path')->nullable()->after('asset_id'); // composited output on public disk
                }
                if (! Schema::hasColumn('marketing_creatives', 'thumb_path')) {
                    $table->string('thumb_path')->nullable()->after('render_path');
                }
                if (! Schema::hasColumn('marketing_creatives', 'width')) {
                    $table->unsignedInteger('width')->nullable()->after('thumb_path');
                }
                if (! Schema::hasColumn('marketing_creatives', 'height')) {
                    $table->unsignedInteger('height')->nullable()->after('width');
                }
                if (! Schema::hasColumn('marketing_creatives', 'spec')) {
                    $table->json('spec')->nullable()->after('copy'); // resolved facts + layer/overlay config
                }
                if (! Schema::hasColumn('marketing_creatives', 'tracking')) {
                    $table->json('tracking')->nullable()->after('spec'); // utm + creative code + target url
                }
                if (! Schema::hasColumn('marketing_creatives', 'warnings')) {
                    $table->json('warnings')->nullable()->after('tracking'); // fact-check / quality warnings
                }
                if (! Schema::hasColumn('marketing_creatives', 'generation_status')) {
                    $table->string('generation_status', 20)->default('draft')->after('status'); // draft|queued|processing|completed|failed|cancelled
                }
                if (! Schema::hasColumn('marketing_creatives', 'generation_error')) {
                    $table->text('generation_error')->nullable()->after('generation_status');
                }
                if (! Schema::hasColumn('marketing_creatives', 'approval_status')) {
                    $table->string('approval_status', 20)->default('draft')->after('generation_error'); // draft|pending|approved|rejected|published|archived
                }
                if (! Schema::hasColumn('marketing_creatives', 'approval_note')) {
                    $table->text('approval_note')->nullable()->after('approval_status');
                }
                if (! Schema::hasColumn('marketing_creatives', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable()->after('approval_note');
                }
                if (! Schema::hasColumn('marketing_creatives', 'variation_group')) {
                    $table->string('variation_group', 40)->nullable()->after('approved_by'); // groups sibling variations
                }
                if (! Schema::hasColumn('marketing_creatives', 'variation_focus')) {
                    $table->string('variation_focus', 30)->nullable()->after('variation_group'); // price|destination|experience|luxury|emotional
                }
                if (! Schema::hasColumn('marketing_creatives', 'parent_id')) {
                    $table->unsignedBigInteger('parent_id')->nullable()->after('variation_focus'); // version lineage
                }
                if (! Schema::hasColumn('marketing_creatives', 'version')) {
                    $table->unsignedInteger('version')->default(1)->after('parent_id');
                }
                if (! Schema::hasColumn('marketing_creatives', 'ai_generation_id')) {
                    $table->unsignedBigInteger('ai_generation_id')->nullable()->after('version');
                }
                if (! Schema::hasColumn('marketing_creatives', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // --- Extend marketing_assets ---------------------------------------
        if (Schema::hasTable('marketing_assets')) {
            Schema::table('marketing_assets', function (Blueprint $table) {
                if (! Schema::hasColumn('marketing_assets', 'width')) {
                    $table->unsignedInteger('width')->nullable()->after('dimensions');
                }
                if (! Schema::hasColumn('marketing_assets', 'height')) {
                    $table->unsignedInteger('height')->nullable()->after('width');
                }
                if (! Schema::hasColumn('marketing_assets', 'source')) {
                    $table->string('source', 20)->default('upload')->after('category'); // portal|ai|upload
                }
                if (! Schema::hasColumn('marketing_assets', 'thumb_path')) {
                    $table->string('thumb_path')->nullable()->after('path');
                }
                if (! Schema::hasColumn('marketing_assets', 'product_type')) {
                    $table->string('product_type', 20)->nullable()->after('source'); // package|hotel|destination|activity
                }
                if (! Schema::hasColumn('marketing_assets', 'product_id')) {
                    $table->unsignedBigInteger('product_id')->nullable()->after('product_type');
                }
                if (! Schema::hasColumn('marketing_assets', 'checksum')) {
                    $table->string('checksum', 64)->nullable()->after('product_id');
                }
                if (! Schema::hasColumn('marketing_assets', 'license_status')) {
                    $table->string('license_status', 20)->default('owned')->after('checksum'); // owned|licensed|illustrative
                }
                if (! Schema::hasColumn('marketing_assets', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // --- Brand kits -----------------------------------------------------
        if (! Schema::hasTable('creative_brand_kits')) {
            Schema::create('creative_brand_kits', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->boolean('is_default')->default(false);
                $table->string('brand_name')->nullable();
                $table->string('logo_path')->nullable();
                $table->string('primary_color', 9)->default('#2563eb');
                $table->string('secondary_color', 9)->default('#0b1f3a');
                $table->string('accent_color', 9)->default('#f59e0b');
                $table->string('text_color', 9)->default('#ffffff');
                $table->string('font_family', 80)->nullable();
                $table->string('website')->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('whatsapp', 40)->nullable();
                $table->string('email')->nullable();
                $table->string('address')->nullable();
                $table->json('socials')->nullable();
                $table->string('default_cta', 40)->default('Book Now');
                $table->string('default_disclaimer')->nullable();
                $table->string('status', 20)->default('active');
                $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['status', 'is_default']);
            });
        }

        // --- Dynamic templates ---------------------------------------------
        if (! Schema::hasTable('creative_templates')) {
            Schema::create('creative_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('category', 40)->default('package'); // package|hotel|promotion|social
                $table->string('subcategory', 60)->nullable();       // honeymoon|flash_sale|reel...
                $table->string('platform', 30)->nullable();
                $table->string('format', 40)->nullable();
                $table->string('style', 40)->nullable();
                $table->text('description')->nullable();
                $table->text('headline_template')->nullable();       // supports {{package_name}} etc.
                $table->text('primary_text_template')->nullable();
                $table->text('image_prompt_template')->nullable();   // AI background prompt scaffold
                $table->json('layers')->nullable();                  // overlay layout config
                $table->json('variables')->nullable();               // supported {{var}} keys
                $table->string('preview_path')->nullable();
                $table->string('status', 20)->default('active');
                $table->boolean('is_system')->default(false);
                $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['category', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('creative_templates');
        Schema::dropIfExists('creative_brand_kits');

        // Reverse creative/asset column additions (best-effort, guarded).
        if (Schema::hasTable('marketing_creatives')) {
            Schema::table('marketing_creatives', function (Blueprint $table) {
                foreach ([
                    'brand_kit_id', 'template_id', 'objective', 'audience', 'audience_detail',
                    'platform', 'format', 'language', 'style', 'headline', 'render_path', 'thumb_path',
                    'width', 'height', 'spec', 'tracking', 'warnings', 'generation_status',
                    'generation_error', 'approval_status', 'approval_note', 'approved_by',
                    'variation_group', 'variation_focus', 'parent_id', 'version', 'ai_generation_id',
                ] as $col) {
                    if (Schema::hasColumn('marketing_creatives', $col)) {
                        $table->dropColumn($col);
                    }
                }
                if (Schema::hasColumn('marketing_creatives', 'campaign_id')) {
                    $table->dropConstrainedForeignId('campaign_id');
                }
                if (Schema::hasColumn('marketing_creatives', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }

        if (Schema::hasTable('marketing_assets')) {
            Schema::table('marketing_assets', function (Blueprint $table) {
                foreach (['width', 'height', 'source', 'thumb_path', 'product_type', 'product_id', 'checksum', 'license_status'] as $col) {
                    if (Schema::hasColumn('marketing_assets', $col)) {
                        $table->dropColumn($col);
                    }
                }
                if (Schema::hasColumn('marketing_assets', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }
    }
};
