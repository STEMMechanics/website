<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StoreLowStockAdminAlert extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{
     *     title:string,
     *     product_type_label:string,
     *     available:int|null,
     *     low_stock_threshold:int,
     *     awaiting:int,
     *     reserved:int,
     *     backorder:int,
     *     preorder:int,
     *     edit_url:string,
     *     notes_excerpt:string|null
     * }>  $products
     */
    public function __construct(
        public array $products,
    ) {}

    public function build(): static
    {
        $fromAddress = trim((string) config('mail.from.address', ''));
        $fromName = trim((string) config('mail.from.name', ''));
        $count = count($this->products);
        $subject = $count === 1
            ? 'Low stock warning: 1 store product needs attention'
            : 'Low stock warning: '.$count.' store products need attention';

        $mail = $this
            ->subject($subject)
            ->markdown('emails.store-low-stock-admin-alert');

        if ($fromAddress !== '') {
            $mail->from($fromAddress, $fromName !== '' ? $fromName : null);
        }

        return $mail;
    }
}
