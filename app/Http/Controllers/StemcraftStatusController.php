<?php

namespace App\Http\Controllers;

use App\Services\StemcraftServerStatusService;
use Illuminate\Http\JsonResponse;

class StemcraftStatusController extends Controller
{
    public function show(StemcraftServerStatusService $serverStatusService): JsonResponse
    {
        return response()
            ->json($serverStatusService->publicStatus())
            ->header('Cache-Control', 'no-store, private');
    }
}
