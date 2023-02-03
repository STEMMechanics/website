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
                    $outputContents = '';
                    $errorContents = '';

                    // output log
                    $filePath = '/home/discordbot/.pm2/logs/stemmech-discordbot-out-0.log';
                    if(file_exists($filePath) === true) {
                        $outputContents = file_get_contents($filePath);
                    }

                    $lines = preg_split("/(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}: (?:(?!\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}: )[\s\S])*)/", $outputContents, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                    $outputContents = implode('', array_reverse($lines));
                    
                    // error log
                    $filePath = '/home/discordbot/.pm2/logs/stemmech-discordbot-error-0.log';
                    if(file_exists($filePath) === true) {
                        $errorContents = file_get_contents($filePath);
                    }

                    $lines = preg_split("/(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}: (?:(?!\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}: )[\s\S])*)/", $errorContents, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                    $errorContents = implode('', array_reverse($lines));

                    return $this->respondJson([
                        'log' => [
                            'output' => $outputContents,
                            'errors' => $errorContents,
                        ]
                    ]);
            }
        }

        return $this->respondForbidden();
    }
}
