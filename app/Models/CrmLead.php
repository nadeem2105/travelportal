<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CrmLead extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        // legacy
        'name', 'email', 'phone', 'destination', 'product_type', 'budget',
        'travellers_count', 'travel_date', 'source', 'status', 'assigned_to',
        'notes', 'tags', 'converted_booking_id',
        // CRM identity + relations
        'uuid', 'lead_number', 'contact_id', 'company_id', 'source_id', 'source_detail',
        'pipeline_id', 'stage_id', 'score', 'priority',
        // trip requirements
        'travel_start_date', 'travel_end_date', 'adults', 'children', 'infants', 'rooms',
        'budget_min', 'budget_max', 'service_type',
        // attribution
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'gclid', 'fbclid', 'landing_page', 'referrer_url',
        'first_touch_source', 'first_touch_medium', 'first_touch_campaign',
        'last_touch_source', 'last_touch_medium', 'last_touch_campaign',
        // external marketing ids
        'external_lead_id', 'external_campaign_id', 'external_adset_id', 'external_ad_id', 'form_id',
        // lifecycle
        'first_contacted_at', 'last_contacted_at', 'next_follow_up_at',
        'converted_at', 'lost_at', 'lost_reason',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'budget_min' => 'decimal:2',
        'budget_max' => 'decimal:2',
        'travel_date' => 'date',
        'travel_start_date' => 'date',
        'travel_end_date' => 'date',
        'tags' => 'array',
        'first_contacted_at' => 'datetime',
        'last_contacted_at' => 'datetime',
        'next_follow_up_at' => 'datetime',
        'converted_at' => 'datetime',
        'lost_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $lead) {
            if (empty($lead->uuid)) {
                $lead->uuid = (string) Str::uuid();
            }
            if (empty($lead->lead_number)) {
                $lead->lead_number = static::generateLeadNumber();
            }
        });
    }

    public static function generateLeadNumber(): string
    {
        $year = now()->format('Y');
        do {
            $seq = str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
            $number = "LD-{$year}-{$seq}";
        } while (static::withTrashed()->where('lead_number', $number)->exists());

        return $number;
    }

    // Relationships -----------------------------------------------------------

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_to');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'source_id');
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }

    public function convertedBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'converted_booking_id');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(CrmFollowUp::class, 'lead_id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(CrmQuotation::class, 'lead_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'lead_id')->latest('occurred_at');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CrmTask::class, 'lead_id');
    }

    // Scopes ------------------------------------------------------------------

    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', ['converted', 'lost']);
    }

    public function scopeConverted($query)
    {
        return $query->where('status', 'converted');
    }
}
