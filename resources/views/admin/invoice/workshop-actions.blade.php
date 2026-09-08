@php($linkedWorkshops = $invoice->tickets->pluck('workshop')->filter()->unique('id'))
@foreach($linkedWorkshops as $linkedWorkshop)
    <x-ui.row-action :label="$linkedWorkshops->count() === 1 ? 'View workshop' : 'View workshop: '.$linkedWorkshop->title" icon="fa-solid fa-chalkboard-user" tone="neutral" :href="route('admin.workshop.edit', $linkedWorkshop)" />
@endforeach
