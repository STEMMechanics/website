<?php

namespace App\Mail;

use App\Models\OrganisationMembershipRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrganisationMembershipRequestAdminNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public OrganisationMembershipRequest $membershipRequest,
        public string $adminUrl,
    ) {}

    public function build(): static
    {
        $this->membershipRequest->loadMissing(['organisation', 'user']);
        $organisationName = $this->membershipRequest->organisation->name ?? 'Organisation';

        return $this
            ->subject('Organisation join request needs review: '.$organisationName)
            ->markdown('emails.organisation-membership-request-admin-notification');
    }
}
