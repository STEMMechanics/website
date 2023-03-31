<?php

namespace App\Http\Controllers\Api;

use App\Enum\HttpResponseCodes;
use App\Models\Event;
use App\Conductors\EventConductor;
use App\Conductors\MediaConductor;
use App\Http\Requests\EventRequest;
use App\Models\Media;
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
            ['isCollection' => true,
                'appendData' => ['total' => $total]
            ]
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
                ['respondCode' => HttpResponseCodes::HTTP_CREATED]
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

    /**
     * Get a list of attachments related to this model.
     *
     * @param Request $request The user request.
     * @param Post    $post    The post model.
     * @return JsonResponse Returns the post attachments.
     * @throws InvalidFormatException
     * @throws BindingResolutionException
     * @throws InvalidCastException
     */
    public function getAttachments(Request $request, Event $event)
    {
        if (EventConductor::viewable($event) === true) {
            $medium = $event->attachments->map(function ($attachment) {
                return $attachment->media;
            });

            return $this->respondAsResource(MediaConductor::collection($request, $medium), ['isCollection' => true, 'resourceName' => 'attachment']);
        }

        return $this->respondForbidden();
    }

    /**
     * Store an attachment related to this model.
     *
     * @param Request $request The user request.
     * @param Post    $post    The post model.
     * @return JsonResponse The response.
     * @throws BindingResolutionException
     * @throws MassAssignmentException
     */
    public function storeAttachment(Request $request, Event $event)
    {
        if (EventConductor::updatable($event) === true) {
            if ($request->has("medium") && Media::find($request->medium)) {
                $event->attachments()->create(['media_id' => $request->medium]);
                return $this->respondCreated();
            }

            return $this->respondWithErrors(['media' => 'The media ID was not found']);
        }

        return $this->respondForbidden();
    }

    /**
     * Update/replace attachments related to this model.
     *
     * @param Request $request The user request.
     * @param Post    $post    The related model.
     * @return JsonResponse
     * @throws BindingResolutionException
     * @throws MassAssignmentException
     */
    public function updateAttachments(Request $request, Event $event)
    {
        if (EventConductor::updatable($event) === true) {
            $mediaIds = $request->attachments;
            if (is_array($mediaIds) === false) {
                $mediaIds = explode(',', $request->attachments);
            }

            $mediaIds = array_map('trim', $mediaIds); // trim each media ID
            $attachments = $event->attachments;

            // Delete attachments that are not in $mediaIds
            foreach ($attachments as $attachment) {
                if (!in_array($attachment->media_id, $mediaIds)) {
                    $attachment->delete();
                }
            }

            // Create new attachments for media IDs that are not already in $post->attachments()
            foreach ($mediaIds as $mediaId) {
                $found = false;

                foreach ($attachments as $attachment) {
                    if ($attachment->media_id == $mediaId) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $event->attachments()->create(['media_id' => $mediaId]);
                }
            }

            return $this->respondNoContent();
        }//end if

        return $this->respondForbidden();
    }

    /**
     * Delete a specific related attachment.
     * @param Request $request The user request.
     * @param Post    $post    The model.
     * @param Media   $medium  The attachment medium.
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function deleteAttachment(Request $request, Event $event, Media $medium)
    {
        if (EventConductor::updatable($event) === true) {
            $attachments = $event->attachments;
            $deleted = false;

            foreach ($attachments as $attachment) {
                if ($attachment->media_id === $medium->id) {
                    $attachment->delete();
                    $deleted = true;
                    break;
                }
            }

            if ($deleted) {
                // Attachment was deleted successfully
                return $this->respondNoContent();
            } else {
                // Attachment with matching media ID was not found
                return $this->respondNotFound();
            }
        }

        return $this->respondForbidden();
    }
}
