<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketManualRefundNotice extends Mailable
{
    use Queueable, SerializesModels;

    public string $recipientName;

    public string $ticketReference;

    public string $workshopTitle;

    public string $invoiceNumber;

    public string $customerName;

    public string $customerEmail;

    public ?float $refundAmount;

    public string $creditsUrl;

    public string $invoiceUrl;

    public string $ticketUrl;

    /**
     * @var array<int, array{
     *     operation_id:int,
     *     status:string,
     *     requested_amount:float,
     *     refunded_amount:float,
     *     failure_message:string,
     *     payment_id:?int,
     *     payment_edit_url:?string,
     *     ticket_reference:?string,
     *     invoice_number:?string,
     *     workshop_title:string
     * }>
     */
    public array $operationSummaries;

    public string $introLine;

    public function __construct(
        string $recipientName,
        string $ticketReference,
        string $workshopTitle,
        string $invoiceNumber,
        string $customerName,
        string $customerEmail,
        ?float $refundAmount,
        string $creditsUrl,
        string $invoiceUrl,
        string $ticketUrl,
        array $operationSummaries = [],
        string $introLine = 'The following ticket has been cancelled.'
    ) {
        $this->recipientName = $recipientName;
        $this->ticketReference = $ticketReference;
        $this->workshopTitle = $workshopTitle;
        $this->invoiceNumber = $invoiceNumber;
        $this->customerName = $customerName;
        $this->customerEmail = $customerEmail;
        $this->refundAmount = $refundAmount !== null ? round(max(0, $refundAmount), 2) : null;
        $this->creditsUrl = $creditsUrl;
        $this->invoiceUrl = $invoiceUrl;
        $this->ticketUrl = $ticketUrl;
        $this->operationSummaries = $operationSummaries;
        $this->introLine = trim($introLine) !== '' ? trim($introLine) : 'The following ticket has been cancelled.';
    }

    public function build(): static
    {
        $mail = $this
            ->subject('Manual refund required for ticket '.$this->ticketReference)
            ->markdown('emails.ticket-manual-refund-notice');

        return $mail;
    }
}
