<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use PDOException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
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
        $this->renderable(function (PDOException $e, $request) {
            if ($request->is('api/*') === true) {
                return response()->json([
                    'message' => 'The server is currently unavailable'
                ], 503);
            }
        });

        $this->reportable(function (Throwable $e) {
            if ($this->shouldReport($e) === true) {
                if (App::runningUnitTests() === false) {
                    $this->sendEmail($e);
                }
            }
        });
    }

    /**
     * Send email
     *
     * @param Throwable $exception Throwable object.
     * @return void
     */
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
