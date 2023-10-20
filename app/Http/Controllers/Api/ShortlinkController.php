<?php

namespace App\Http\Controllers\Api;

use App\Conductors\ShortlinkConductor;
use App\Enum\HttpResponseCodes;
use App\Http\Requests\ShortlinkRequest;
use App\Models\Shortlink;
use Illuminate\Http\Request;

class ShortlinkController extends ApiController
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
        list($collection, $total) = ShortlinkConductor::request($request);

        return $this->respondAsResource(
            $collection,
            ['isCollection' => true,
                'appendData' => ['total' => $total]
            ],
            function ($options) {
                return $options['total'] === 0;
            }
        );
    }

    /**
     * Display the specified resource.
     *
     * @param \Illuminate\Http\Request $request   The endpoint request.
     * @param  \App\Models\Shortlink    $shortlink The request shortlink.
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, Shortlink $shortlink)
    {
        if (ShortlinkConductor::viewable($shortlink) === true) {
            return $this->respondAsResource(ShortlinkConductor::model($request, $shortlink));
        }

        return $this->respondForbidden();
    }

    /**
     * Store a new media resource
     *
     * @param  \App\Http\Requests\ShortlinkRequest $request The shortlink.
     * @return \Illuminate\Http\Response
     */
    public function store(ShortlinkRequest $request)
    {
        if (ShortlinkConductor::creatable() === true) {
            $shortlink = Shortlink::create($request->all());

            return $this->respondAsResource(
                ShortlinkConductor::model($request, $shortlink),
                ['respondCode' => HttpResponseCodes::HTTP_ACCEPTED]
            );
        }//end if

        return $this->respondForbidden();
    }

    /**
     * Update the media resource in storage.
     *
     * @param  \App\Http\Requests\ShortlinkRequest $request   The update request.
     * @param  \App\Models\Shortlink               $shortlink The specified shortlink.
     * @return \Illuminate\Http\Response
     */
    public function update(ShortlinkRequest $request, Shortlink $shortlink)
    {
        if (ShortlinkConductor::updatable($shortlink) === true) {
            $shortlink->update($request->all());
            return $this->respondAsResource(ShortlinkConductor::model($request, $shortlink));
        }//end if

        return $this->respondForbidden();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Shortlink $shortlink Specified shortlink.
     * @return \Illuminate\Http\Response
     */
    public function destroy(Shortlink $shortlink)
    {
        if (ShortlinkConductor::destroyable($shortlink) === true) {
            $shortlink->delete();
            return $this->respondNoContent();
        }

        return $this->respondForbidden();
    }
}
