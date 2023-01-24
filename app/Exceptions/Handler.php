<?php

namespace App\Exceptions;

use App\Enum\HttpResponseCodes;
use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use PDOException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];


    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        // $this->renderable(function (HttpException $e, $request) {
        //     if ($request->is('api/*')) {
        //         $message = $e->getMessage();
        //         if ($message === '') {
        //             $message = HttpResponseCodes::$statusTexts[$e->getStatusCode()];
        //         }

        //         return response()->json([
        //             'message' => $message
        //         ], $e->getStatusCode());
        //     }
        // });

        $this->renderable(function (NotFoundHttpException $e, $request) {
            if ($request->is('api/*') === true) {
                return response()->json([
                    'message' => 'Resource not found'
                ], 404);
            }
        });

        $this->renderable(function (PDOException $e, $request) {
            if ($request->is('api/*') === true) {
                return response()->json([
                    'message' => 'The server is currently unavailable'
                ], 503);
            }
        });

        $this->reportable(function (Throwable $e) {
            //
        });
    }
}
