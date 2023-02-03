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
     * @param  Request $request The log request.
     * @param  string  $name    The log name.
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, string $name)
    {
        if ($request->user()?->hasPermission('logs/' . $name) === true) {
            switch (strtolower($name)) {
                case 'discord':
                    $data = [];
                    // $outputContents = '';
                    // $errorContents = '';

                    $logs = $request->get('logs');
                    if ($logs === null) {
                        $logs = ['output', 'error'];
                    } else {
                        $logs = explode(',', strtolower($logs));
                    }

                    $lines = intval($request->get('lines', 50));
                    if ($lines > 100) {
                        $lines = 100;
                    } elseif ($lines < 0) {
                        $lines = 1;
                    }

                    $before = $request->get('before');
                    if ($before !== null) {
                        $before = preg_split("/^([0-9]{4})-([0-9]{2})-([0-9]{2})T([0-9]{2}):([0-9]{2}):([0-9]{2}): /", $before, -1, (PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY));
                        if (count($before) < 6) {
                            $before = null;
                        }
                    }

                    $after = $request->get('after');
                    if ($after !== null) {
                        $after = preg_split("/^([0-9]{4})-([0-9]{2})-([0-9]{2})T([0-9]{2}):([0-9]{2}):([0-9]{2}): /", $after, -1, (PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY));
                        if (count($after) < 6) {
                            $after = null;
                        }
                    }

                    $logFiles = [
                        [
                            'name' => 'output',
                            'path' => '/home/discordbot/.pm2/logs/stemmech-discordbot-out-0.log'
                        ],[
                            'name' => 'error',
                            'path' => '/home/discordbot/.pm2/logs/stemmech-discordbot-error-0.log'
                        ]
                    ];

                    foreach ($logFiles as $logFile) {
                        if (in_array($logFile['name'], $logs) === true) {
                            $logContent = '';

                            if (file_exists($logFile['path']) === true) {
                                $logContent = file_get_contents($logFile['path']);
                            }

                            $logArray = preg_split("/(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}: (?:(?!\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}: )[\s\S])*)/", $logContent, -1, (PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY));

                            $logContent = '';
                            $logLineCount = 0;
                            $logLineSkip = false;
                            foreach (array_reverse($logArray) as $logLine) {
                                $lineDate = preg_split("/^([0-9]{4})-([0-9]{2})-([0-9]{2})T([0-9]{2}):([0-9]{2}):([0-9]{2}): /", $logLine, -1, (PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY));
                                if (count($lineDate) >= 6) {
                                    $logLineSkip = false;

                                    // Is line before
                                    if ($before !== null && ($lineDate[0] > $before[0] || $lineDate[1] > $before[1] || $lineDate[2] > $before[2] || $lineDate[3] > $before[3] || $lineDate[4] > $before[4] || $lineDate[5] > $before[5])) {
                                        $logLineSkip = true;
                                        continue;
                                    }

                                    // Is line after
                                    if ($after !== null && ($after[0] > $lineDate[0] || $after[1] > $lineDate[1] || $after[2] > $lineDate[2] || $after[3] > $lineDate[3] || $after[4] > $lineDate[4] || $after[5] > $lineDate[5])) {
                                        $logLineSkip = true;
                                        continue;
                                    }

                                    $logLineCount += 1;
                                }

                                if ($logLineCount > $lines) {
                                    break;
                                }

                                if ($logLineSkip === false) {
                                    $logContent .= $logLine;
                                }
                            }//end foreach

                            $data[$logFile['name']] = $logContent;
                        }//end if
                    }//end foreach

                    return $this->respondJson([
                        'log' => $data
                    ]);
            }//end switch
        }//end if

        return $this->respondForbidden();
    }
}
