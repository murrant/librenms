<?php

class AjaxUpdateException extends \Exception
{
    public __construct(string $message = "", int $httpcode = 400, ?Throwable $previous = null)
    {
        parent::__construct($message, $httpcode, $previous);
    }

    public function render(Request $request): \Illuminate\Routing\Response|bool
    {
        if($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], $this->getCode());
        }

        return false;
    }
}