<?php

namespace App\Services\WhatsApp\Assistant;

use App\Models\Booking;
use App\Models\Contact;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Services\Crm\ContactService;

/**
 * Maps a WhatsApp sender (wa_id / phone) to a customer identity and enforces
 * identity verification. THIS is where authorization lives — the AI never
 * decides who may see what. Every data-access tool asks this resolver for the
 * set of booking IDs the current conversation is allowed to touch, and the
 * resolver scopes strictly to the verified customer.
 *
 * Verification paths (config services.whatsapp.ai_assistant.require_verification):
 *  1. Registered phone — the WhatsApp number (which Meta proves the sender
 *     controls) matches a registered User's phone. Auto-verified.
 *  2. Booking reference — the customer supplies a booking reference whose own
 *     contact phone / owner phone matches the WhatsApp number. This binds the
 *     reference to the sender's own number, so knowing a stranger's booking ID
 *     is NOT enough.
 */
class CustomerResolver
{
    public function __construct(private ContactService $contacts)
    {
    }

    /** Normalized form of the WhatsApp number (digits + country code). */
    public function normalize(string $waIdOrPhone): ?string
    {
        return $this->contacts->normalizePhone($waIdOrPhone);
    }

    /**
     * Is this conversation already verified? Re-checks that the cached verified
     * identity still matches the conversation's WhatsApp number.
     */
    public function isVerified(WhatsAppConversation $conversation): bool
    {
        if (! (bool) config('services.whatsapp.ai_assistant.require_verification', true)) {
            // Verification disabled — treat any resolvable identity as trusted.
            return true;
        }

        return $conversation->isVerified();
    }

    /**
     * Attempt to auto-verify by matching the WhatsApp number to a registered
     * User account (path 1). Persists the verified identity on the conversation.
     * Returns true when verification succeeded.
     */
    public function autoVerify(WhatsAppConversation $conversation): bool
    {
        if ($conversation->isVerified()) {
            return true;
        }

        $phone = $this->normalize($conversation->wa_id);
        if (! $phone) {
            return false;
        }

        $user = $this->findUserByPhone($phone);
        $contact = $conversation->contact_id
            ? Contact::find($conversation->contact_id)
            : $this->contacts->findExisting($phone, null);

        // Only auto-verify when the number maps to a concrete customer that owns
        // at least one booking, OR to a registered user account. A bare CRM
        // contact with no bookings gets no data access until it books.
        if ($user) {
            $this->markVerified($conversation, $user, $contact);

            return true;
        }

        if ($contact && $contact->user_id) {
            $this->markVerified($conversation, $contact->user, $contact);

            return true;
        }

        // Guest customer: number matches booking contact JSON. Auto-verify since
        // the sender provably controls this number and it is the booking's phone.
        foreach ($this->guestBookingCandidates($phone)->get(['id', 'contact']) as $b) {
            if ($this->normalize((string) data_get($b->contact, 'phone')) === $phone) {
                $this->markVerified($conversation, null, $contact);

                return true;
            }
        }

        return false;
    }

    /**
     * Verify by booking reference (path 2). The reference must belong to a
     * booking whose contact phone or owner phone matches this WhatsApp number.
     * Returns true on success.
     */
    public function verifyByBookingReference(WhatsAppConversation $conversation, string $reference): bool
    {
        $reference = trim($reference);
        if ($reference === '') {
            return false;
        }

        $phone = $this->normalize($conversation->wa_id);
        if (! $phone) {
            return false;
        }

        $booking = Booking::where('booking_reference', $reference)->first();
        if (! $booking) {
            return false;
        }

        if (! $this->bookingBelongsToPhone($booking, $phone)) {
            return false;
        }

        $user = $booking->user_id ? User::find($booking->user_id) : $this->findUserByPhone($phone);
        $contact = $conversation->contact_id
            ? Contact::find($conversation->contact_id)
            : $this->contacts->findExisting($phone, data_get($booking->contact, 'email'));

        $this->markVerified($conversation, $user, $contact);

        return true;
    }

    /**
     * The definitive set of booking IDs this verified conversation may access.
     * Returns an empty array when unverified — callers MUST treat empty as "no
     * access". This is the single choke point for authorization.
     *
     * @return array<int>
     */
    public function ownedBookingIds(WhatsAppConversation $conversation): array
    {
        if (! $this->isVerified($conversation)) {
            return [];
        }

        $phone = $this->normalize($conversation->wa_id);
        $userId = $conversation->verified_user_id;

        $ids = [];

        if ($userId) {
            $ids = Booking::where('user_id', $userId)->pluck('id')->all();
        }

        // Include guest bookings placed under the same (verified) phone number.
        // Coarse SQL prefilter on local digits, then exact normalized match in
        // PHP (booking contact phone is stored raw/unnormalized).
        if ($phone) {
            foreach ($this->guestBookingCandidates($phone)->get(['id', 'contact']) as $b) {
                if ($this->normalize((string) data_get($b->contact, 'phone')) === $phone) {
                    $ids[] = $b->id;
                }
            }
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Fetch a booking BY reference but only if the conversation owns it. Returns
     * null when the booking does not exist or is not owned — the AI cannot
     * probe arbitrary references.
     */
    public function ownedBooking(WhatsAppConversation $conversation, string $reference): ?Booking
    {
        $owned = $this->ownedBookingIds($conversation);
        if (empty($owned)) {
            return null;
        }

        return Booking::where('booking_reference', trim($reference))
            ->whereIn('id', $owned)
            ->first();
    }

    /** Resolve the display name for greetings (never leaks another customer). */
    public function displayName(WhatsAppConversation $conversation): ?string
    {
        if ($conversation->verified_user_id && $conversation->verifiedUser) {
            return $conversation->verifiedUser->name;
        }
        if ($conversation->verified_contact_id && $conversation->verifiedContact) {
            return $conversation->verifiedContact->name;
        }

        return $conversation->profile_name;
    }

    // --- internals -------------------------------------------------------

    private function markVerified(WhatsAppConversation $conversation, ?User $user, ?Contact $contact): void
    {
        $conversation->forceFill([
            'verified_user_id' => $user?->id,
            'verified_contact_id' => $contact?->id,
            'verified_at' => now(),
        ])->save();
    }

    private function findUserByPhone(string $normalizedPhone): ?User
    {
        // User.phone may be stored raw/formatted; match on the trailing local
        // digits to be resilient, then confirm by re-normalizing.
        $local = $this->localDigits($normalizedPhone);

        return User::query()
            ->whereNotNull('phone')
            ->where('phone', 'like', '%' . $local)
            ->get(['id', 'name', 'phone'])
            ->first(fn (User $u) => $this->normalize($u->phone) === $normalizedPhone);
    }

    /**
     * Candidate guest bookings whose stored contact JSON phone MIGHT match the
     * given normalized phone. Coarse SQL prefilter on the local digits only —
     * callers MUST confirm with an exact normalized match in PHP, since the
     * booking contact phone is stored raw/unnormalized.
     */
    private function guestBookingCandidates(string $normalizedPhone)
    {
        $local = $this->localDigits($normalizedPhone);

        return Booking::query()
            ->whereNull('user_id')
            ->where('contact->phone', 'like', '%' . $local . '%');
    }

    private function bookingBelongsToPhone(Booking $booking, string $normalizedPhone): bool
    {
        if ($booking->user_id) {
            $user = User::find($booking->user_id);
            if ($user && $this->normalize($user->phone) === $normalizedPhone) {
                return true;
            }
        }

        $bookingPhone = $this->normalize((string) data_get($booking->contact, 'phone'));

        return $bookingPhone !== null && $bookingPhone === $normalizedPhone;
    }

    /** Last 10 digits (local part) used for coarse LIKE prefiltering. */
    private function localDigits(string $normalizedPhone): string
    {
        return substr($normalizedPhone, -10);
    }
}
