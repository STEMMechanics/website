@php($defaultVersionId = $defaultVersionId ?? \Illuminate\Support\Facades\DB::table('finance_settings')->where('id', 1)->value('default_pricing_version_id'))

    <x-ui.table variant="listing" mobileCards>
        <thead><tr><x-ui.list-heading field="name" label="Plan" /><th class="text-center">Actions</th></tr></thead>
        <tbody data-list-results>
            @foreach($versions as $version)
                <tr>
                    <td data-mobile-primary><div class="flex flex-wrap items-center gap-2"><span>{{ $version->name }}</span>@if((int) ($defaultVersionId ?? 0) === $version->id)<x-ui.badge color="success">Default</x-ui.badge>@endif @if($version->archived)<x-ui.badge color="gray">Archived</x-ui.badge>@endif</div></td>
                    <td data-mobile-actions class="text-center whitespace-nowrap"><x-ui.row-actions>
                        <x-ui.row-action label="Edit allocation plan" icon="fa-pen-to-square" tone="primary" data-record-editor href="{{ route('admin.cost-centre.allocations', ['tab' => 'editor', 'edit_id' => $version->id]) }}" />
                        <x-ui.row-action label="Copy allocation plan" icon="fa-copy" data-record-editor href="{{ route('admin.cost-centre.allocations', ['tab' => 'editor', 'template_id' => $version->id]) }}" />
                        @if(! $version->archived && (int) ($defaultVersionId ?? 0) !== $version->id)
                            <form method="POST" action="{{ route('admin.cost-centre.default-version') }}">@csrf<input type="hidden" name="version_id" value="{{ $version->id }}"><x-ui.row-action type="submit" label="Make default" icon="fa-star" tone="warning" /></form>
                        @endif
                        <form method="POST" action="{{ route('admin.cost-centre.version.archive', $version->id) }}">
                            @csrf
                            <input type="hidden" name="archived" value="{{ $version->archived ? 0 : 1 }}">
                            <x-ui.row-action type="submit" :label="$version->archived ? 'Restore allocation plan' : 'Archive allocation plan'" :icon="$version->archived ? 'fa-box-open' : 'fa-box-archive'" :disabled="(int) ($defaultVersionId ?? 0) === $version->id" />
                        </form>
                        @php($deleteBlocked = (int) ($defaultVersionId ?? 0) === $version->id || in_array($version->id, $usedVersionIds ?? []))
                        <x-ui.row-action type="button" label="{{ $deleteBlocked ? 'Cannot delete a default or in-use allocation plan' : 'Delete allocation plan' }}" icon="fa-trash" tone="danger" :disabled="$deleteBlocked"
                            x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete allocation plan?', 'Delete this unused allocation plan? This cannot be undone.', '{{ route('admin.cost-centre.version.destroy', $version->id) }}')" />
                    </x-ui.row-actions></td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>

<x-ui.record-dialog />
