<?php

namespace LibreNMS\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AjaxUpdateException extends \Exception
{
    public function __construct(string $message = "", int $httpcode = 400, ?Throwable $previous = null)
    {
        parent::__construct($message, $httpcode, $previous);
    }

    public function render(Request $request): JsonResponse|false
    {
        if($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], $this->getCode());
        }

        return false;
    }
}
