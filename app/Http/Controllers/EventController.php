<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $homeView = true;
        $search = $request->get('search', '');

        $query = Event::query();

        if(!auth()->user()?->admin) {
            $query = $query->where('status', '!=', 'draft');
        }

        if($request->has('search') && $request->search !== '') {
            $homeView = false;
            $query = $query->where(function ($query) use ($request) {
                $query->where('title', 'like', '%' . $request->search . '%')
                    ->orWhere('content', 'like', '%' . $request->search . '%');
            });
        }

        if($request->has('location') && $request->location !== '') {
            $homeView = false;
            $query = $query->whereHas('location', function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->location . '%');
            });
        }

        if($request->has('date') && $request->date !== '') {
            $homeView = false;
            $dates = explode('-', $request->date);
            $dates = array_map('trim', $dates);
            $dates = array_map(function($date) {
                $date = trim($date);

                if(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    return $date;
                }
                if(preg_match('/^(\d{2})-(\d{2})-(\d{2})$/', $date, $matches)) {
                    return '20' . $matches[1] . '-' . $matches[2] . '-' . $matches[3];
                }
                if(preg_match('/^\d{4}-\d{2}$/', $date)) {
                    return $date . '-01';
                }
                if(preg_match('/^\d{4}$/', $date)) {
                    return $date . '-01-01';
                }
                if(preg_match('/^(\d{2})\/(\d{2})\/(\d{2})$/', $date, $matches)) {
                    return '20' . $matches[3] . '-' . $matches[2] . '-' . $matches[1];
                }
                if(preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
                    return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
                }

                return '';
            }, $dates);

            if(count($dates) == 2) {
                // If there are two dates, filter between starts_at and ends_at
                $query = $query->whereDate('starts_at', '>=', $dates[0])
                    ->whereDate('ends_at', '<=', $dates[1]);
            } else {
                // If there is one date, filter starts_at that date or newer
                $query = $query->whereDate('starts_at', '>=', $dates[0]);
            }
        }

        if($homeView) {
            $query = $query->where('starts_at', '>=', Carbon::now()->subDays(8))
                ->orderBy('starts_at', 'asc');
        } else {
            $query = $query->orderBy('starts_at', 'asc');
        }

        $events = $query->paginate(12);
        return view('event.index', [
            'events' => $events,
            'search' => $search,
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function admin_index(Request $request)
    {
        $query = Event::query();

        if($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
            $query->orWhere('content', 'like', '%' . $request->search . '%');
        }

        $events = $query->orderBy('starts_at', 'desc')->paginate(12)->onEachSide(1);

        return view('admin.event.index', [
            'events' => $events
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function admin_create()
    {
        return view('admin.event.edit');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function admin_store(Request $request)
    {
        $request->validate([
            'title' => 'required',
            'content' => 'required',
            'starts_at' => 'required',
            'ends_at' => 'required|after:starts_at',
            'publish_at' => 'required',
            'closes_at' => 'required',
            'status' => 'required',
            'hero_media_name' => 'required|exists:media,name',
            'registration_data' => 'required_unless:registration,none',
        ], [
            'title.required' => __('validation.custom_messages.title_required'),
            'content.required' => __('validation.custom_messages.content_required'),
            'starts_at.required' => __('validation.custom_messages.starts_at_required'),
            'ends_at.required' => __('validation.custom_messages.ends_at_required'),
            'ends_at.after' => __('validation.custom_messages.ends_at_after'),
            'publish_at.required' => __('validation.custom_messages.publish_at_required'),
            'closes_at.required' => __('validation.custom_messages.closes_at_required'),
            'status.required' => __('validation.custom_messages.status_required'),
            'hero_media_name.required' => __('validation.custom_messages.hero_media_name_required'),
            'hero_media_name.exists' => __('validation.custom_messages.hero_media_name_exists'),
            'registration_data.required_unless' => __('validation.custom_messages.registration_data_required_unless'),
        ]);

        $eventData = $request->all();
        $eventData['user_id'] = auth()->user()->id;

        if($eventData['status'] === 'open' && Carbon::parse($eventData['starts_at'])->lt(Carbon::now())) {
            $eventData['status'] = 'closed';
        }

        $event = Event::create($eventData);
        $event->updateFiles($request->input('files'));

        session()->flash('message', 'Event has been created');
        session()->flash('message-title', 'Event created');
        session()->flash('message-type', 'success');
        return redirect()->route('admin.event.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event)
    {
        if(!auth()->user()?->admin && $event->status == 'draft') {
            abort(404);
        }

        return view('event.show', ['event' => $event]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function admin_edit(Event $event)
    {
        return view('admin.event.edit', ['event' => $event]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function admin_update(Request $request, Event $event)
    {
        $request->validate([
            'title' => 'required',
            'content' => 'required',
            'starts_at' => 'required',
            'ends_at' => 'required|after:starts_at',
            'publish_at' => 'required',
            'closes_at' => 'required',
            'status' => 'required',
            'hero_media_name' => 'required|exists:media,name',
            'registration_data' => 'required_unless:registration,none',
        ], [
            'title.required' => __('validation.custom_messages.title_required'),
            'content.required' => __('validation.custom_messages.content_required'),
            'starts_at.required' => __('validation.custom_messages.starts_at_required'),
            'ends_at.required' => __('validation.custom_messages.ends_at_required'),
            'ends_at.after' => __('validation.custom_messages.ends_at_after'),
            'publish_at.required' => __('validation.custom_messages.publish_at_required'),
            'closes_at.required' => __('validation.custom_messages.closes_at_required'),
            'status.required' => __('validation.custom_messages.status_required'),
            'hero_media_name.required' => __('validation.custom_messages.hero_media_name_required'),
            'hero_media_name.exists' => __('validation.custom_messages.hero_media_name_exists'),
            'registration_data.required_unless' => __('validation.custom_messages.registration_data_required_unless'),
        ]);

        $eventData = $request->all();
        if($eventData['status'] === 'open' && Carbon::parse($eventData['starts_at'])->lt(Carbon::now())) {
            $eventData['status'] = 'closed';
        }

        $event->update($eventData);
        $event->updateFiles($request->input('files'));

        session()->flash('message', 'Event has been updated');
        session()->flash('message-title', 'Event updated');
        session()->flash('message-type', 'success');
        return redirect()->route('admin.event.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function admin_destroy(Event $event)
    {
        $event->delete();
        session()->flash('message', 'Event has been deleted');
        session()->flash('message-title', 'Event deleted');
        session()->flash('message-type', 'danger');

        return redirect()->route('admin.event.index');
    }

    /**
     * Duplicate the specified resource.
     */
    public function admin_duplicate(Event $event)
    {
        $newWorkshop = $event->replicate();
        $newWorkshop->title = $newWorkshop->title . ' (copy)';
        $newWorkshop->status = 'draft';
        $newWorkshop->save();

        foreach($event->files as $file) {
            $newWorkshop->files()->attach($file->name);
        }

        session()->flash('message', 'Event has been duplicated');
        session()->flash('message-title', 'Event duplicated');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.event.edit', $newWorkshop);
    }
}
