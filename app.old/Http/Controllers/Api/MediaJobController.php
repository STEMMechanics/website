<?php

namespace App\Http\Controllers\Api;

use App\Conductors\MediaJobConductor;
use App\Http\Controllers\Api\ApiController;
use App\Models\MediaJob;
use Illuminate\Http\Request;

class MediaJobController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @param \Illuminate\Http\Request $request The endpoint request.
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        list($collection, $total) = MediaJobConductor::request($request);

        return $this->respondAsResource(
            $collection,
            ['isCollection' => true,
                'appendData' => ['total' => $total],
                'resourceName' => 'media_job'
            ],
            function ($options) {
                return $options['total'] === 0;
            }
        );
    }

    /**
     * Display the specified resource.
     *
     * @param \Illuminate\Http\Request $request  The endpoint request.
     * @param  \App\Models\MediaJob     $mediaJob The request media job.
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, MediaJob $mediaJob)
    {
        if (MediaJobConductor::viewable($mediaJob) === true) {
            return $this->respondAsResource(
                MediaJobConductor::model($request, $mediaJob),
                ['resourceName' => 'media_job']
            );
        }

        return $this->respondForbidden();
    }
}
