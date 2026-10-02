<?php

namespace App\Mail;

use App\Models\CrmQuotation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CrmQuotationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CrmQuotation $quotation)
    {
        $this->quotation->loadMissing(['lead.assignee', 'package.itineraries', 'package.destination']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Travel Quotation: {$this->quotation->title} [{$this->quotation->quotation_number}] - Leemroz Travels"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.crm_quotation',
            with: [
                'quotation' => $this->quotation,
                'lead' => $this->quotation->lead,
                'package' => $this->quotation->package,
                'agent' => $this->quotation->lead?->assignee,
            ]
        );
    }

    public function attachments(): array
    {
        try {
            // Use PdfDocumentService which has output-buffer protection.
            // Direct Pdf::loadView() inside a Mailable can leak DomPDF's internal
            // output buffer, corrupting the Symfony mailer transport and causing
            // the attachment to silently fail.
            $pdfBinary = app(\App\Services\PdfDocumentService::class)->quotation($this->quotation);

            return [
                \Illuminate\Mail\Mailables\Attachment::fromData(
                    fn () => $pdfBinary,
                    "Quotation-{$this->quotation->quotation_number}.pdf"
                )->withMime('application/pdf'),
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Could not attach quotation PDF to email: ' . $e->getMessage(), [
                'quotation' => $this->quotation->quotation_number ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }
}
