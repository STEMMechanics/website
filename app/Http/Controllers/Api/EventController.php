<?php

namespace App\Http\Controllers\Api;

use App\Enum\HttpResponseCodes;
use App\Filters\EventFilter;
use App\Http\Requests\EventRequest;
use App\Models\Event;
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
     * @param  EventFilter $filter The event filter.
     * @return \Illuminate\Http\Response
     */
    public function index(EventFilter $filter)
    {
        return $this->respondAsResource(
            $filter->filter(),
            ['total' => $filter->foundTotal()]
        );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  EventRequest $request The event store request.
     * @return \Illuminate\Http\Response
     */
    public function store(EventRequest $request)
    {
        $event = Event::create($request->all());
        return $this->respondAsResource(
            (new EventFilter($request))->filter($event),
            null,
            HttpResponseCodes::HTTP_CREATED
        );
    }

    /**
     * Display the specified resource.
     *
     * @param  EventFilter       $filter The event filter.
     * @param  \App\Models\Event $event  The specified event.
     * @return \Illuminate\Http\Response
     */
    public function show(EventFilter $filter, Event $event)
    {
        return $this->respondAsResource($filter->filter($event));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  EventRequest      $request The event update request.
     * @param  \App\Models\Event $event   The specified event.
     * @return \Illuminate\Http\Response
     */
    public function update(EventRequest $request, Event $event)
    {
        $event->update($request->all());
        return $this->respondAsResource((new EventFilter($request))->filter($event));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Event $event The specified event.
     * @return \Illuminate\Http\Response
     */
    public function destroy(Event $event)
    {
        $event->delete();
        return $this->respondNoContent();
    }
}
