<?php

namespace App\Http\Controllers;

use App\Models\Workshop;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WorkshopController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = Workshop::query();
        $query = $query->where('starts_at', '>=', Carbon::now()->subDays(8))
            ->orderBy('starts_at', 'asc');

        $workshops = $query->paginate(12);
        return view('workshop.index', [
            'workshops' => $workshops
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function past_index()
    {
        $query = Workshop::query();
        $query = $query->where('starts_at', '<', Carbon::now())
            ->orderBy('starts_at', 'desc');

        $workshops = $query->paginate(12);
        return view('workshop.index', [
            'workshops' => $workshops
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function admin_index(Request $request)
    {
        $query = Workshop::query();

        if($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
            $query->orWhere('content', 'like', '%' . $request->search . '%');
        }

        $workshops = $query->orderBy('starts_at', 'desc')->paginate(12)->onEachSide(1);

        return view('admin.workshop.index', [
            'workshops' => $workshops
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function admin_create()
    {
        return view('admin.workshop.edit');
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

        $workshopData = $request->all();
        $workshopData['user_id'] = auth()->user()->id;

        if($workshopData['status'] === 'open' && Carbon::parse($workshopData['starts_at'])->lt(Carbon::now())) {
            $workshopData['status'] = 'closed';
        }

        $workshop = Workshop::create($workshopData);
        $workshop->updateFiles($request->input('files'));

        session()->flash('message', 'Workshop has been created');
        session()->flash('message-title', 'Workshop created');
        session()->flash('message-type', 'success');
        return redirect()->route('admin.workshop.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(Workshop $workshop)
    {
        if(!auth()->user()?->admin && $workshop->status == 'draft') {
            abort(404);
        }

        return view('workshop.show', ['workshop' => $workshop]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function admin_edit(Workshop $workshop)
    {
        return view('admin.workshop.edit', ['workshop' => $workshop]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function admin_update(Request $request, Workshop $workshop)
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

        $workshopData = $request->all();
        if($workshopData['status'] === 'open' && Carbon::parse($workshopData['starts_at'])->lt(Carbon::now())) {
            $workshopData['status'] = 'closed';
        }

        $workshop->update($workshopData);
        $workshop->updateFiles($request->input('files'));

        session()->flash('message', 'Workshop has been updated');
        session()->flash('message-title', 'Workshop updated');
        session()->flash('message-type', 'success');
        return redirect()->route('admin.workshop.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function admin_destroy(Workshop $workshop)
    {
        $workshop->delete();
        session()->flash('message', 'Workshop has been deleted');
        session()->flash('message-title', 'Workshop deleted');
        session()->flash('message-type', 'danger');

        return redirect()->route('admin.workshop.index');
    }

    /**
     * Duplicate the specified resource.
     */
    public function admin_duplicate(Workshop $workshop)
    {
        $newWorkshop = $workshop->replicate();
        $newWorkshop->title = $newWorkshop->title . ' (copy)';
        $newWorkshop->status = 'draft';
        $newWorkshop->save();

        foreach($workshop->files as $file) {
            $newWorkshop->files()->attach($file->name);
        }

        session()->flash('message', 'Workshop has been duplicated');
        session()->flash('message-title', 'Workshop duplicated');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.workshop.edit', $newWorkshop);
    }
}
