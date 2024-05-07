@php
    $eventContent = isset($event) ? $event->content : '';
@endphp
<x-layout>
    <x-mast backRoute="admin.event.index" backTitle="Workshops">{{ isset($event) ? 'Edit' : 'Create' }} Workshop</x-mast>

    <x-container class="mt-4">
        <form x-data="{type:'physical',registration:'{{old('registration', $event->registration ?? 'none')}}'}" method="POST" action="{{ route('admin.event.' . (isset($event) ? 'update' : 'store'), $event ?? []) }}">
            @isset($event)
                @method('PUT')
            @endisset
            @csrf
            <div class="mb-4">
                <x-ui.input label="Title" name="title" value="{!! isset($event) ? $event->title : '' !!}" />
            </div>
            <div class="mb-4">
                <x-ui.media label="Image" name="hero_media_name" value="{{ $event->hero_media_name ?? '' }}" allow_uploads="true" />
            </div>
            <div class="flex flex-col sm:flex-row sm:gap-8">
                <div class="flex-1">
                    <x-ui.select label="Type" name="type" x-model="type">
                        <option value="physical" {{ ($event->location_id ?? '') !== '' || !isset($event) ? 'selected' : '' }}>Physical</option>
                        <option value="online" {{ ($event->location_id ?? '') === null ? 'selected' : '' }}>Online</option>
                    </x-ui.select>
                </div>
                <div class="flex-1">
                    <span x-show="type==='physical'">
                        <x-ui.select label="Location" name="location_id">
                            @foreach(\App\Models\Location::orderByRaw("name = 'Online' DESC, name ASC")->get() as $location)
                                <option value="{{ $location->id }}" {{ ($event->location_id ?? '') === $location->id ? 'selected' : '' }}>{{ $location->name }}</option>
                            @endforeach
                        </x-ui.select>
                    </span>
                </div>
            </div>
                <div class="flex flex-col sm:flex-row sm:gap-8">
                <div class="flex-1">
                    <x-ui.input type="datetime-local" label="Start Date" name="starts_at" value="{{ \App\Helpers::timestampNoSeconds($event->starts_at ?? '') }}" onchange="updatedStartsAt()"/>
                </div>
                <div class="flex-1">
                    <x-ui.input type="datetime-local" label="End Date" name="ends_at" value="{{ \App\Helpers::timestampNoSeconds($event->ends_at ?? '') }}" />
                </div>
            </div>
            <div class="flex flex-col sm:flex-row sm:gap-8">
                <div class="flex-1">
                    <x-ui.select label="Status" name="status">
                        <option value="draft" {{ ($event->status ?? '') === 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="open" {{ ($event->status ?? '') === 'open' ? 'selected' : '' }}>Open</option>
                        <option value="private" {{ ($event->status ?? '') === 'private' ? 'selected' : '' }}>Private</option>
                        <option value="full" {{ ($event->status ?? '') === 'full' ? 'selected' : '' }}>Full</option>
                        <option value="scheduled" {{ ($event->status ?? '') === 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                        <option value="closed" {{ ($event->status ?? '') === 'closed' ? 'selected' : '' }}>Closed</option>
                        <option value="cancelled" {{ ($event->status ?? '') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </x-ui.select>
                </div>
                <div class="flex-1">
                    <x-ui.input type="datetime-local" label="Publish Date" name="publish_at" value="{{ \App\Helpers::timestampNoSeconds($event->publish_at ?? '') }}" onchange="updatedPublishAt()" />
                </div>
            </div>
            <div class="flex flex-col sm:flex-row sm:gap-8">
                <div class="hidden sm:block flex-1">
                    &nbsp;
                </div>
                <div class="flex-1">
                    <x-ui.input type="datetime-local" label="Closes Date" name="closes_at" value="{{ \App\Helpers::timestampNoSeconds($event->closes_at ?? '') }}" />
                </div>
            </div>
            <div class="flex flex-col sm:flex-row sm:gap-8">
                <div class="flex-1">
                    <x-ui.input label="Price" name="price" info="Leave blank to hide from public. Also supports Free, TBD or TBC" value="{{ $event->price ?? '' }}" />
                </div>
                <div class="flex-1">
                    <x-ui.input label="Ages" name="ages" info="Leave blank to hide from public" value="{{ $event->ages ?? '8+' }}" />
                </div>
            </div>
            <div class="flex flex-col sm:flex-row sm:gap-8">
                <div class="flex-1">
                    <x-ui.select label="Registration" name="registration" x-model="registration" onchange="document.getElementsByName('registration_data').forEach((e)=>e.value='')">
                        <option value="none" {{ (old('registration', $event->registration ?? '')) === 'none' ? 'selected' : '' }}>None</option>
                        <option value="link" {{ (old('registration', $event->registration ?? '')) === 'link' ? 'selected' : '' }}>External Link</option>
                        <option value="email" {{ (old('registration', $event->registration ?? '')) === 'email' ? 'selected' : '' }}>External Email</option>
                        <option value="message" {{ (old('registration', $event->registration ?? '')) === 'message' ? 'selected' : '' }}>Custom Message</option>
                    </x-ui.select>
                </div>
                <div class="flex-1">
                    <span x-show="registration==='link'">
                        <x-ui.input label="Registration URL" name="registration_url" id="registration_url" value="{{ $event->registration_data ?? '' }}" error="{{ $errors->first('registration_data') }}" />
                    </span>
                    <span x-show="registration==='email'">
                        <x-ui.input label="Registration Email" name="registration_email" id="registration_email" value="{{ $event->registration_data ?? '' }}" error="{{ $errors->first('registration_data') }}" />
                    </span>
                    <span x-show="registration==='message'">
                        <x-ui.input label="Registration Message" name="registration_message" id="registration_message" value="{{ $event->registration_data ?? '' }}" error="{{ $errors->first('registration_data') }}" />
                    </span>
                    <input type="hidden" name="registration_data" id="registration_data" value="{{ $event->registration_data ?? '' }}">
                </div>
            </div>
            <div class="mb-4">
                <x-ui.editor
                    label="Content"
                    name="content"
                    value="{!! $eventContent !!}"
                ></x-ui.editor>
            </div>
            <div class="mb-4">
                <x-ui.filelist
                    label="Files"
                    name="files"
                    editor="true"
                    value="{!! isset($event) ? $event->files()->orderBy('name')->get() : '' !!}"
                ></x-ui.filelist>
            </div>
            <div class="flex justify-end gap-4 mt-8">
                @isset($event)
                    <x-ui.button type="button" color="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete workshop?', 'Are you sure you want to delete this workshop? This action cannot be undone', '{{ route('admin.event.destroy', $event) }}')">Delete</x-ui.button>
                @endisset
                <x-ui.button type="submit">{{ isset($event) ? 'Save' : 'Create' }}</x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>

<script>
    function updatedStartsAt() {
        const startsAt = document.getElementsByName('starts_at')[0].value;
        console.log(startsAt);

        const elemEndsAt = document.getElementsByName('ends_at')[0];
        if(elemEndsAt.value === '') {
            let endsAt = new Date(startsAt);
            endsAt.setHours(endsAt.getHours() + 1);
            document.getElementsByName('ends_at')[0].value = SM.toLocalISOString(endsAt);
        }

        let closesAt = new Date(startsAt);
        closesAt.setHours(closesAt.getHours() - 2);
        document.getElementsByName('closes_at')[0].value = SM.toLocalISOString(closesAt);
    }

    function updatedPublishAt() {
        const publishAt = document.getElementsByName('publish_at')[0].value;
        const now = new Date();

        if (publishAt > now) {
            document.getElementsByName('status')[0].value = 'scheduled';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const elementIds = ['registration_url', 'registration_email', 'registration_message'];
        const registrationElem = document.getElementById('registration_data');

        if(registrationElem) {
            elementIds.forEach(id => {
                const elem = document.getElementById(id);
                if (elem) {
                    elem.addEventListener('change', function(event) {
                        registrationElem.value = event.target.value;
                    });
                }
            })
        }
    });

    /* Initalize */
    const elemPublishAt = document.getElementsByName('publish_at')[0];
    if(elemPublishAt && elemPublishAt.value === '') {
        let publishAt = new Date();
        document.getElementsByName('publish_at')[0].value = SM.toLocalISOString(publishAt);
    }

</script>
