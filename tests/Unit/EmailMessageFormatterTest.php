<?php

namespace Tests\Unit;

use App\Mail\FinanceDocumentPdf;
use App\Mail\WorkshopTicketBroadcast;
use App\Support\EmailMessageFormatter;
use Tests\TestCase;

class EmailMessageFormatterTest extends TestCase
{
    public function test_normalize_for_markdown_converts_single_newlines_and_preserves_blank_lines(): void
    {
        $input = "Line 1\n\n- Line 2\n- Line 3\n\nLine 4\nLine 5";

        $result = EmailMessageFormatter::normalizeForMarkdown($input);

        $this->assertSame("Line 1\n\n- Line 2  \n- Line 3\n\nLine 4  \nLine 5", $result);
    }

    public function test_normalize_for_markdown_returns_empty_string_for_blank_input(): void
    {
        $this->assertSame('', EmailMessageFormatter::normalizeForMarkdown(" \n\t\r\n "));
    }

    public function test_workshop_broadcast_uses_shared_normalizer(): void
    {
        $mailable = new WorkshopTicketBroadcast(
            subjectLine: 'Subject',
            workshopTitle: 'Workshop',
            messageBody: "Line 1\nLine 2"
        );

        $this->assertSame("Line 1  \nLine 2", $mailable->messageBody);
    }

    public function test_finance_document_uses_shared_normalizer_for_custom_and_full_message(): void
    {
        $mailable = new FinanceDocumentPdf(
            documentType: 'invoice',
            documentNumber: 'INV-1',
            recipientName: 'Alex Doe',
            pdfContent: 'PDF',
            pdfFilename: 'invoice.pdf',
            customMessage: "Line 1\nLine 2",
            fullMessage: "Hi {{name}},\nLine 2"
        );

        $this->assertSame("Line 1  \nLine 2", $mailable->customMessage);
        $this->assertSame("Hi Alex,  \nLine 2", $mailable->resolvedFullMessage);
    }
}

