<?php

namespace App\Http\Controllers;

use App\Models\Workshop;
use App\Services\Finance\WorkshopAllocation;
use Illuminate\Http\Request;

class WorkshopAllocationController extends Controller
{
    public function index()
    {
        return redirect()->route('admin.workshop.index', ['view' => 'list', 'allocation_state' => 'needs_review']);
    }

    public function edit(Workshop $workshop, WorkshopAllocation $allocations)
    {
        return view('admin.workshop.allocation', ['workshop' => $workshop, 'allocation' => $allocations->context($workshop), 'state' => $allocations->state($workshop)]);
    }

    public function store(Request $request, Workshop $workshop, WorkshopAllocation $allocations)
    {
        $data = $request->validate(['source_hash' => 'required|string|size:64', 'revision' => 'nullable|string', 'outcomes_reviewed' => 'required|accepted', 'override' => 'nullable|boolean', 'targets' => 'required_if:override,1|array|min:1', 'targets.*' => 'required|numeric|min:0|max:10000000']);
        $allocations->finalise($workshop, $data, $request->user()->id);

        return redirect()->route('admin.workshop.allocation.edit', $workshop)->with('message', 'Workshop allocation finalised.')->with('message-type', 'success');
    }
}
