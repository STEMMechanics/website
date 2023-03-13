<?php

namespace App\Http\Controllers\Api;

use App\Enum\HttpResponseCodes;
use App\Models\Event;
use App\Conductors\EventConductor;
use App\Http\Requests\EventRequest;
use Illuminate\Http\Request;

class EventController extends ApiController
{
    /**
     * ApplicationController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum')
        ->only(['store','update','destroy']);
    }

    /**
     * Display a listing of the resource.
     *
     * @param \Illuminate\Http\Request $request The endpoint request.
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        list($collection, $total) = EventConductor::request($request);

        return $this->respondAsResource(
            $collection,
            true,
            ['total' => $total]
        );
    }

    /**
     * Display the specified resource.
     *
     * @param \Illuminate\Http\Request $request The endpoint request.
     * @param  \App\Models\Event        $event   The specified event.
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, Event $event)
    {
        if (EventConductor::viewable($event) === true) {
            return $this->respondAsResource(EventConductor::model($request, $event));
        }

        return $this->respondForbidden();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\EventRequest $request The request.
     * @return \Illuminate\Http\Response
     */
    public function store(EventRequest $request)
    {
        if (EventConductor::creatable() === true) {
            $event = Event::create($request->all());
            return $this->respondAsResource(
                EventConductor::model($request, $event),
                false,
                null,
                HttpResponseCodes::HTTP_CREATED
            );
        } else {
            return $this->respondForbidden();
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\EventRequest $request The endpoint request.
     * @param  \App\Models\Event               $event   The specified event.
     * @return \Illuminate\Http\Response
     */
    public function update(EventRequest $request, Event $event)
    {
        if (EventConductor::updatable($event) === true) {
            $event->update($request->all());
            return $this->respondAsResource(EventConductor::model($request, $event));
        }

        return $this->respondForbidden();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Event $event The specified event.
     * @return \Illuminate\Http\Response
     */
    public function destroy(Event $event)
    {
        if (EventConductor::destroyable($event) === true) {
            $event->delete();
            return $this->respondNoContent();
        } else {
            return $this->respondForbidden();
        }
    }
}
