<?php

namespace App\Http\Controllers\Api;

use App\Enum\HttpResponseCodes;
use App\Http\Requests\EventRequest;
use App\Models\Event;
use App\Conductors\EventConductor;
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
     * @param  Request      $request The request.
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        list($collection, $total) = EventConductor::request($request);

        return $this->respondAsResource(
            $collection,
            ['total' => $total]
        );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request      $request The request.
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if(EventConductor::creatable()) {
            $event = Event::create($request->all());
            return $this->respondAsResource(
                EventConductor::model($request, $event),
                null,
                HttpResponseCodes::HTTP_CREATED
            );
        } else {
            return $this->respondForbidden();
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  Request      $request The request.
     * @param  \App\Models\Event $event  The specified event.
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, Event $event)
    {
        if(EventConductor::viewable($event)) {
            return $this->respondAsResource(EventConductor::model($request, $event));
        }

        return $this->respondForbidden();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request      $request The request.
     * @param  \App\Models\Event $event   The specified event.
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Event $event)
    {
        if(EventConductor::updatable($event)) {
            $event->update($request->all());
            return $this->respondAsResource(EventConductor::model($request, $event));
        } else {
            return $this->respondForbidden();
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Event $event The specified event.
     * @return \Illuminate\Http\Response
     */
    public function destroy(Event $event)
    {
        if(EventConductor::destroyable($event)) {
            $event->delete();
            return $this->respondNoContent();
        } else {
            return $this->respondForbidden();
        }
    }
}
