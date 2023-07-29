<?php

namespace App\Http\Controllers\Api;

use App\Enum\HttpResponseCodes;
use App\Models\Event;
use App\Conductors\EventConductor;
use App\Conductors\MediaConductor;
use App\Conductors\UserConductor;
use App\Http\Requests\EventRequest;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends ApiController
{
    /**
     * ApplicationController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum')
        ->only(['store','update','destroy', 'userAdd', 'userUpdate', 'userDelete']);
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
            $event = Event::create($request->except(['attachments']));

            if ($request->has('attachments') === true) {
                $event->addAttachments($request->get('attachments'));
            }

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
            if ($request->has('attachments') === true) {
                $event->deleteAttachments();
                $event->addAttachments($request->get('attachments'));
            }

            $event->update($request->except(['attachments']));
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

    public function userList(Request $request, Event $event)
    {
        $authUser = $request->user();
        $eventUsers = $event->users;

        if ($authUser !== null) {
            $isAdmin = $authUser->hasPermission('admin/events');
            $isEventUser = $eventUsers->contains($authUser->id);

            if ($isAdmin === true || $isEventUser === true) {
                if ($isAdmin === false) {
                    $eventUsers = $eventUsers->filter(function ($user) use ($authUser) {
                        return $user->id === $authUser->id;
                    });
                }

                return $this->respondAsResource(UserConductor::collection($request, $eventUsers), ['isCollection' => true, 'resourceName' => 'users']);
            }

            return $this->respondNotFound();
        }

        return $this->respondForbidden();
    }

    public function userAdd(Request $request, Event $event)
    {
        $authUser = $request->user();
        if ($authUser !== null && $authUser->hasPermission('admin/events') === true) {
            if ($request->has("users") === true) {
                $eventUsers = $event->users()->pluck('user_id')->toArray(); // Get the current users in the event
                $requestedUsers = $request->input("users"); // Get the requested users

                $usersToAdd = array_diff($requestedUsers, $eventUsers); // Users to add
                $usersToRemove = array_diff($eventUsers, $requestedUsers); // Users to remove

                // Add missing users
                foreach ($usersToAdd as $userToAdd) {
                    if (User::find($userToAdd) !== null) {
                        $event->users()->attach($userToAdd);
                    }
                }

                // Remove extra users
                foreach ($usersToRemove as $userToRemove) {
                    $event->users()->detach($userToRemove);
                }

                return $this->respondNoContent();
            }//end if

            return $this->respondWithErrors(['users' => 'The user list was not found']);
        }//end if

        return $this->respondForbidden();
    }

    public function userUpdate(Request $request, Event $event)
    {
        // only admin/events permitted
    }

    public function userDelete(Request $request, Event $event, User $user)
    {
        $authUser = $request->user();
        if ($authUser !== null && $authUser->hasPermission('admin/events') === true) {
            $eventUsers = $event->users;
            if ($eventUsers->find($user->id) !== null) {
                $eventUsers->detach($user->id);
                return $this->respondNoContent();
            } else {
                return $this->respondNotFound();
            }
        }

        return $this->respondForbidden();
    }
}
