<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WorkshopTicketRollTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        UserGroup::query()->create(['user_id' => $admin->id, 'slug' => 'admin']);

        return $admin;
    }

    public function test_ticket_toolbar_exposes_sign_in_and_keeps_secondary_actions_labelled(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create();
        $workshop = $ticket->workshop;
        $response = $this->actingAs($admin)->get(route('admin.workshop.tickets', $workshop));
        $response->assertOk();
        $document = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
        $toolbar = $document->querySelector('[data-ticket-toolbar]');
        $print = $toolbar->querySelector('a[href="'.route('admin.workshop.tickets.pdf', $workshop).'"]');
        $this->assertStringContainsString('Print sign-in sheet', $print->textContent);
        $this->assertNull($print->closest('dialog'));
        $menu = $toolbar->querySelector('#workshop-ticket-tools');
        $this->assertStringContainsString('Ticket tools', $menu->textContent);
        foreach (['Attendance export (PDF)', 'Email ticket contacts', 'Text ticket contacts'] as $label) {
            $this->assertStringContainsString($label, $menu->textContent);
        }
        $this->assertStringNotContainsString('Create ticket', $menu->textContent);
    }

    public function test_ticket_roll_only_includes_active_tickets_for_this_workshop(): void
    {
        $admin = $this->admin();
        $paid = Ticket::factory()->create(['firstname' => 'Zoe', 'status' => Ticket::STATUS_PAID]);
        $workshop = $paid->workshop;
        $door = Ticket::factory()->create(['workshop_id' => $workshop->id, 'firstname' => 'Ada', 'status' => Ticket::STATUS_PENDING_DOOR]);
        foreach ([Ticket::STATUS_HOLD, Ticket::STATUS_CANCELLED, Ticket::STATUS_REISSUED] as $status) {
            Ticket::factory()->create(['workshop_id' => $workshop->id, 'status' => $status]);
        }
        $other = Workshop::factory()->create(['user_id' => $admin->id, 'hero_media_name' => $workshop->hero_media_name]);
        Ticket::factory()->create(['workshop_id' => $other->id]);

        $pdf = Mockery::mock(PdfDocument::class);
        Pdf::shouldReceive('loadView')->once()->with('pdf.workshop-ticket-roll', Mockery::on(function (array $data) use ($door, $paid): bool {
            $this->assertSame([$door->id, $paid->id], $data['currentTickets']->pluck('id')->all());
            $this->assertArrayNotHasKey('cancelledTickets', $data);
            $this->assertArrayNotHasKey('reissuedTickets', $data);

            return true;
        }))->andReturn($pdf);
        $pdf->shouldReceive('setPaper')->once()->with('a4', 'landscape')->andReturnSelf();
        $pdf->shouldReceive('setOption')->once()->andReturnSelf();
        $pdf->shouldReceive('stream')->once()->andReturn(response('%PDF-test', 200, ['Content-Type' => 'application/pdf']));

        $this->actingAs($admin)->get(route('admin.workshop.tickets.pdf', $workshop))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_sign_in_pdf_renders_landscape_with_blank_rows_and_repeated_consent(): void
    {
        $this->admin();
        $ticket = Ticket::factory()->create();
        $workshop = $ticket->workshop;
        $workshop->title = 'Straw Towers';
        $workshop->starts_at = '2026-09-22 10:30:00';
        $workshop->location->name = 'Community Learning Centre';

        foreach ([0 => 2, 7 => 2, 10 => 2, 26 => 4] as $count => $pageCount) {
            $tickets = collect(range(1, max($count, 1)))->take($count)->map(fn ($i) => new Ticket([
                'firstname' => 'Attendee '.$i,
                'surname' => $count > 10 ? 'Long-Surname Example' : 'Example',
                'email' => $count > 10 ? 'parent.with.a.long.email.address@example.com' : 'parent'.$i.'@example.com',
                'phone' => '0400 123 456',
                'reference_code' => 'REF'.$i,
            ]));
            $data = ['workshop' => $workshop, 'currentTickets' => $tickets];
            $html = view('pdf.workshop-ticket-roll', $data)->render();
            $this->assertStringContainsString('Media consent (Yes)', $html);
            $this->assertStringContainsString('your signature confirms your contact details are correct and your child has been dropped off', $html);
            $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
            $sheets = $document->querySelectorAll('.sheet');
            $this->assertCount($pageCount, $sheets);
            foreach ($sheets as $sheet) {
                $this->assertCount(4, $sheet->querySelectorAll('.roll th'));
                $this->assertCount(10, $sheet->querySelectorAll('.roll tbody tr'));
            }
            $dropInSheet = $sheets->item($pageCount - 1);
            $this->assertSame('Drop-in sign-in', trim($dropInSheet->querySelector('.document-title')->textContent));
            foreach ($dropInSheet->querySelectorAll('.roll tbody tr') as $row) {
                $cells = $row->querySelectorAll('td');
                $this->assertSame('', trim($cells->item(0)->textContent));
                $this->assertSame('', trim($cells->item(1)->textContent));
                $this->assertSame('', trim($cells->item(3)->textContent));
            }
            $populatedRows = $document->querySelectorAll('.reference')->length;
            $this->assertSame($count, $populatedRows);
            $this->assertStringNotContainsString('Cancelled Tickets', $html);
            $pdf = Pdf::loadView('pdf.workshop-ticket-roll', $data)->setPaper('a4', 'landscape');
            $pdf->render();
            $canvas = $pdf->getDomPDF()->getCanvas();
            $this->assertGreaterThan($canvas->get_height(), $canvas->get_width());
            if ($directory = getenv('PDF_QA_DIR')) {
                file_put_contents($directory.'/ticket-roll-'.$count.'.pdf', $pdf->output());
            }
            $this->assertSame($pageCount, $canvas->get_page_count(), 'Unexpected overflow for '.$count.' attendees');
        }
    }
}
