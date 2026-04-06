<?php

namespace App\Mail;

use App\Models\SaasCheckoutOrder;
use App\Models\SaasLicense;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LicenseKeyDelivered extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SaasCheckoutOrder $order,
        public SaasLicense $license,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->order->locale === 'tr'
            ? 'Hostvim lisans anahtarınız'
            : 'Your Hostvim license key';

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.license-key-delivered',
        );
    }
}
