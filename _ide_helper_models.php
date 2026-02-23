<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property string $email
 * @property string|null $confirmed
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailSubscriptions newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailSubscriptions newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailSubscriptions query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailSubscriptions whereConfirmed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailSubscriptions whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailSubscriptions whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailSubscriptions whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailSubscriptions whereUpdatedAt($value)
 */
	class EmailSubscriptions extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $created_by
 * @property string|null $supplier
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $paid_on
 * @property numeric $total_amount
 * @property numeric $gst_amount
 * @property string|null $receipt_document_path
 * @property string|null $receipt_document_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $creator
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereGstAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense wherePaidOn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereReceiptDocumentName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereReceiptDocumentPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereSupplier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereUpdatedAt($value)
 */
	class Expense extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $invoice_number
 * @property string|null $user_id
 * @property string|null $billing_name
 * @property string|null $billing_email
 * @property string|null $billing_phone
 * @property string $status
 * @property \Illuminate\Support\Carbon $issue_date
 * @property \Illuminate\Support\Carbon|null $issued_at
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property string|null $purchase_order_number
 * @property numeric $subtotal_amount
 * @property numeric $gst_amount
 * @property numeric $total_amount
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvoicePaymentAllocation> $allocations
 * @property-read int|null $allocations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvoiceLine> $lines
 * @property-read int|null $lines_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read int|null $payments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TaxAdjustment> $taxAdjustments
 * @property-read int|null $tax_adjustments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ticket> $tickets
 * @property-read int|null $tickets_count
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereBillingEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereBillingName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereBillingPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereGstAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereInvoiceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereIssueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereIssuedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice wherePurchaseOrderNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereSubtotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereUserId($value)
 */
	class Invoice extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $invoice_id
 * @property int $line_number
 * @property string $kind
 * @property string $description
 * @property string|null $notes
 * @property array<array-key, mixed>|null $details_json
 * @property numeric $quantity
 * @property numeric $unit_price_ex_tax
 * @property numeric $tax_rate
 * @property numeric $line_total_ex_tax
 * @property numeric $tax_amount
 * @property numeric $line_total_inc_tax
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int|null $original_invoice_line_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Invoice $invoice
 * @property-read InvoiceLine|null $originalLine
 * @property-read \Illuminate\Database\Eloquent\Collection<int, InvoiceLine> $reversalLines
 * @property-read int|null $reversal_lines_count
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $source
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereDetailsJson($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereKind($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereLineNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereLineTotalExTax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereLineTotalIncTax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereOriginalInvoiceLineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereSourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereSourceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereTaxAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereTaxRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereUnitPriceExTax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereUpdatedAt($value)
 */
	class InvoiceLine extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $payment_id
 * @property int $invoice_id
 * @property int|null $tax_adjustment_id
 * @property numeric $allocated_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Payment $customerPayment
 * @property-read \App\Models\Invoice $invoice
 * @property-read \App\Models\TaxAdjustment|null $taxAdjustment
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePaymentAllocation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePaymentAllocation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePaymentAllocation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePaymentAllocation whereAllocatedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePaymentAllocation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePaymentAllocation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePaymentAllocation whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePaymentAllocation wherePaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePaymentAllocation whereTaxAdjustmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePaymentAllocation whereUpdatedAt($value)
 */
	class InvoicePaymentAllocation extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $name
 * @property string|null $address
 * @property string|null $address_url
 * @property string|null $url
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\LocationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location whereAddressUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location whereUrl($value)
 */
	class Location extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $name
 * @property string $title
 * @property string|null $hash
 * @property string $mime_type
 * @property int $size
 * @property array<array-key, mixed>|null $variants
 * @property string $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $password
 * @property string $status
 * @property-read string $file_type
 * @property-read string $thumbnail
 * @property-read string $url
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $mediable
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\MediaFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereMimeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereVariants($value)
 */
	class Media extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $kind
 * @property int|null $refund_of_payment_id
 * @property string|null $user_id
 * @property string|null $created_by
 * @property \Illuminate\Support\Carbon|null $received_on
 * @property string|null $payment_method
 * @property string|null $reference
 * @property numeric $total_amount
 * @property numeric $gst_amount
 * @property string|null $notes
 * @property string|null $gateway_provider
 * @property string|null $gateway_status
 * @property string|null $gateway_reference_id
 * @property string|null $square_payment_id
 * @property string|null $square_order_id
 * @property string|null $square_location_id
 * @property string|null $square_receipt_url
 * @property string|null $square_card_brand
 * @property string|null $square_card_last4
 * @property int|null $square_paid_money_amount
 * @property int $square_refunded_money_amount
 * @property \Illuminate\Support\Carbon|null $square_gateway_created_at
 * @property \Illuminate\Support\Carbon|null $square_gateway_updated_at
 * @property string|null $square_last_event_type
 * @property string|null $square_last_event_id
 * @property \Illuminate\Support\Carbon|null $square_last_event_at
 * @property array<array-key, mixed>|null $square_webhook_payload
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvoicePaymentAllocation> $allocations
 * @property-read int|null $allocations_count
 * @property-read \App\Models\User|null $creator
 * @property-read int $square_remaining_refundable_money
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property-read int|null $invoices_count
 * @property-read Payment|null $refundOf
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Payment> $refunds
 * @property-read int|null $refunds_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SquareWebhookEvent> $squareWebhookEvents
 * @property-read int|null $square_webhook_events_count
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereGatewayProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereGatewayReferenceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereGatewayStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereGstAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereKind($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereReceivedOn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereRefundOfPaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereSquareCardBrand($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereSquareCardLast4($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereSquareGatewayCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereSquareGatewayUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereSquareLastEventAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereSquareLastEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereSquareLastEventType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereSquareLocationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereSquareOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereSquarePaidMoneyAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereSquarePaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereSquareReceiptUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereSquareRefundedMoneyAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereSquareWebhookPayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereUserId($value)
 */
	class Payment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $hero_media_name
 * @property string $status
 * @property string|null $published_at
 * @property string $title
 * @property string $content
 * @property string $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $author
 * @property-read string $slug
 * @property-read \App\Models\Media $hero
 * @method static \Database\Factories\PostFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereHeroMediaName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post wherePublishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereUserId($value)
 */
	class Post extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $quote_number
 * @property string|null $user_id
 * @property \Illuminate\Support\Carbon $quote_date
 * @property string|null $purchase_order_number
 * @property string|null $title
 * @property string|null $description
 * @property array<array-key, mixed>|null $line_items
 * @property numeric $subtotal_amount
 * @property numeric $gst_amount
 * @property numeric $total_amount
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereGstAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereLineItems($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote wherePurchaseOrderNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereQuoteDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereQuoteNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereSubtotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereUserId($value)
 */
	class Quote extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $recipient
 * @property string $mailable_class
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SentEmail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SentEmail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SentEmail query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SentEmail whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SentEmail whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SentEmail whereMailableClass($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SentEmail whereRecipient($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SentEmail whereUpdatedAt($value)
 */
	class SentEmail extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Support\HtmlString $value_to_html
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteOption newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteOption newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteOption query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteOption whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteOption whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteOption whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteOption whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteOption whereValue($value)
 */
	class SiteOption extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $invoice_id
 * @property int|null $tax_adjustment_id
 * @property int|null $ticket_id
 * @property int|null $payment_id
 * @property string $idempotency_key
 * @property int $requested_cents
 * @property int $refunded_cents
 * @property string|null $square_refund_id
 * @property string $status
 * @property string|null $failure_message
 * @property array<array-key, mixed>|null $payload
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\TaxAdjustment|null $adjustmentNote
 * @property-read \App\Models\Payment|null $customerPayment
 * @property-read \App\Models\Invoice|null $invoice
 * @property-read \App\Models\Ticket|null $ticket
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareRefundOperation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareRefundOperation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareRefundOperation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareRefundOperation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareRefundOperation whereFailureMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareRefundOperation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareRefundOperation whereIdempotencyKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareRefundOperation whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareRefundOperation wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareRefundOperation wherePaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareRefundOperation whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareRefundOperation whereRefundedCents($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareRefundOperation whereRequestedCents($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareRefundOperation whereSquareRefundId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareRefundOperation whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareRefundOperation whereTaxAdjustmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareRefundOperation whereTicketId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareRefundOperation whereUpdatedAt($value)
 */
	class SquareRefundOperation extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $event_id
 * @property string|null $event_type
 * @property int|null $payment_id
 * @property array<array-key, mixed>|null $payload
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Payment|null $customerPayment
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareWebhookEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareWebhookEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareWebhookEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareWebhookEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareWebhookEvent whereEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareWebhookEvent whereEventType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareWebhookEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareWebhookEvent wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareWebhookEvent wherePaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareWebhookEvent whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SquareWebhookEvent whereUpdatedAt($value)
 */
	class SquareWebhookEvent extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $invoice_id
 * @property string $adjustment_number
 * @property \Illuminate\Support\Carbon|null $issue_date
 * @property numeric $subtotal_amount
 * @property numeric $gst_amount
 * @property numeric $total_amount
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvoicePaymentAllocation> $allocations
 * @property-read int|null $allocations_count
 * @property-read \App\Models\Invoice $invoice
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TaxAdjustmentLine> $lines
 * @property-read int|null $lines_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustment whereAdjustmentNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustment whereGstAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustment whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustment whereIssueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustment whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustment whereSubtotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustment whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustment whereUpdatedAt($value)
 */
	class TaxAdjustment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $tax_adjustment_id
 * @property int|null $invoice_line_id
 * @property int $line_number
 * @property string $description
 * @property string|null $notes
 * @property numeric $quantity
 * @property numeric $unit_price_ex_tax
 * @property numeric $tax_rate
 * @property numeric $line_total_ex_tax
 * @property numeric $tax_amount
 * @property numeric $line_total_inc_tax
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\InvoiceLine|null $invoiceLine
 * @property-read \App\Models\TaxAdjustment $taxAdjustment
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustmentLine newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustmentLine newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustmentLine query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustmentLine whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustmentLine whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustmentLine whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustmentLine whereInvoiceLineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustmentLine whereLineNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustmentLine whereLineTotalExTax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustmentLine whereLineTotalIncTax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustmentLine whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustmentLine whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustmentLine whereTaxAdjustmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustmentLine whereTaxAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustmentLine whereTaxRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustmentLine whereUnitPriceExTax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxAdjustmentLine whereUpdatedAt($value)
 */
	class TaxAdjustmentLine extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $reference_code
 * @property int $status
 * @property string|null $user_id
 * @property string $workshop_id
 * @property int|null $invoice_id
 * @property int|null $invoice_line_id
 * @property int|null $reissued_to_ticket_id
 * @property int|null $reissued_from_ticket_id
 * @property string|null $firstname
 * @property string|null $surname
 * @property string|null $email
 * @property string|null $phone
 * @property \Illuminate\Support\Carbon|null $attended_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $customer_status_label
 * @property-read string $status_label
 * @property-read \App\Models\Invoice|null $invoice
 * @property-read \App\Models\InvoiceLine|null $invoiceLine
 * @property-read Ticket|null $reissuedFromTicket
 * @property-read Ticket|null $reissuedToTicket
 * @property-read \App\Models\User|null $user
 * @property-read \App\Models\Workshop $workshop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereAttendedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereFirstname($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereInvoiceLineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereReferenceCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereReissuedFromTicketId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereReissuedToTicketId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereSurname($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereWorkshopId($value)
 */
	class Ticket extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $user_id
 * @property string $type
 * @property array<array-key, mixed>|null $data
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property string $created_at
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereUserId($value)
 */
	class Token extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property int $admin
 * @property string|null $firstname
 * @property string|null $surname
 * @property string|null $company
 * @property string|null $phone
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string|null $remember_token
 * @property string|null $shipping_address
 * @property string|null $shipping_address2
 * @property string|null $shipping_city
 * @property string|null $shipping_state
 * @property string|null $shipping_postcode
 * @property string|null $shipping_country
 * @property string|null $billing_address
 * @property string|null $billing_address2
 * @property string|null $billing_city
 * @property string|null $billing_state
 * @property string|null $billing_postcode
 * @property string|null $billing_country
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $tfa_secret
 * @property int $agree_tos
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserBackupCode> $backupCodes
 * @property-read int|null $backup_codes_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $createdPayments
 * @property-read int|null $created_payments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Expense> $expenses
 * @property-read int|null $expenses_count
 * @property-read mixed $email_update_pending
 * @property mixed $subscribed
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property-read int|null $invoices_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read int|null $payments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Quote> $quotes
 * @property-read int|null $quotes_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ticket> $tickets
 * @property-read int|null $tickets_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Token> $tokens
 * @property-read int|null $tokens_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAdmin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAgreeTos($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBillingAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBillingAddress2($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBillingCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBillingCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBillingPostcode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBillingState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCompany($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFirstname($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereShippingAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereShippingAddress2($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereShippingCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereShippingCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereShippingPostcode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereShippingState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSurname($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTfaSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 */
	class User extends \Eloquent implements \Illuminate\Contracts\Auth\MustVerifyEmail {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $user_id
 * @property string $code
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBackupCode newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBackupCode newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBackupCode query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBackupCode whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBackupCode whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBackupCode whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBackupCode whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBackupCode whereUserId($value)
 */
	class UserBackupCode extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $title
 * @property string $hero_media_name
 * @property string $content
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property \Illuminate\Support\Carbon|null $publish_at
 * @property \Illuminate\Support\Carbon|null $closes_at
 * @property string $status
 * @property string|null $price
 * @property string|null $ages
 * @property string $registration
 * @property string|null $registration_data
 * @property int|null $max_tickets
 * @property string|null $location_id
 * @property string $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WorkshopAttendance> $attendances
 * @property-read int|null $attendances_count
 * @property-read \App\Models\User $author
 * @property-read string $slug
 * @property-read \App\Models\Media $hero
 * @property-read \App\Models\Location|null $location
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ticket> $tickets
 * @property-read int|null $tickets_count
 * @method static \Database\Factories\WorkshopFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workshop newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workshop newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workshop query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workshop whereAges($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workshop whereClosesAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workshop whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workshop whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workshop whereEndsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workshop whereHeroMediaName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workshop whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workshop whereLocationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workshop whereMaxTickets($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workshop wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workshop wherePublishAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workshop whereRegistration($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workshop whereRegistrationData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workshop whereStartsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workshop whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workshop whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workshop whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workshop whereUserId($value)
 */
	class Workshop extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $workshop_id
 * @property int|null $ticket_id
 * @property string|null $user_id
 * @property string|null $created_by
 * @property string $source
 * @property string|null $firstname
 * @property string|null $surname
 * @property string|null $email
 * @property string|null $phone
 * @property \Illuminate\Support\Carbon|null $attended_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Ticket|null $ticket
 * @property-read \App\Models\User|null $user
 * @property-read \App\Models\Workshop $workshop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkshopAttendance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkshopAttendance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkshopAttendance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkshopAttendance whereAttendedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkshopAttendance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkshopAttendance whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkshopAttendance whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkshopAttendance whereFirstname($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkshopAttendance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkshopAttendance wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkshopAttendance whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkshopAttendance whereSurname($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkshopAttendance whereTicketId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkshopAttendance whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkshopAttendance whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkshopAttendance whereWorkshopId($value)
 */
	class WorkshopAttendance extends \Eloquent {}
}

