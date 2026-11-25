@foreach($pendingOrganisationRequests as $membershipRequest)
    <form
        id="cancel-organisation-request-{{ $membershipRequest->id }}"
        method="POST"
        action="{{ route('account.organisation-request.cancel', $membershipRequest) }}"
        class="hidden"
    >
        @csrf
        @method('DELETE')
    </form>
@endforeach

@foreach($linkedOrganisations as $memberOrganisation)
    <form
        id="leave-organisation-{{ $memberOrganisation->id }}"
        method="POST"
        action="{{ route('account.organisation.leave', $memberOrganisation) }}"
        class="hidden"
        x-data
        x-on:submit.prevent="$SM.confirmAndSubmit($el, {{ \Illuminate\Support\Js::encode('Leave organisation?') }}, {{ \Illuminate\Support\Js::encode('You will no longer be linked to '.$memberOrganisation->name.'. If you want to rejoin later, an organisation manager or STEMMechanics staff member will need to approve your request.'.($memberOrganisation->pivot?->role === 'contact' ? ' If you are the last manager, STEMMechanics staff will manage the organisation and review any pending join requests.' : '')) }}, 'Leave organisation')"
    >
        @csrf
        @method('DELETE')
    </form>
@endforeach
