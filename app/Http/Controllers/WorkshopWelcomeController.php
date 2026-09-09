<?php

namespace App\Http\Controllers;

use App\Jobs\SendWorkshopWelcome;
use App\Mail\WorkshopWelcome;
use App\Models\Workshop;
use App\Services\WorkshopWelcomeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkshopWelcomeController extends Controller
{
    public function preview(Workshop $workshop): string
    {
        return (new WorkshopWelcome($workshop))->render();
    }

    public function send(Request $request, Workshop $workshop, WorkshopWelcomeService $service): RedirectResponse
    {
        $data = $request->validate(['action' => 'required|in:send,resend,retry']);
        abort_unless($service->eligible($workshop), 422, 'Enable and save a welcome email for an upcoming workshop first.');
        if ($data['action'] === 'retry') {
            DB::table('workshop_welcome_deliveries')->where('workshop_id', $workshop->id)
                ->where('generation', $workshop->welcome_generation)->where('status', 'failed')->orderBy('id')
                ->each(function ($delivery): void {
                    DB::table('workshop_welcome_deliveries')->where('id', $delivery->id)->update(['status' => 'queued', 'error' => null, 'updated_at' => now()]);
                    SendWorkshopWelcome::dispatch((int) $delivery->id)->afterCommit();
                });
        } else {
            DB::transaction(function () use ($workshop, $data): void {
                $locked = Workshop::query()->lockForUpdate()->findOrFail($workshop->id);
                if ($data['action'] === 'resend') {
                    $locked->welcome_generation++;
                }
                $locked->welcome_send_at = now();
                $locked->save();
            });
            $service->queueDue($workshop->fresh());
        }

        return back()->with('message', 'Welcome emails queued for active ticket contacts.')->with('message-type', 'success');
    }
}
