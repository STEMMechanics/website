<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\ContactSendRequest;
use App\Jobs\SendEmailJob;
use App\Mail\Contact;

class ContactController extends ApiController
{
    /**
     * Send the request to the site admin by email
     *
     * @param  \App\Http\Requests\User\ContactSendRequest $request Request data.
     * @return \Illuminate\Http\Response
     */
    public function send(ContactSendRequest $request)
    {
        dispatch((new SendEmailJob(
            config('contact.contact_address'),
            new Contact(
                $request->input('name'),
                $request->input('email'),
                $request->input('content')
            )
        )))->onQueue('mail');

        return $this->respondCreated();
    }
}
