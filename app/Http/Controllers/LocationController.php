<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Location::query();

        if ($request->has('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
            $query->orWhere('address', 'like', '%'.$request->search.'%');
        }

        $locations = $query->orderBy('name')->paginate(12)->onEachSide(1);

        return view('admin.location.index', [
            'locations' => $locations,
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
        $validated = $request->validate([
            'name' => 'required',
            'address' => 'nullable|string|max:255',
            'suburb' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:40',
            'postcode' => 'nullable|string|max:12',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'url' => 'nullable|url',
            'address_url' => 'nullable|url',
        ], [
            //            'firstname.required' => __('validation.custom_messages.firstname_required'),
            //            'surname.required' => __('validation.custom_messages.surname_required'),
        ]);

        $location = Location::create($this->normalizedLocationData($validated));

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'location' => [
                    'id' => (string) $location->id,
                    'name' => (string) $location->name,
                    'address' => (string) ($location->address ?? ''),
                ],
            ]);
        }

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
        $validated = $request->validate([
            'name' => 'required',
            'address' => 'nullable|string|max:255',
            'suburb' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:40',
            'postcode' => 'nullable|string|max:12',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'url' => 'nullable|url',
            'address_url' => 'nullable|url',
        ]);

        $location->update($this->normalizedLocationData($validated));

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

    private function normalizedLocationData(array $validated): array
    {
        foreach (['name', 'address', 'suburb', 'state', 'postcode', 'url', 'address_url'] as $field) {
            $validated[$field] = trim((string) ($validated[$field] ?? '')) ?: null;
        }

        return $validated;
    }
}
