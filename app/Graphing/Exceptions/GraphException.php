<?php

namespace App\Graphing\Exceptions;

use App\Graphing\GraphErrorImage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;
use Throwable;

/**
 * Base for all errors produced while resolving or rendering a graph.
 */
abstract class GraphException extends RuntimeException
{
    public function __construct(string $message, protected readonly ?string $shortText = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Text displayed on small error images
     */
    public function shortText(): string
    {
        return $this->shortText ?? $this->getMessage();
    }

    public function httpStatus(): int
    {
        return 500;
    }

    /**
     * Expected failures are not logged, override to return false to use default reporting.
     */
    public function report(): bool
    {
        return true;
    }

    /**
     * When thrown while resolving a graph image request (such as from GraphRequest), respond with an error image.
     */
    public function render(Request $request): Response|false
    {
        if (! $request->routeIs('graph')) {
            return false;
        }

        $image = GraphErrorImage::forVars($this, $request->all());

        return response($image->data, 500, ['Content-type' => $image->contentType()]);
    }
}
