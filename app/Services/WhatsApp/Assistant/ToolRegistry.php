<?php

namespace App\Services\WhatsApp\Assistant;

/**
 * Central registry of the tools exposed to the AI assistant. Keeps the
 * provider-agnostic JSON-Schema tool definitions in one place, plus the
 * name -> CustomerTools method map used to dispatch a model tool call.
 *
 * `verify_identity` is handled specially by the orchestrator (it calls the
 * resolver, not CustomerTools) and is therefore not in MAP.
 */
class ToolRegistry
{
    /** Tool name => CustomerTools method. */
    public const MAP = [
        'get_my_profile' => 'getMyProfile',
        'get_my_bookings' => 'getMyBookings',
        'get_my_upcoming_bookings' => 'getMyUpcomingBookings',
        'get_my_past_bookings' => 'getMyPastBookings',
        'get_booking_details' => 'getBookingDetails',
        'get_booking_hotel' => 'getBookingHotel',
        'get_booking_flight' => 'getBookingFlight',
        'get_booking_cab' => 'getBookingCab',
        'get_booking_payment_status' => 'getBookingPaymentStatus',
        'get_booking_invoice' => 'getBookingInvoice',
        'get_booking_receipt' => 'getBookingReceipt',
        'get_booking_itinerary' => 'getBookingItinerary',
        'get_booking_documents' => 'getBookingDocuments',
        'get_cancellation_policy' => 'getCancellationPolicy',
        'request_booking_cancellation' => 'requestBookingCancellation',
        'request_booking_modification' => 'requestBookingModification',
        'get_payment_link' => 'getPaymentLink',
        'request_human_agent' => 'requestHumanAgent',
        'search_packages' => 'searchPackages',
    ];

    /**
     * Provider-agnostic tool definitions. Each: name, description, parameters
     * (JSON Schema). booking_reference is optional everywhere — when omitted and
     * the customer owns exactly one booking, the backend uses that one; with
     * several it asks the customer to choose.
     */
    public static function definitions(): array
    {
        $bookingRef = [
            'type' => 'object',
            'properties' => [
                'booking_reference' => [
                    'type' => 'string',
                    'description' => 'The booking reference (e.g. KB10245). Optional — omit if the customer did not specify and let the backend resolve.',
                ],
            ],
        ];

        return [
            [
                'name' => 'verify_identity',
                'description' => 'Verify the customer by a booking reference tied to their WhatsApp number. Call this when an unverified customer wants their booking/payment/document data. The backend confirms the reference belongs to this number.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'booking_reference' => ['type' => 'string', 'description' => 'A booking reference the customer provides.'],
                    ],
                    'required' => ['booking_reference'],
                ],
            ],
            [
                'name' => 'get_my_profile',
                'description' => 'Get the verified customer\'s own profile (name, email, phone on file, preferred language).',
                'parameters' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'get_my_bookings',
                'description' => 'List all bookings belonging to the verified customer (reference, title, destination, travel date, status, total).',
                'parameters' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'get_my_upcoming_bookings',
                'description' => 'List the verified customer\'s upcoming/confirmed trips, nearest travel date first.',
                'parameters' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'get_my_past_bookings',
                'description' => 'List the verified customer\'s previous (completed/cancelled) bookings.',
                'parameters' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'get_booking_details',
                'description' => 'Full details of one owned booking: status, destination, travel dates, travellers, hotel/flight/cab summary, total/paid/pending.',
                'parameters' => $bookingRef,
            ],
            [
                'name' => 'get_booking_hotel',
                'description' => 'Hotel details for an owned booking (name, address, check-in/out, room type, meal plan, confirmation number). Stored booking info.',
                'parameters' => $bookingRef,
            ],
            [
                'name' => 'get_booking_flight',
                'description' => 'Flight details for an owned booking (airline, flight number, route, date, PNR). STORED info only — not live status.',
                'parameters' => $bookingRef,
            ],
            [
                'name' => 'get_booking_cab',
                'description' => 'Cab details for an owned booking (vehicle, pickup/drop, pickup date/time, driver if assigned).',
                'parameters' => $bookingRef,
            ],
            [
                'name' => 'get_booking_payment_status',
                'description' => 'Payment summary for an owned booking: total, paid, pending, status, last payment. Never implies success unless status says paid.',
                'parameters' => $bookingRef,
            ],
            [
                'name' => 'get_booking_invoice',
                'description' => 'Send the invoice PDF for an owned booking over WhatsApp.',
                'parameters' => $bookingRef,
            ],
            [
                'name' => 'get_booking_receipt',
                'description' => 'Send the payment receipt/invoice PDF for an owned booking over WhatsApp.',
                'parameters' => $bookingRef,
            ],
            [
                'name' => 'get_booking_itinerary',
                'description' => 'Send the itinerary PDF for an owned booking over WhatsApp.',
                'parameters' => $bookingRef,
            ],
            [
                'name' => 'get_booking_documents',
                'description' => 'Send all documents (invoice, itinerary, vouchers, tickets) for an owned booking over WhatsApp.',
                'parameters' => $bookingRef,
            ],
            [
                'name' => 'get_cancellation_policy',
                'description' => 'Get the cancellation policy and whether an owned booking is cancellable. Call this BEFORE requesting a cancellation.',
                'parameters' => $bookingRef,
            ],
            [
                'name' => 'request_booking_cancellation',
                'description' => 'Raise a cancellation REQUEST for an owned booking. Only pass confirm=true after the customer explicitly confirms. Never claim the booking is cancelled — our team reviews it.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'booking_reference' => ['type' => 'string', 'description' => 'The booking reference to cancel.'],
                        'confirm' => ['type' => 'boolean', 'description' => 'true only after explicit customer confirmation.'],
                    ],
                ],
            ],
            [
                'name' => 'request_booking_modification',
                'description' => 'Record a modification request (change hotel/dates, add rooms, etc.) for an owned booking. Our team checks availability and cost before any change; never confirm the change is done.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'booking_reference' => ['type' => 'string', 'description' => 'The booking reference to modify.'],
                        'requested_change' => ['type' => 'string', 'description' => 'What the customer wants to change, in their words.'],
                    ],
                ],
            ],
            [
                'name' => 'get_payment_link',
                'description' => 'Get the secure payment link and pending amount for an owned booking that has a pending balance.',
                'parameters' => $bookingRef,
            ],
            [
                'name' => 'request_human_agent',
                'description' => 'Hand the conversation over to a human travel consultant. Pauses the assistant.',
                'parameters' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'search_packages',
                'description' => 'Search the public travel package catalog for new trip inquiries. No verification needed. Returns indicative starting prices only.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Free-text search (package name/keywords).'],
                        'destination' => ['type' => 'string', 'description' => 'Destination name filter, e.g. Kashmir.'],
                    ],
                ],
            ],
        ];
    }
}
