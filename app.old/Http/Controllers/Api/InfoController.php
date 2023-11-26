<?php

namespace App\Http\Controllers\Api;

use App\Enum\HttpResponseCodes;
use App\Models\Media;
use Illuminate\Http\Request;

class InfoController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @param \Illuminate\Http\Request $request The endpoint request.
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $info = [
            "version" => "1.0.0",
            "max_upload_size" => Media::getMaxUploadSize()
        ];

        return $this->respondJson($info);
    }
}
