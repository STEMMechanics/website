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
    public function show(Request $request, string $name)
    {
        if($request->user()?->hasPermission('logs/' . $name)) {
            switch(strtolower($name)) {
                case 'discord':
                    $contents = '';
                    $filePath = '/opt/discordbot/discordbot.log';
                    if(file_exists($filePath) === true) {
                        $contents = file_get_contents($filePath);
                    }

                    $lines = preg_split("/(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}: (?:(?!\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}: )[\s\S])*)/", $contents, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                    return $this->respondJson(['log' => implode('', array_reverse($lines))]);
            }
        }

        return $this->respondForbidden();
    }
}
