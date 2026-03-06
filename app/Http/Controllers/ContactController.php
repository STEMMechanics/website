<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmail;
use App\Mail\ContactMessage;
use App\Support\AltchaTrust;
use GrantHolle\Altcha\Rules\ValidAltcha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $defaultEmail = $user !== null ? (string) $user->email : '';

        return view('contact', [
            'defaultName' => old('name', $user?->getName() ?? ''),
            'defaultEmail' => old('email', $defaultEmail),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
        ];

        if (AltchaTrust::shouldRequire($request)) {
            $rules['altcha'] = ['required', new ValidAltcha()];
        }

        $validated = $request->validate($rules);

        if (array_key_exists('altcha', $rules)) {
            AltchaTrust::markVerified($request);
        }

        $name = trim((string) $validated['name']);
        $email = strtolower(trim((string) $validated['email']));
        $subject = trim((string) $validated['subject']);
        $message = trim((string) $validated['message']);

        dispatch(new SendEmail(
            $this->contactRecipientAddress(),
            new ContactMessage(
                senderName: $name,
                senderEmail: $email,
                subjectLine: $subject,
                messageBody: $message,
                senderUserId: $request->user()?->id !== null ? (string) $request->user()->id : null,
                senderIp: $request->ip(),
                userAgent: (string) $request->userAgent()
            )
        ))->onQueue('mail');

        session()->flash('message', 'Your message has been sent. We will get back to you as soon as we can.');
        session()->flash('message-title', 'Message sent');
        session()->flash('message-type', 'success');

        return redirect()->route('contact');
    }

    private function contactRecipientAddress(): string
    {
        $configured = trim((string) config('mail.contact_to.address', ''));
        if ($configured !== '') {
            return $configured;
        }

        $fallback = trim((string) config('mail.from.address', ''));

        return $fallback !== '' ? $fallback : 'hello@stemmechanics.com.au';
    }
}
