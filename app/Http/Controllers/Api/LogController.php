<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

class LogController extends ApiController
{
    /**
     * ApplicationController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum')
        ->only(['show']);
    }

    /**
     * Display the specified resource.
     *
     * @param  Request       $request The log request.
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        if($request->has('name') && $request->user()?->hasPermission('logs/' . $request->get('name'))) {
            switch(strtolower($request->has('name'))) {
                case 'discord':
                    $contents = '';
                    $filePath = '/opt/discordbot/discordbot.log';
                    if(file_exists($filePath) === true) {
                        $contents = file_get_contents($filePath);
                    }

                    return $this->respondJson(['log' => $contents]);
            }
        }

        return $this->respondForbidden();
    }
}
