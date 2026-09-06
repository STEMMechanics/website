<?php

// Explicit public list fields only: never infer filters from database columns.
$lists = [
    'admin.user.index' => ['model' => \App\Models\User::class, 'fields' => [
        'firstname' => ['label' => 'Name', 'type' => 'text'],
        'surname' => ['label' => 'Surname', 'type' => 'text'],
        'email' => ['label' => 'Email', 'type' => 'text'],
        'phone' => ['label' => 'Phone', 'type' => 'text'],
        'created_at' => ['label' => 'Created', 'type' => 'date'],
    ]],
    'admin.location.index' => ['model' => \App\Models\Location::class, 'fields' => [
        'name' => ['label' => 'Name', 'type' => 'text'],
        'address' => ['label' => 'Address', 'type' => 'text'],
        'suburb' => ['label' => 'Suburb', 'type' => 'text'],
        'state' => ['label' => 'State', 'type' => 'text'],
        'postcode' => ['label' => 'Postcode', 'type' => 'text'],
    ]],
    'admin.organisation.index' => ['model' => \App\Models\Organisation::class, 'fields' => [
        'name' => ['label' => 'Name', 'type' => 'text'],
        'type' => ['label' => 'Type', 'type' => 'text'],
        'billing_city' => ['label' => 'City', 'type' => 'text'],
        'created_at' => ['label' => 'Created', 'type' => 'date'],
    ]],
    'admin.shop.product.index' => ['model' => \App\Models\Product::class, 'fields' => [
        'title' => ['label' => 'Product', 'type' => 'text'],
        'sku' => ['label' => 'SKU', 'type' => 'text'],
        'status' => ['label' => 'Status', 'type' => 'text'],
        'product_type' => ['label' => 'Type', 'type' => 'text'],
        'price' => ['label' => 'Price', 'type' => 'number'],
        'created_at' => ['label' => 'Created', 'type' => 'date'],
    ]],
    'admin.shop.category.index' => ['model' => \App\Models\ProductCategory::class, 'fields' => [
        'name' => ['label' => 'Name', 'type' => 'text'],
        'slug' => ['label' => 'Slug', 'type' => 'text'],
        'sort_order' => ['label' => 'Order', 'type' => 'number'],
    ]],
    'admin.shop.coupon.index' => ['model' => \App\Models\Coupon::class, 'fields' => [
        'code' => ['label' => 'Code', 'type' => 'text'],
        'discount_type' => ['label' => 'Type', 'type' => 'text'],
        'created_at' => ['label' => 'Created', 'type' => 'date'],
    ]],
    'admin.shop.order.index' => ['model' => \App\Models\StoreOrder::class, 'fields' => [
        'order_number' => ['label' => 'Order', 'type' => 'text'],
        'status' => ['label' => 'Status', 'type' => 'text'],
        'total_amount' => ['label' => 'Total', 'type' => 'number'],
        'created_at' => ['label' => 'Created', 'type' => 'date'],
    ]],
    'account.order.index' => ['model' => \App\Models\StoreOrder::class, 'fields' => [
        'order_number' => ['label' => 'Order', 'type' => 'text'],
        'status' => ['label' => 'Status', 'type' => 'text'],
        'total_amount' => ['label' => 'Total', 'type' => 'number'],
        'created_at' => ['label' => 'Created', 'type' => 'date'],
    ]],
    'admin.invoice.index' => ['model' => \App\Models\Invoice::class, 'fields' => [
        'invoice_number' => ['label' => 'Invoice', 'type' => 'text'],
        'status' => ['label' => 'Status', 'type' => 'text'],
        'issue_date' => ['label' => 'Issued', 'type' => 'date'],
        'due_date' => ['label' => 'Due', 'type' => 'date'],
        'total_amount' => ['label' => 'Total', 'type' => 'number'],
    ]],
    'account.invoice.index' => ['model' => \App\Models\Invoice::class, 'fields' => [
        'invoice_number' => ['label' => 'Invoice', 'type' => 'text'],
        'status' => ['label' => 'Status', 'type' => 'text'],
        'issue_date' => ['label' => 'Issued', 'type' => 'date'],
        'due_date' => ['label' => 'Due', 'type' => 'date'],
        'total_amount' => ['label' => 'Total', 'type' => 'number'],
    ]],
    'admin.quote.index' => ['model' => \App\Models\Quote::class, 'fields' => [
        'quote_number' => ['label' => 'Quote', 'type' => 'text'],
        'title' => ['label' => 'Title', 'type' => 'text'],
        'status' => ['label' => 'Status', 'type' => 'text'],
        'quote_date' => ['label' => 'Date', 'type' => 'date'],
        'valid_until' => ['label' => 'Valid until', 'type' => 'date'],
        'total_amount' => ['label' => 'Total', 'type' => 'number'],
    ]],
    'account.quote.index' => ['model' => \App\Models\Quote::class, 'fields' => [
        'quote_number' => ['label' => 'Quote', 'type' => 'text'],
        'title' => ['label' => 'Title', 'type' => 'text'],
        'status' => ['label' => 'Status', 'type' => 'text'],
        'quote_date' => ['label' => 'Date', 'type' => 'date'],
        'valid_until' => ['label' => 'Valid until', 'type' => 'date'],
        'total_amount' => ['label' => 'Total', 'type' => 'number'],
    ]],
    'admin.payment.index' => ['model' => \App\Models\Payment::class, 'fields' => [
        'reference' => ['label' => 'Reference', 'type' => 'text'],
        'payment_method' => ['label' => 'Method', 'type' => 'text'],
        'received_on' => ['label' => 'Received', 'type' => 'date'],
        'total_amount' => ['label' => 'Amount', 'type' => 'number'],
    ]],
    'account.payment.index' => ['model' => \App\Models\Payment::class, 'fields' => [
        'reference' => ['label' => 'Reference', 'type' => 'text'],
        'payment_method' => ['label' => 'Method', 'type' => 'text'],
        'received_on' => ['label' => 'Received', 'type' => 'date'],
        'total_amount' => ['label' => 'Amount', 'type' => 'number'],
    ]],
    'account.invoice.receipts' => ['model' => \App\Models\Payment::class, 'fields' => [
        'reference' => ['label' => 'Reference', 'type' => 'text'],
        'payment_method' => ['label' => 'Method', 'type' => 'text'],
        'received_on' => ['label' => 'Received', 'type' => 'date'],
        'total_amount' => ['label' => 'Amount', 'type' => 'number'],
    ]],
    'admin.expense.index' => ['model' => \App\Models\Expense::class, 'fields' => [
        'supplier' => ['label' => 'Supplier', 'type' => 'text'],
        'description' => ['label' => 'Description', 'type' => 'text'],
        'paid_on' => ['label' => 'Paid', 'type' => 'date'],
        'total_amount' => ['label' => 'Amount', 'type' => 'number'],
    ]],
    'admin.subscription.index' => ['model' => \App\Models\EmailSubscriptions::class, 'fields' => [
        'email' => ['label' => 'Email', 'type' => 'text'],
        'confirmed' => ['label' => 'Confirmed', 'type' => 'boolean'],
        'created_at' => ['label' => 'Subscribed', 'type' => 'date'],
    ]],
    'admin.site_option.index' => ['model' => \App\Models\SiteOption::class, 'fields' => [
        'name' => ['label' => 'Name', 'type' => 'text'],
    ]],
    'admin.pick-list-template.index' => ['model' => \App\Models\PickListTemplate::class, 'fields' => [
        'name' => ['label' => 'Name', 'type' => 'text'],
        'description' => ['label' => 'Description', 'type' => 'text'],
        'duration' => ['label' => 'Duration', 'type' => 'number'],
        'participants' => ['label' => 'Participants', 'type' => 'number'],
    ]],
    'admin.workshop-template.index' => ['model' => \App\Models\PickListTemplate::class, 'fields' => [
        'name' => ['label' => 'Name', 'type' => 'text'],
        'description' => ['label' => 'Description', 'type' => 'text'],
        'duration' => ['label' => 'Duration', 'type' => 'number'],
        'participants' => ['label' => 'Participants', 'type' => 'number'],
    ]],
    'admin.workshop-category.index' => ['model' => \App\Models\WorkshopCategory::class, 'fields' => [
        'name' => ['label' => 'Name', 'type' => 'text'],
        'slug' => ['label' => 'Slug', 'type' => 'text'],
        'hide_in_footer' => ['label' => 'Hidden in footer', 'type' => 'boolean'],
    ]],
    'admin.subscription.theme.index' => ['model' => \App\Models\NewsletterStoreTheme::class, 'fields' => [
        'name' => ['label' => 'Name', 'type' => 'text'],
        'title' => ['label' => 'Title', 'type' => 'text'],
        'is_active' => ['label' => 'Active', 'type' => 'boolean'],
        'sort_order' => ['label' => 'Order', 'type' => 'number'],
    ]],
    'admin.reminder.index' => ['model' => \App\Models\Reminder::class, 'fields' => [
        'subject' => ['label' => 'Subject', 'type' => 'text'],
        'recipient_email' => ['label' => 'Recipient', 'type' => 'text'],
        'status' => ['label' => 'Status', 'type' => 'text'],
        'scheduled_at' => ['label' => 'Scheduled', 'type' => 'date'],
        'sent_at' => ['label' => 'Sent', 'type' => 'date'],
    ]],
    'admin.workshop.history' => ['model' => \App\Models\Workshop::class, 'fields' => [
        'title' => ['label' => 'Workshop', 'type' => 'text'],
        'status' => ['label' => 'Status', 'type' => 'text'],
        'starts_at' => ['label' => 'Date', 'type' => 'date'],
        'price' => ['label' => 'Price', 'type' => 'number'],
    ]],
    'admin.workshop.index' => ['model' => \App\Models\Workshop::class, 'fields' => [
        'title' => ['label' => 'Workshop', 'type' => 'text'],
        'status' => ['label' => 'Status', 'type' => 'text', 'labels' => ['scheduled' => 'Opens Soon']],
        'starts_at' => ['label' => 'Date', 'type' => 'date'],
        'price' => ['label' => 'Price', 'type' => 'number'],
    ]],
    'account.media.index' => ['model' => \App\Models\Media::class, 'fields' => [
        'title' => ['label' => 'File', 'type' => 'text'],
        'name' => ['label' => 'Filename', 'type' => 'text'],
        'mime_type' => ['label' => 'Type', 'type' => 'text'],
        'size' => ['label' => 'Size', 'type' => 'number'],
        'created_at' => ['label' => 'Uploaded', 'type' => 'date'],
    ]],
    'account.ticket.index' => ['model' => \App\Models\Ticket::class, 'fields' => [
        'reference_code' => ['label' => 'Reference', 'type' => 'text'],
        'firstname' => ['label' => 'Name', 'type' => 'text'],
        'surname' => ['label' => 'Surname', 'type' => 'text'],
        'email' => ['label' => 'Email', 'type' => 'text'],
        'status' => ['label' => 'Status', 'type' => 'text'],
        'created_at' => ['label' => 'Created', 'type' => 'date'],
    ]],
    'admin.custom-page.index' => ['model' => \App\Models\CustomPage::class, 'fields' => [
        'title' => ['label' => 'Title', 'type' => 'text'],
        'path' => ['label' => 'Path', 'type' => 'text'],
        'is_published' => ['label' => 'Published', 'type' => 'boolean'],
    ]],
    'admin.ticket.index' => ['model' => \App\Models\Ticket::class, 'fields' => [
        'reference_code' => ['label' => 'Reference', 'type' => 'text'],
        'firstname' => ['label' => 'Name', 'type' => 'text'],
        'surname' => ['label' => 'Surname', 'type' => 'text'],
        'email' => ['label' => 'Email', 'type' => 'text'],
        'created_at' => ['label' => 'Created', 'type' => 'date'],
    ]],
    'admin.server.audit' => ['model' => \App\Models\AuditLog::class, 'fields' => [
        'event' => ['label' => 'Event', 'type' => 'text'],
        'created_at' => ['label' => 'Date', 'type' => 'date'],
    ]],
    'admin.server.sent-emails' => ['model' => \App\Models\SentEmail::class, 'fields' => [
        'recipient' => ['label' => 'Recipient', 'type' => 'text'],
        'status' => ['label' => 'Status', 'type' => 'text'],
        'sent_at' => ['label' => 'Sent', 'type' => 'date'],
        'created_at' => ['label' => 'Created', 'type' => 'date'],
    ]],
    'admin.server.sent-sms' => ['model' => \App\Models\SentSms::class, 'fields' => [
        'recipient' => ['label' => 'Recipient', 'type' => 'text'],
        'status' => ['label' => 'Status', 'type' => 'text'],
        'sent_at' => ['label' => 'Sent', 'type' => 'date'],
        'created_at' => ['label' => 'Created', 'type' => 'date'],
    ]],
    'admin.server.square-events' => ['model' => \App\Models\SquareWebhookEvent::class, 'fields' => [
        'event_type' => ['label' => 'Type', 'type' => 'text'],
        'created_at' => ['label' => 'Received', 'type' => 'date'],
        'processed_at' => ['label' => 'Processed', 'type' => 'date'],
    ]],
];

// Sort-only fields describe displayed columns without exposing extra filters.
$paymentAllocated = '(SELECT COALESCE(SUM(ipa.allocated_amount), 0) FROM invoice_payment_allocations ipa WHERE ipa.payment_id = payments.id)';
$paymentRefunds = '(SELECT COALESCE(SUM(r.total_amount), 0) FROM payments r WHERE r.refund_of_payment_id = payments.id)';
$paymentAvailable = "(payments.total_amount - {$paymentAllocated})";
$paymentUnallocated = "((CASE WHEN {$paymentAvailable} > 0 THEN {$paymentAvailable} ELSE 0 END) - {$paymentRefunds})";
foreach (['admin.payment.index', 'account.payment.index', 'account.invoice.receipts'] as $route) {
    $lists[$route]['fields'] += [
        'id' => ['label' => 'ID', 'type' => 'text', 'filter' => false],
        'kind' => ['label' => 'Payment type', 'type' => 'text', 'filter' => false],
        'clearance' => ['label' => 'Status', 'type' => 'text', 'filter' => false, 'sort_sql' => "CASE WHEN payments.kind = 'refund' OR payments.refund_of_payment_id IS NOT NULL THEN 'Refund' WHEN payments.kind = 'payment' AND payments.payment_method = 'bank_transfer' AND payments.cleared_at IS NULL THEN 'Pending clearance' ELSE 'Cleared' END"],
        'allocated' => ['label' => 'Allocated', 'type' => 'number', 'filter' => false, 'sort_sql' => $paymentAllocated],
        'unallocated' => ['label' => 'Unallocated', 'type' => 'number', 'filter' => false, 'sort_sql' => "CASE WHEN {$paymentUnallocated} > 0 THEN {$paymentUnallocated} ELSE 0 END"],
    ];
}
$lists['admin.shop.coupon.index']['fields'] += [
    'status' => ['label' => 'Status', 'type' => 'text'],
    'amount' => ['label' => 'Amount', 'type' => 'number'],
];
$lists['admin.custom-page.index']['fields']['updated_at'] = ['label' => 'Updated', 'type' => 'date'];
$lists['admin.server.sent-emails']['fields']['mailable_class'] = ['label' => 'Template', 'type' => 'text'];
$lists['admin.server.sent-emails']['fields']['id'] = ['label' => 'Record ID', 'type' => 'text', 'filter' => false];

$lists['admin.workshop.files'] = $lists['account.media.index'];
$lists['admin.workshop.photos'] = $lists['account.media.index'];
$lists['admin.workshop.tickets'] = $lists['account.ticket.index'];
$lists['admin.user.payments'] = $lists['account.payment.index'];
$lists['admin.server.square-webhooks'] = $lists['admin.server.square-events'];
$lists['admin.payment.refunds'] = ['model' => \App\Models\SquareRefundOperation::class, 'fields' => [
    'status' => ['label' => 'Status', 'type' => 'text'],
    'created_at' => ['label' => 'Created', 'type' => 'date'],
]];
$lists['admin.workshop.attendance'] = $lists['account.ticket.index'];
$lists['admin.workshop.coverage'] = ['model' => \App\Models\Workshop::class, 'fields' => ['title' => ['label' => 'Workshop', 'type' => 'text']]];
$lists['admin.server.backups'] = ['fields' => [
    'filename' => ['label' => 'File', 'type' => 'text'],
    'size' => ['label' => 'Size', 'type' => 'number'],
    'modified_at' => ['label' => 'Modified', 'type' => 'date'],
]];
$lists['tickets.invoice.receipts'] = $lists['account.invoice.receipts'];
$lists['admin.user.index']['description'] = 'Manage users, verification status and account access.';
return $lists;
