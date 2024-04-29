<?php

namespace App\Exceptions;

use Exception;

class FileTooLargeException extends Exception
{
    /**
     * The error code of the exception.
     *
     * @var int
     */
    protected $code;

    /**
     * The error message of the exception.
     *
     * @var string
     */
    protected $message;

    /**
     * Create a new exception instance.
     *
     * @param string $message
     * @param int $code
     * @return void
     */
    public function __construct(string $message, int $code = 0)
    {
        $this->message = $message;
        $this->code = $code;

        parent::__construct($message, $code);
    }
}
