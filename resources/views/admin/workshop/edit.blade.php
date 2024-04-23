@php
    $workshopContent = isset($workshop) ? $workshop->content : '';
@endphp
<x-layout>
    <x-mast backRoute="admin.workshop.index" backTitle="Workshops">{{ isset($workshop) ? 'Edit' : 'Create' }} Workshop</x-mast>

    <x-container class="mt-4">
        <form x-data="{type:'physical',registration:'{{old('registration', $workshop->registration ?? 'none')}}'}" method="POST" action="{{ route('admin.workshop.' . (isset($workshop) ? 'update' : 'store'), $workshop ?? []) }}">
            @isset($workshop)
                @method('PUT')
            @endisset
            @csrf
            <div class="mb-4">
                <x-ui.input label="Title" name="title" value="{!! isset($workshop) ? $workshop->title : '' !!}" />
            </div>
            <div class="mb-4">
                <x-ui.media label="Image" name="hero_media_name" value="{{ $workshop->hero_media_name ?? '' }}" allow_uploads="true" />
            </div>
            <div class="flex gap-8">
                <div class="flex-1">
                    <x-ui.select label="Type" name="type" x-model="type">
                        <option value="physical" {{ ($workshop->location_id ?? '') !== '' || !isset($workshop) ? 'selected' : '' }}>Physical</option>
                        <option value="online" {{ ($workshop->location_id ?? '') === null ? 'selected' : '' }}>Online</option>
                    </x-ui.select>
                </div>
                <div class="flex-1">
                    <span x-show="type==='physical'">
                        <x-ui.select label="Location" name="location_id">
                            @foreach(\App\Models\Location::orderByRaw("name = 'Online' DESC, name ASC")->get() as $location)
                                <option value="{{ $location->id }}" {{ ($workshop->location_id ?? '') === $location->id ? 'selected' : '' }}>{{ $location->name }}</option>
                            @endforeach
                        </x-ui.select>
                    </span>
                </div>
            </div>
            <div class="flex gap-8">
                <div class="flex-1">
                    <x-ui.input type="datetime-local" label="Start Date" name="starts_at" value="{{ \App\Helpers::timestampNoSeconds($workshop->starts_at ?? '') }}" onchange="updatedStartsAt()"/>
                </div>
                <div class="flex-1">
                    <x-ui.input type="datetime-local" label="End Date" name="ends_at" value="{{ \App\Helpers::timestampNoSeconds($workshop->ends_at ?? '') }}" />
                </div>
            </div>
            <div class="flex gap-8">
                <div class="flex-1">
                    <x-ui.select label="Status" name="status">
                        <option value="draft" {{ ($workshop->status ?? '') === 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="open" {{ ($workshop->status ?? '') === 'open' ? 'selected' : '' }}>Open</option>
                        <option value="full" {{ ($workshop->status ?? '') === 'full' ? 'selected' : '' }}>Full</option>
                        <option value="scheduled" {{ ($workshop->status ?? '') === 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                        <option value="closed" {{ ($workshop->status ?? '') === 'closed' ? 'selected' : '' }}>Closed</option>
                    </x-ui.select>
                </div>
                <div class="flex-1">
                    <x-ui.input type="datetime-local" label="Publish Date" name="publish_at" value="{{ \App\Helpers::timestampNoSeconds($workshop->publish_at ?? '') }}" onchange="updatedPublishAt()" />
                </div>
            </div>
                <div class="flex gap-8">
                    <div class="flex-1">
                        &nbsp;
                    </div>
                    <div class="flex-1">
                        <x-ui.input type="datetime-local" label="Closes Date" name="closes_at" value="{{ \App\Helpers::timestampNoSeconds($workshop->closes_at ?? '') }}" />
                    </div>
                </div>
            <div class="flex gap-8">
                <div class="flex-1">
                    <x-ui.input label="Price" name="price" info="Leave blank to hide from public. Also supports Free, TBD or TBC" value="{{ $workshop->price ?? '' }}" />
                </div>
                <div class="flex-1">
                    <x-ui.input label="Ages" name="ages" info="Leave blank to hide from public" value="{{ $workshop->ages ?? '8+' }}" />
                </div>
            </div>
            <div class="flex gap-8">
                <div class="flex-1">
                    <x-ui.select label="Registration" name="registration" x-model="registration" onchange="document.getElementsByName('registration_data').forEach((e)=>e.value='')">
                        <option value="none" {{ (old('registration', $workshop->registration ?? '')) === 'none' ? 'selected' : '' }}>None</option>
                        <option value="link" {{ (old('registration', $workshop->registration ?? '')) === 'link' ? 'selected' : '' }}>External Link</option>
                        <option value="email" {{ (old('registration', $workshop->registration ?? '')) === 'email' ? 'selected' : '' }}>External Email</option>
                        <option value="message" {{ (old('registration', $workshop->registration ?? '')) === 'message' ? 'selected' : '' }}>Custom Message</option>
                    </x-ui.select>
                </div>
                <div class="flex-1">
                    <span x-show="registration==='link'">
                        <x-ui.input label="Registration URL" name="registration_url" value="{{ $workshop->registration_data ?? '' }}" error="{{ $errors->first('registration_data') }}" onchange="document.getElementById('registration_data').value = event.target.value" />
                    </span>
                    <span x-show="registration==='email'">
                        <x-ui.input label="Registration Email" name="registration_email" value="{{ $workshop->registration_data ?? '' }}" error="{{ $errors->first('registration_data') }}" onchange="document.getElementById('registration_data').value = event.target.value" />
                    </span>
                    <span x-show="registration==='message'">
                        <x-ui.input label="Registration Message" name="registration_message" value="{{ $workshop->registration_data ?? '' }}" error="{{ $errors->first('registration_data') }}" onchange="document.getElementById('registration_data').value = event.target.value" />
                    </span>
                    <input type="hidden" name="registration_data" id="registration_data" value="{{ $workshop->registration_data ?? '' }}">
                </div>
            </div>
            <div class="mb-4">
                <x-ui.editor
                    label="Content"
                    name="content"
                    value="{!! $workshopContent !!}"
                ></x-ui.editor>
            </div>
            <div class="mb-4">
                <x-ui.filelist
                    label="Files"
                    name="files"
                    editor="true"
                    value="{!! isset($workshop) ? $workshop->files()->orderBy('name')->get() : '' !!}"
                ></x-ui.filelist>
            </div>
            <div class="flex justify-end gap-4 mt-8">
                @isset($workshop)
                    <x-ui.button type="button" color="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete workshop?', 'Are you sure you want to delete this workshop? This action cannot be undone', '{{ route('admin.workshop.destroy', $workshop) }}')">Delete</x-ui.button>
                @endisset
                <x-ui.button type="submit">{{ isset($workshop) ? 'Save' : 'Create' }}</x-ui.button>
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

    /* Initalize */
    const elemPublishAt = document.getElementsByName('publish_at')[0];
    if(elemPublishAt && elemPublishAt.value === '') {
        let publishAt = new Date();
        document.getElementsByName('publish_at')[0].value = SM.toLocalISOString(publishAt);
    }

    /* Remove seconds from dates if they exist */
    // document.getElementsByName('starts_at')[0].value = document.getElementsByName('starts_at')[0].value.replace(/T(\d{2}:\d{2}):\d{2}$/, 'T$1');
    // document.getElementsByName('ends_at')[0].value = document.getElementsByName('ends_at')[0].value.replace(/T(\d{2}:\d{2}):\d{2}$/, 'T$1');
    // document.getElementsByName('publish_at')[0].value = document.getElementsByName('publish_at')[0].value.replace(/T(\d{2}:\d{2}):\d{2}$/, 'T$1');
    // document.getElementsByName('closes_at')[0].value = document.getElementsByName('closes_at')[0].value.replace(/T(\d{2}:\d{2}):\d{2}$/, 'T$1');
</script>
