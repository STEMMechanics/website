<?php

namespace App\Http\Controllers\Api;

use App\Conductors\AnalyticsConductor;
use App\Conductors\Conductor;
use App\Enum\HttpResponseCodes;
use App\Http\Requests\AnalyticsRequest;
use App\Models\Media;
use App\Models\Analytics;
use Illuminate\Http\JsonResponse;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\InvalidCastException;
use Illuminate\Database\Eloquent\MassAssignmentException;
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
            $searchFields = ['attribute', 'type', 'useragent', 'ip'];

            $queryRequest = new Request();
            $queryRequest->merge($request->only($searchFields));
            foreach ($searchFields as $field) {
                unset($request[$field]);
            }

            $query = Analytics::query()
                ->selectRaw('session,
                MIN(created_at) as created_at,
                TIMESTAMPDIFF(MINUTE, MIN(created_at), MAX(created_at)) as duration');
            $query = Conductor::filterQuery($query, $queryRequest);

            list($collection, $total) = AnalyticsConductor::collection($request, $query
            ->groupBy('session')
            ->get());

            return $this->respondAsResource(
                $collection,
                ['isCollection' => true,
                    'appendData' => ['total' => $total]
                ]
            );
        }//end if

        return $this->respondForbidden();
    }

    /**
     * Display the specified resource.
     *
     * @param \Illuminate\Http\Request $request   The endpoint request.
     * @param  \App\Models\Analytics    $analytics The analyics model.
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, int $session)
    {
        if ($request->user() !== null && $request->user()?->hasPermission('admin/analytics') === true) {
            list($collection, $total) = AnalyticsConductor::collection($request, Analytics::query()
            ->where('session', $session)
            ->get());

            return $this->respondAsResource(
                $collection,
                ['isCollection' => true,
                    'appendData' => ['total' => $total]
                ]
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
                $data['session'] = $request->input('session');
                $analytics = Analytics::create($data);
            } else {
                $analytics = Analytics::createWithSession($data);
            }

            return $this->respondAsResource(
                AnalyticsConductor::model($request, $analytics),
                ['respondCode' => HttpResponseCodes::HTTP_CREATED]
            );
        } else {
            return $this->respondForbidden();
        }//end if
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\AnalyticsRequest $request   The analytics update request.
     * @param  \App\Models\Analytics               $analytics The specified analytics.
     * @return \Illuminate\Http\Response
     */
    public function update(AnalyticsRequest $request, Analytics $analytics)
    {
        if (AnalyticsConductor::updatable($analytics) === true) {
            $analytics->update($request->all());
            return $this->respondAsResource(AnalyticsConductor::model($request, $analytics));
        }

        return $this->respondForbidden();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Analytics $analytics The specified analytics.
     * @return \Illuminate\Http\Response
     */
    public function destroy(Analytics $analytics)
    {
        if (AnalyticsConductor::destroyable($analytics) === true) {
            $analytics->delete();
            return $this->respondNoContent();
        } else {
            return $this->respondForbidden();
        }
    }
}
