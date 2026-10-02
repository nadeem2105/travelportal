<?php

namespace App\Services\Crm;

use App\Models\Contact;
use Illuminate\Support\Facades\DB;

/**
 * Owns contact identity: phone/email normalization, deduplication, find-or-create,
 * and merge. Never creates a duplicate contact when a normalized phone or email
 * already matches.
 */
class ContactService
{
    public function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return null;
        }
        // Strip leading zeros; prepend default country code for bare local numbers.
        $digits = ltrim($digits, '0');
        $cc = (string) config('crm.default_country_code', '91');
        if (strlen($digits) === 10) {
            $digits = $cc . $digits;
        }

        return $digits;
    }

    public function normalizeEmail(?string $email): ?string
    {
        if (! $email) {
            return null;
        }
        $email = strtolower(trim($email));

        return $email !== '' ? $email : null;
    }

    /** Find an existing contact by normalized phone or email. */
    public function findExisting(?string $phone, ?string $email): ?Contact
    {
        $phone = $this->normalizePhone($phone);
        $email = $this->normalizeEmail($email);

        if (! $phone && ! $email) {
            return null;
        }

        return Contact::query()
            ->when($phone, fn ($q) => $q->orWhere('phone', $phone))
            ->when($email, fn ($q) => $q->orWhere('email', $email))
            ->first();
    }

    /**
     * Find or create a contact from raw input. Fills only empty fields on an
     * existing contact (never clobbers curated data).
     *
     * @param  array  $data  name, phone, email, city, country, source_id, opt-ins…
     */
    public function findOrCreate(array $data): Contact
    {
        $phone = $this->normalizePhone($data['phone'] ?? null);
        $email = $this->normalizeEmail($data['email'] ?? null);

        $contact = $this->findExisting($data['phone'] ?? null, $data['email'] ?? null);

        if ($contact) {
            // Backfill missing fields only.
            $fill = [];
            foreach (['name', 'city', 'state', 'country', 'address', 'source_id'] as $f) {
                if (empty($contact->{$f}) && ! empty($data[$f])) {
                    $fill[$f] = $data[$f];
                }
            }
            if (! $contact->email && $email) {
                $fill['email'] = $email;
            }
            if (! $contact->phone && $phone) {
                $fill['phone'] = $phone;
                $fill['phone_raw'] = $data['phone'] ?? null;
            }
            if ($fill) {
                $contact->fill($fill)->save();
            }

            return $contact;
        }

        return Contact::create([
            'name' => $data['name'] ?? ($data['phone'] ?? $email ?? 'Unknown'),
            'phone' => $phone,
            'phone_raw' => $data['phone'] ?? null,
            'email' => $email,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'country' => $data['country'] ?? null,
            'address' => $data['address'] ?? null,
            'source_id' => $data['source_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'lifecycle_stage' => $data['lifecycle_stage'] ?? 'lead',
            'marketing_opt_in' => (bool) ($data['marketing_opt_in'] ?? false),
            'whatsapp_opt_in' => (bool) ($data['whatsapp_opt_in'] ?? false),
            'email_opt_in' => (bool) ($data['email_opt_in'] ?? true),
            'sms_opt_in' => (bool) ($data['sms_opt_in'] ?? true),
        ]);
    }

    /**
     * Merge secondary contact into primary: re-point leads/activities/tasks/tags,
     * backfill empty primary fields, then soft-delete the secondary.
     */
    public function merge(Contact $primary, Contact $secondary): Contact
    {
        if ($primary->id === $secondary->id) {
            return $primary;
        }

        DB::transaction(function () use ($primary, $secondary) {
            \App\Models\CrmLead::where('contact_id', $secondary->id)->update(['contact_id' => $primary->id]);
            \App\Models\CrmActivity::where('contact_id', $secondary->id)->update(['contact_id' => $primary->id]);
            \App\Models\CrmTask::where('contact_id', $secondary->id)->update(['contact_id' => $primary->id]);

            // Move tags not already present.
            $existing = $primary->tags()->pluck('tags.id')->all();
            $incoming = $secondary->tags()->pluck('tags.id')->all();
            $primary->tags()->syncWithoutDetaching(array_diff($incoming, $existing));

            // Backfill empty primary fields from secondary.
            $fill = [];
            foreach (['email', 'phone', 'alternate_phone', 'city', 'state', 'country', 'address', 'company_id', 'assigned_user_id'] as $f) {
                if (empty($primary->{$f}) && ! empty($secondary->{$f})) {
                    $fill[$f] = $secondary->{$f};
                }
            }
            if ($fill) {
                $primary->fill($fill)->save();
            }

            $secondary->delete();
        });

        return $primary->fresh();
    }
}
