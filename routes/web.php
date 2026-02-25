<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BasController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\EmailSubscriptionController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\SiteOptionController;
use App\Http\Controllers\SquareWebhookController;
use App\Http\Controllers\SubscribeController;
use App\Http\Controllers\TaxAdjustmentController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkshopController;
use App\Http\Controllers\WorkshopPickListController;
use App\Http\Controllers\PickListTemplateController;
use App\Http\Controllers\WorkshopTicketFlowController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('index');

// Route::get('posts', [PostController::class, 'index'])->name('post.index');
// Route::get('posts/{post}', [PostController::class, 'show'])->name('post.show');
Route::get('workshops', [WorkshopController::class, 'index'])->name('workshop.index');
Route::get('workshops/past', [WorkshopController::class, 'past_index'])->name('workshop.past.index');
Route::get('workshops/{workshop}', [WorkshopController::class, 'show'])->name('workshop.show');
Route::post('workshops/{workshop}/private-access', [WorkshopController::class, 'privateAccess'])->name('workshop.private-access');
Route::get('workshops/{workshop}/tickets', [WorkshopTicketFlowController::class, 'start'])->name('workshop.ticket.flow.start');
Route::get('workshops/{workshop}/tickets/login', [WorkshopTicketFlowController::class, 'loginRedirect'])->name('workshop.ticket.flow.login');
Route::post('workshops/{workshop}/tickets/start', [WorkshopTicketFlowController::class, 'begin'])->name('workshop.ticket.flow.begin');
Route::get('workshops/{workshop}/tickets/payment', [WorkshopTicketFlowController::class, 'payment'])->name('workshop.ticket.flow.payment');
Route::post('workshops/{workshop}/tickets/payment', [WorkshopTicketFlowController::class, 'processPayment'])->name('workshop.ticket.flow.payment.process');
Route::get('workshops/{workshop}/tickets/details', [WorkshopTicketFlowController::class, 'details'])->name('workshop.ticket.flow.details');
Route::get('workshops/{workshop}/tickets/details/keepalive', [WorkshopTicketFlowController::class, 'detailsKeepAlive'])->name('workshop.ticket.flow.details.keepalive');
Route::post('workshops/{workshop}/tickets/details', [WorkshopTicketFlowController::class, 'saveDetails'])->name('workshop.ticket.flow.details.save');
Route::get('workshops/{workshop}/tickets/complete', [WorkshopTicketFlowController::class, 'complete'])->name('workshop.ticket.flow.complete');
Route::get('workshops/{workshop}/tickets/complete/download-all', [WorkshopTicketFlowController::class, 'downloadAll'])->name('workshop.ticket.flow.complete.download-all');
Route::post('workshops/{workshop}/tickets/cancel', [WorkshopTicketFlowController::class, 'cancel'])->name('workshop.ticket.flow.cancel');

Route::get('search', [SearchController::class, 'index'])->name('search.index');
Route::get('unsubscribe/{email}', [SubscribeController::class, 'destroy'])->name('unsubscribe');
Route::get('/tickets', [TicketController::class, 'showRequest'])->name('tickets.request');
Route::post('/tickets', [TicketController::class, 'sendMagicLink'])->middleware('throttle:magic-link')->name('tickets.send');
Route::get('/tickets/magic', [TicketController::class, 'showByMagicToken'])->name('tickets.magic');
Route::get('/tickets/{ticket}/pdf', [TicketController::class, 'pdf'])->name('tickets.pdf');
Route::post('/tickets/{ticket}/attendee', [TicketController::class, 'updateAttendee'])->name('tickets.attendee.update');
Route::get('/tickets/{ticket}/invoice/pdf', [TicketController::class, 'invoicePdf'])->name('tickets.invoice.pdf');
Route::get('/tickets/{ticket}/invoice/receipts', [TicketController::class, 'invoiceReceipts'])->name('tickets.invoice.receipts');
Route::get('/tickets/{ticket}/invoice/receipts/{payment}/pdf', [TicketController::class, 'invoiceReceiptPdf'])->name('tickets.invoice.receipt.pdf');
Route::post('/tickets/{ticket}/cancel', [TicketController::class, 'cancel'])->name('tickets.cancel');
Route::get('/invoices/magic', [InvoiceController::class, 'showByMagicToken'])->name('invoice.magic');
Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'magicPdf'])->name('invoice.magic.pdf');
Route::post('/invoices/{invoice}/pay', [InvoiceController::class, 'magicPay'])->name('invoice.magic.pay');
Route::get('/invoices/{invoice}/receipts/{payment}/pdf', [InvoiceController::class, 'receiptPdf'])->middleware('signed')->name('invoice.receipt.pdf');
Route::get('/pay/{invoice}', [InvoiceController::class, 'publicPayShow'])->name('invoice.public.pay.show');
Route::post('/pay/{invoice}', [InvoiceController::class, 'publicPayProcess'])->middleware('throttle:invoice-public')->name('invoice.public.pay.process');
Route::post('/pay/{invoice}/email-documents', [InvoiceController::class, 'publicEmailDocuments'])->middleware('throttle:invoice-public')->name('invoice.public.email-documents');
Route::post('/webhooks/square', [SquareWebhookController::class, 'handle'])->name('webhook.square');

Route::middleware('auth')->group(function () {
    Route::get('/account', [AccountController::class, 'show'])->name('account.show');
    Route::post('/account', [AccountController::class, 'update'])->name('account.update');
    Route::delete('/account', [AccountController::class, 'destroy'])->name('account.destroy');
    Route::get('/account/invoices', [InvoiceController::class, 'accountIndex'])->name('account.invoice.index');
    Route::get('/account/invoices/{invoice}', [InvoiceController::class, 'accountShow'])->name('account.invoice.show');
    Route::get('/account/invoices/{invoice}/receipts', [InvoiceController::class, 'accountReceipts'])->name('account.invoice.receipts');
    Route::get('/account/invoices/{invoice}/receipts/{payment}', [InvoiceController::class, 'accountReceiptShow'])->name('account.invoice.receipt.show');
    Route::post('/account/invoices/{invoice}/pay', [InvoiceController::class, 'accountPay'])->name('account.invoice.pay');
    Route::get('/account/invoices/{invoice}/pdf', [InvoiceController::class, 'accountPdf'])->name('account.invoice.pdf');
    Route::get('/account/invoices/{invoice}/receipts/{payment}/pdf', [InvoiceController::class, 'accountReceiptPdf'])->name('account.invoice.receipt.pdf');
    Route::get('/account/quotes', [QuoteController::class, 'accountIndex'])->name('account.quote.index');
    Route::get('/account/quotes/{quote}/pdf', [QuoteController::class, 'accountPdf'])->name('account.quote.pdf');
    Route::get('/account/tickets', [TicketController::class, 'accountIndex'])->name('account.ticket.index');
    Route::get('/account/tickets/{ticket}/pdf', [TicketController::class, 'accountPdf'])->name('account.ticket.pdf');
    Route::post('/account/tickets/{ticket}/attendee', [TicketController::class, 'updateAttendee'])->name('account.ticket.attendee.update');
    Route::get('/account/tickets/{ticket}/invoice/pdf', [TicketController::class, 'accountInvoicePdf'])->name('account.ticket.invoice.pdf');
    Route::post('/account/tickets/{ticket}/cancel', [TicketController::class, 'accountCancel'])->name('account.ticket.cancel');
    Route::get('/account/payments', [PaymentController::class, 'accountIndex'])->name('account.payment.index');
    Route::get('/account/payments/{payment}/receipt', [PaymentController::class, 'accountReceiptPdf'])->name('account.payment.receipt');
    Route::get('/account/2fa', [AccountController::class, 'show_tfa'])->name('account.show.tfa');
    Route::get('/account/2fa/image', [AccountController::class, 'show_tfa_image'])->name('account.show.tfa.image');
    Route::post('/account/2fa', [AccountController::class, 'post_tfa'])->name('account.post.tfa');
    Route::post('/account/2fa/reset-backup-codes', [AccountController::class, 'post_tfa_reset_backup_codes'])->name('account.post.tfa.reset-backup-codes');
    Route::delete('/account/2fa', [AccountController::class, 'destroy_tfa'])->name('account.destroy.tfa');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'postLogin'])->middleware('throttle:login')->name('login.store');
Route::get('/logout', [AuthController::class, 'showLogout'])->name('logout.show');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'postRegister'])->name('register.store');
Route::get('/update-email', [AuthController::class, 'updateEmail'])->name('update.email');

Route::get('/about', function () {
    return view('about');
})->name('about');

Route::get('/contact', function () {
    return view('contact');
})->name('contact');

Route::get('/code-of-conduct', function () {
    return view('code-of-conduct');
})->name('code-of-conduct');

Route::get('/terms-conditions', function () {
    return view('terms-conditions');
})->name('terms-conditions');

Route::get('/privacy', function () {
    return view('privacy');
})->name('privacy');

Route::get('/media', [MediaController::class, 'index'])->name('media.index');
Route::get('/media/{media}', [MediaController::class, 'show'])->name('media.show');
Route::get('/media/download/{media}', [MediaController::class, 'download'])->name('media.download');

Route::middleware(['admin', 'nocache'])->group(function () {
    Route::get('/admin/media', [MediaController::class, 'admin_index'])->name('admin.media.index');
    Route::post('/admin/media/regenerate-missing-variants', [MediaController::class, 'admin_regenerate_missing_variants'])->name('admin.media.regenerate-missing-variants');
    Route::get('/admin/media/regenerate-missing-variants/status', [MediaController::class, 'admin_regenerate_missing_variants_status'])->name('admin.media.regenerate-missing-variants.status');
    Route::get('/admin/media/create', [MediaController::class, 'admin_create'])->name('admin.media.create');
    Route::post('/admin/media', [MediaController::class, 'admin_store'])->name('admin.media.store');
    Route::get('/admin/media/{media}', [MediaController::class, 'admin_edit'])->name('admin.media.edit');
    Route::put('/admin/media/{media}', [MediaController::class, 'admin_update'])->name('admin.media.update');
    Route::post('/admin/media/{media}/regenerate-variants', [MediaController::class, 'admin_regenerate_variants'])->name('admin.media.regenerate-variants');
    Route::delete('/admin/media/{media}', [MediaController::class, 'admin_destroy'])->name('admin.media.destroy');
    Route::get('/admin/locations', [LocationController::class, 'index'])->name('admin.location.index');
    Route::get('/admin/locations/create', [LocationController::class, 'create'])->name('admin.location.create');
    Route::post('/admin/locations', [LocationController::class, 'store'])->name('admin.location.store');
    Route::get('/admin/locations/{location}', [LocationController::class, 'edit'])->name('admin.location.edit');
    Route::put('/admin/locations/{location}', [LocationController::class, 'update'])->name('admin.location.update');
    Route::delete('/admin/locations/{location}', [LocationController::class, 'destroy'])->name('admin.location.destroy');

    //    Route::get('/admin/posts', [PostController::class, 'admin_index'])->name('admin.post.index');
    //    Route::get('/admin/posts/create', [PostController::class, 'admin_create'])->name('admin.post.create');
    //    Route::post('/admin/posts', [PostController::class, 'admin_store'])->name('admin.post.store');
    //    Route::get('/admin/posts/{post}', [PostController::class, 'admin_edit'])->name('admin.post.edit');
    //    Route::put('/admin/posts/{post}', [PostController::class, 'admin_update'])->name('admin.post.update');
    //    Route::delete('/admin/posts/{post}', [PostController::class, 'admin_destroy'])->name('admin.post.destroy');

    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.user.index');
    Route::get('/admin/users/create', [UserController::class, 'create'])->name('admin.user.create');
    Route::post('/admin/users', [UserController::class, 'store'])->name('admin.user.store');
    Route::post('/admin/users/inline', [UserController::class, 'storeInline'])->name('admin.user.store-inline');
    Route::get('/admin/users/{user}', [UserController::class, 'edit'])->name('admin.user.edit');
    Route::put('/admin/users/{user}', [UserController::class, 'update'])->name('admin.user.update');
    Route::delete('/admin/users/{user}', [UserController::class, 'destroy'])->name('admin.user.destroy');

    Route::get('/admin/subscriptions', [EmailSubscriptionController::class, 'index'])->name('admin.subscription.index');
    Route::get('/admin/subscriptions/create', [EmailSubscriptionController::class, 'create'])->name('admin.subscription.create');
    Route::post('/admin/subscriptions', [EmailSubscriptionController::class, 'store'])->name('admin.subscription.store');
    Route::get('/admin/subscriptions/{subscription}', [EmailSubscriptionController::class, 'edit'])->name('admin.subscription.edit');
    Route::put('/admin/subscriptions/{subscription}', [EmailSubscriptionController::class, 'update'])->name('admin.subscription.update');
    Route::delete('/admin/subscriptions/{subscription}', [EmailSubscriptionController::class, 'destroy'])->name('admin.subscription.destroy');

    Route::get('/admin/server', [ServerController::class, 'admin_index'])->name('admin.server.index');
    Route::get('/admin/server/options', [SiteOptionController::class, 'index'])->name('admin.site_option.index');
    Route::get('/admin/server/options/create', [SiteOptionController::class, 'create'])->name('admin.site_option.create');
    Route::post('/admin/server/options', [SiteOptionController::class, 'store'])->name('admin.site_option.store');
    Route::get('/admin/server/options/{siteOption}', [SiteOptionController::class, 'edit'])->name('admin.site_option.edit');
    Route::put('/admin/server/options/{siteOption}', [SiteOptionController::class, 'update'])->name('admin.site_option.update');
    Route::delete('/admin/server/options/{siteOption}', [SiteOptionController::class, 'destroy'])->name('admin.site_option.destroy');
    Route::get('/admin/server/log', [ServerController::class, 'admin_laravel_log'])->name('admin.server.log');
    Route::post('/admin/server/log/clear', [ServerController::class, 'admin_clear_log'])->name('admin.server.log.clear');
    Route::post('/admin/server/deploy/log/clear', [ServerController::class, 'admin_clear_deploy_log'])->name('admin.server.deploy.log.clear');
    Route::post('/admin/server/deploy', [ServerController::class, 'admin_deploy'])->name('admin.server.deploy');
    Route::get('/admin/server/deploy/log', [ServerController::class, 'admin_deploy_log'])->name('admin.server.deploy.log');
    Route::get('/admin/server/orphans', [ServerController::class, 'admin_orphans'])->name('admin.server.orphans');
    Route::get('/admin/server/audit', [ServerController::class, 'admin_audit'])->name('admin.server.audit');
    Route::post('/admin/server/audit/prune', [ServerController::class, 'admin_audit_prune'])->name('admin.server.audit.prune');
    Route::post('/admin/server/orphans/scan', [ServerController::class, 'admin_orphans_scan'])->name('admin.server.orphans.scan');
    Route::get('/admin/server/orphans/file', [ServerController::class, 'admin_orphans_file'])->name('admin.server.orphans.file');
    Route::get('/admin/server/orphans/download-all', [ServerController::class, 'admin_orphans_download_all'])->name('admin.server.orphans.download-all');
    Route::get('/admin/server/square-webhooks', [ServerController::class, 'admin_square_webhooks'])->name('admin.server.square-webhooks');
    Route::get('/admin/server/square-webhooks/{event}', [ServerController::class, 'admin_square_webhook_show'])->name('admin.server.square-webhooks.show');
    Route::get('/admin/server/sent-emails', [ServerController::class, 'admin_sent_emails'])->name('admin.server.sent-emails');
    Route::get('/admin/analytics', [AnalyticsController::class, 'index'])->name('admin.analytics.index');
    Route::post('/admin/analytics/prune', [AnalyticsController::class, 'prune'])->name('admin.analytics.prune');

    Route::get('/admin/workshops', [WorkshopController::class, 'admin_index'])->name('admin.workshop.index');
    Route::get('/admin/workshops/create', [WorkshopController::class, 'admin_create'])->name('admin.workshop.create');
    Route::get('/admin/workshops/{workshop}/duplicate', [WorkshopController::class, 'admin_duplicate'])->name('admin.workshop.duplicate');
    Route::get('/admin/workshops/{workshop}/tickets', [WorkshopController::class, 'admin_tickets'])->name('admin.workshop.tickets');
    Route::get('/admin/workshops/{workshop}/tickets/pdf', [WorkshopController::class, 'admin_tickets_pdf'])->name('admin.workshop.tickets.pdf');
    Route::post('/admin/workshops/{workshop}/tickets/email', [WorkshopController::class, 'admin_tickets_email'])->name('admin.workshop.tickets.email');
    Route::get('/admin/workshops/{workshop}/attendance', [WorkshopController::class, 'admin_attendance'])->name('admin.workshop.attendance');
    Route::post('/admin/workshops/{workshop}/attendance/tickets', [WorkshopController::class, 'admin_attendance_tickets'])->name('admin.workshop.attendance.tickets');
    Route::post('/admin/workshops/{workshop}/attendance/dropins', [WorkshopController::class, 'admin_attendance_dropin_store'])->name('admin.workshop.attendance.dropin.store');
    Route::post('/admin/workshops/{workshop}/attendance/dropins/{attendance}/delete', [WorkshopController::class, 'admin_attendance_dropin_destroy'])->name('admin.workshop.attendance.dropin.destroy');
    Route::get('/admin/workshops/{workshop}/pick-list', [WorkshopPickListController::class, 'show'])->name('admin.workshop.pick-list');
    Route::post('/admin/workshops/{workshop}/pick-list', [WorkshopPickListController::class, 'save'])->name('admin.workshop.pick-list.save');
    Route::get('/admin/workshops/{workshop}/pick-list/pdf', [WorkshopPickListController::class, 'pdf'])->name('admin.workshop.pick-list.pdf');
    Route::get('/admin/tickets', [TicketController::class, 'adminIndex'])->name('admin.ticket.index');
    Route::post('/admin/tickets/{ticket}/cancel', [TicketController::class, 'adminCancel'])->name('admin.ticket.cancel');
    Route::post('/admin/workshops', [WorkshopController::class, 'admin_store'])->name('admin.workshop.store');
    Route::get('/admin/workshops/{workshop}', [WorkshopController::class, 'admin_edit'])->name('admin.workshop.edit');
    Route::put('/admin/workshops/{workshop}', [WorkshopController::class, 'admin_update'])->name('admin.workshop.update');
    Route::delete('/admin/workshops/{workshop}', [WorkshopController::class, 'admin_destroy'])->name('admin.workshop.destroy');

    Route::get('/admin/expenses', [ExpenseController::class, 'index'])->name('admin.expense.index');
    Route::get('/admin/expenses/create', [ExpenseController::class, 'create'])->name('admin.expense.create');
    Route::post('/admin/expenses', [ExpenseController::class, 'store'])->name('admin.expense.store');
    Route::get('/admin/expenses/{expense}', [ExpenseController::class, 'edit'])->name('admin.expense.edit');
    Route::put('/admin/expenses/{expense}', [ExpenseController::class, 'update'])->name('admin.expense.update');
    Route::delete('/admin/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('admin.expense.destroy');
    Route::get('/admin/expenses/{expense}/document', [ExpenseController::class, 'viewDocument'])->name('admin.expense.document.view');
    Route::get('/admin/expenses/{expense}/document/download', [ExpenseController::class, 'downloadDocument'])->name('admin.expense.document.download');
    Route::delete('/admin/expenses/{expense}/document', [ExpenseController::class, 'removeDocument'])->name('admin.expense.document.remove');

    Route::get('/admin/payments', [PaymentController::class, 'index'])->name('admin.payment.index');
    Route::get('/admin/payments/create', [PaymentController::class, 'create'])->name('admin.payment.create');
    Route::post('/admin/payments', [PaymentController::class, 'store'])->name('admin.payment.store');
    Route::get('/admin/payments/{payment}', [PaymentController::class, 'edit'])->name('admin.payment.edit');
    Route::get('/admin/payments/{payment}/receipt', [PaymentController::class, 'receiptPdf'])->name('admin.payment.receipt');
    Route::put('/admin/payments/{payment}', [PaymentController::class, 'update'])->name('admin.payment.update');
    Route::delete('/admin/payments/{payment}', [PaymentController::class, 'destroy'])->name('admin.payment.destroy');
    Route::post('/admin/payments/{payment}/square/charge', [PaymentController::class, 'chargeWithSquare'])->name('admin.payment.square.charge');
    Route::post('/admin/payments/{payment}/square/refund', [PaymentController::class, 'refundWithSquare'])->name('admin.payment.square.refund');
    Route::post('/admin/payments/{payment}/refund/manual', [PaymentController::class, 'refundManual'])->name('admin.payment.refund.manual');

    Route::get('/admin/invoices', [InvoiceController::class, 'index'])->name('admin.invoice.index');
    Route::get('/admin/invoices/create', [InvoiceController::class, 'create'])->name('admin.invoice.create');
    Route::post('/admin/invoices', [InvoiceController::class, 'store'])->name('admin.invoice.store');
    Route::get('/admin/invoices/{invoice}', [InvoiceController::class, 'edit'])->name('admin.invoice.edit');
    Route::put('/admin/invoices/{invoice}', [InvoiceController::class, 'update'])->name('admin.invoice.update');
    Route::delete('/admin/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('admin.invoice.destroy');
    Route::get('/admin/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('admin.invoice.pdf');
    Route::post('/admin/invoices/{invoice}/email', [InvoiceController::class, 'emailPdf'])->name('admin.invoice.email');
    Route::post('/admin/invoices/{invoice}/email-payment-link', [InvoiceController::class, 'emailPaymentLink'])->name('admin.invoice.email-payment-link');
    Route::post('/admin/invoices/{invoice}/payment-link', [InvoiceController::class, 'paymentLink'])->name('admin.invoice.payment-link');
    Route::get('/admin/invoices/{invoice}/adjustments', [InvoiceController::class, 'adjustmentIndex'])->name('admin.invoice.short.adjustments');
    Route::get('/admin/invoices/{invoice}/adjustments/create', [TaxAdjustmentController::class, 'create'])->name('admin.tax_adjustment.create');
    Route::post('/admin/invoices/{invoice}/adjustments', [TaxAdjustmentController::class, 'store'])->name('admin.tax_adjustment.store');
    Route::get('/admin/invoices/{invoice}/adjustments/{taxAdjustment}', [TaxAdjustmentController::class, 'edit'])->name('admin.tax_adjustment.edit');
    Route::get('/admin/invoices/{invoice}/adjustments/{taxAdjustment}/pdf', [TaxAdjustmentController::class, 'pdf'])->name('admin.tax_adjustment.pdf');
    Route::post('/admin/invoices/{invoice}/adjustments/{taxAdjustment}/email', [TaxAdjustmentController::class, 'emailPdf'])->name('admin.tax_adjustment.email');
    Route::put('/admin/invoices/{invoice}/adjustments/{taxAdjustment}', [TaxAdjustmentController::class, 'update'])->name('admin.tax_adjustment.update');

    Route::get('/admin/quotes', [QuoteController::class, 'index'])->name('admin.quote.index');
    Route::get('/admin/quotes/create', [QuoteController::class, 'create'])->name('admin.quote.create');
    Route::post('/admin/quotes', [QuoteController::class, 'store'])->name('admin.quote.store');
    Route::get('/admin/quotes/{quote}', [QuoteController::class, 'edit'])->name('admin.quote.edit');
    Route::put('/admin/quotes/{quote}', [QuoteController::class, 'update'])->name('admin.quote.update');
    Route::delete('/admin/quotes/{quote}', [QuoteController::class, 'destroy'])->name('admin.quote.destroy');
    Route::get('/admin/quotes/{quote}/pdf', [QuoteController::class, 'pdf'])->name('admin.quote.pdf');
    Route::post('/admin/quotes/{quote}/email', [QuoteController::class, 'emailPdf'])->name('admin.quote.email');
    Route::post('/admin/quotes/{quote}/create-invoice', [QuoteController::class, 'createInvoice'])->name('admin.quote.create-invoice');
    Route::get('/admin/pick-list-templates', [PickListTemplateController::class, 'index'])->name('admin.pick-list-template.index');
    Route::get('/admin/pick-list-templates/create', [PickListTemplateController::class, 'create'])->name('admin.pick-list-template.create');
    Route::post('/admin/pick-list-templates', [PickListTemplateController::class, 'store'])->name('admin.pick-list-template.store');
    Route::get('/admin/pick-list-templates/{pickListTemplate}', [PickListTemplateController::class, 'edit'])->name('admin.pick-list-template.edit');
    Route::put('/admin/pick-list-templates/{pickListTemplate}', [PickListTemplateController::class, 'update'])->name('admin.pick-list-template.update');
    Route::post('/admin/pick-list-templates/{pickListTemplate}/duplicate', [PickListTemplateController::class, 'duplicate'])->name('admin.pick-list-template.duplicate');
    Route::delete('/admin/pick-list-templates/{pickListTemplate}', [PickListTemplateController::class, 'destroy'])->name('admin.pick-list-template.destroy');
    Route::get('/admin/bas', [BasController::class, 'index'])->name('admin.bas.index');
    Route::get('/admin/bas/export/csv', [BasController::class, 'exportCsv'])->name('admin.bas.export.csv');
    Route::get('/admin/bas/export/pdf', [BasController::class, 'exportPdf'])->name('admin.bas.export.pdf');

});

Route::fallback(function () {
    return response()->view('errors.404', [], 404);
});
