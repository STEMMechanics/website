<table class="text-sm mb-4">
    <tr>
        <th class="text-left pr-4 w-24">Workshop</th>
        <td>{{ $workshop->title }}</td>
    </tr>
    <tr>
        <th class="text-left pr-4">Date</th>
        <td>{{ $workshop->getTicketTimeRangeLabel() }}</td>
    </tr>
    @if(!empty($workshop->hosted_for))
    <tr>
        <th class="text-left pr-4 align-top">Hosted For</th>
        <td>
            {{ $workshop->hosted_for }}
        </td>
    </tr>
    @endif
    @if(!$workshop->isPrivate())
    <tr>
        <th class="text-left pr-4 align-top">Location</th>
        <td>
            {{ $workshop->getLocationDisplay() }}
        </td>
    </tr>
    @endif
    @foreach($rows as $row)
        <tr>
            <th class="text-left pr-4">{{ $row['label'] ?? '-' }}</th>
            <td>{{ $row['value'] ?? '-' }}</td>
        </tr>
    @endforeach
</table>
