<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\ReceiptService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class OrderConfirmationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Order Confirmed — Le Miting',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-confirmation',
            with: [
                'receiptUrl' => URL::signedRoute(
                    'orders.receipt',
                    ['order' => $this->order->id],
                    now()->addHours(24),
                ),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn (): string => app(ReceiptService::class)->generateReceipt($this->order)->output(),
                'receipt-'.$this->order->order_number.'.pdf',
            )->withMime('application/pdf'),
        ];
    }
}
