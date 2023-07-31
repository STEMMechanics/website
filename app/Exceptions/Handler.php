<?php

namespace App\Exceptions;

use App\Mail\ExceptionMail;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use PDOException;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\ErrorHandler\Exception\FlattenException;

class Handler extends ExceptionHandler
{
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
     */
    public function register(): void
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
            if ($this->shouldReport($e) === true) {
                if(App::runningUnitTests() === false) {
                    $this->sendEmail($e);
                }
            }
        });
    }


    public function sendEmail(Throwable $exception)
    {
        try {
            $e = FlattenException::createFromThrowable($exception);
            $handler = new HtmlErrorRenderer(true);
            $css = $handler->getStylesheet();
            $content = $handler->getBody($e);

            Mail::send('emails.exception', compact('css', 'content'), function ($message) {
                $message
                ->to('webmaster@stemmechanics.com.au')
                ->subject('Exception Generated')
                ;
            });
        } catch (Throwable $ex) {
            Log::error($ex);
        }
    }
}
