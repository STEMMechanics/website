<?php

namespace App\Http\Controllers\Api;

use App\Conductors\AnalyticsConductor;
use App\Conductors\Conductor;
use App\Enum\HttpResponseCodes;
use App\Http\Requests\AnalyticsRequest;
use App\Models\Analytics;
use App\Models\AnalyticsSession;
use Illuminate\Http\Request;

class AnalyticsController extends ApiController
{
    /**
     * AnalyticsController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum')
            ->only([
                'index',
                'update',
                'delete'
            ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @param \Illuminate\Http\Request $request The endpoint request.
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->user() !== null && $request->user()?->hasPermission('admin/analytics') === true) {
            $request->rename([
                'type' => 'requests.type',
                'path' => 'requests.path'
            ]);

            list($collection, $total) = AnalyticsConductor::request($request);

            return $this->respondAsResource(
                $collection,
                ['resourceName' => 'session',
                    'isCollection' => true,
                    'appendData' => ['total' => $total]
                ]
            );
        }//end if

        return $this->respondForbidden();
    }

    /**
     * Display the specified resource.
     *
     * @param \Illuminate\Http\Request     $request The endpoint request.
     * @param  \App\Models\AnalyticsSession $session The analytics session.
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, AnalyticsSession $session)
    {
        if ($request->user() !== null && $request->user()?->hasPermission('admin/analytics') === true) {
            $session->load(['requests' => function ($query) {
                $query->select('session_id', 'type', 'path', 'created_at');
            }
            ]);

            foreach ($session->requests as $requestItem) {
                $requestItem->makeHidden('session_id');
            }

            return $this->respondAsResource(
                $session,
                ['resourceName' => 'session']
            );
        }

        return $this->respondForbidden();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \App\Http\Requests\AnalyticsRequest $request The user request.
     * @return \Illuminate\Http\Response
     */
    public function store(AnalyticsRequest $request)
    {
        if (AnalyticsConductor::creatable() === true) {
            $analytics = null;
            $user = $request->user();

            $data = [
                'type' => $request->input('type'),
                'attribute' => $request->input('attribute', ''),
                'useragent' => $request->userAgent(),
                'ip' => $request->ip()
            ];

            if ($user !== null && $user->hasPermission('admin/analytics') === true && $request->has('session') === true) {
                $data['session_id'] = $request->input('session_id');
                $analytics = AnalyticsRequest::create($data);
            } else {
                $analytics = AnalyticsRequest::create($data);
            }

            return $this->respondAsResource(
                AnalyticsConductor::model($request, $analytics),
                ['respondCode' => HttpResponseCodes::HTTP_CREATED]
            );
        } else {
            return $this->respondForbidden();
        }//end if
    }
}
