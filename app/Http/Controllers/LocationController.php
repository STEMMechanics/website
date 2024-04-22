<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Post;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Location::query();

        if($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
            $query->orWhere('address', 'like', '%' . $request->search . '%');
        }

        $locations = $query->orderBy('name')->paginate(12)->onEachSide(1);

        return view('admin.location.index', [
            'locations' => $locations
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.location.edit');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'address_url' => 'nullable|url',
        ], [
//            'firstname.required' => __('validation.custom_messages.firstname_required'),
//            'surname.required' => __('validation.custom_messages.surname_required'),
        ]);

        Location::create(array_merge(
            $request->all(),
        ));

        session()->flash('message', 'Location has been created');
        session()->flash('message-title', 'Location created');
        session()->flash('message-type', 'success');
        return redirect()->route('admin.location.index');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Location $location)
    {
        return view('admin.location.edit', ['location' => $location]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Location $location)
    {
        $request->validate([
            'name' => 'required',
            'address_url' => 'url',
        ], [
//            'firstname.required' => __('validation.custom_messages.firstname_required'),
//            'surname.required' => __('validation.custom_messages.surname_required'),
        ]);

        $location->update($request->all());

        session()->flash('message', 'Location has been updated');
        session()->flash('message-title', 'Location updated');
        session()->flash('message-type', 'success');
        return redirect()->route('admin.location.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Location $location)
    {
        $location->delete();
        session()->flash('message', 'Location has been deleted');
        session()->flash('message-title', 'Location deleted');
        session()->flash('message-type', 'danger');

        return redirect()->route('admin.location.index');
    }
}
