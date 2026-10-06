<?php

namespace App\Http\Controllers;

use App\Graphing\Exceptions\GraphException;
use App\Graphing\GraphErrorImage;
use App\Http\Requests\GraphRequest;
use Illuminate\Http\Response;
use LibreNMS\Util\Debug;

class GraphController extends Controller
{
    /**
     * @throws GraphException when debug is enabled
     */
    public function __invoke(GraphRequest $request): Response
    {
        if ($request->user() !== null) {
            // only allow debug for logged in users
            Debug::set($request->boolean('debug'));
        }

        try {
            $image = $request->graph()->render();
        } catch (GraphException $e) {
            if (Debug::isEnabled()) {
                throw $e;
            }

            $image = GraphErrorImage::forQuery($e, $request->graphQuery());

            return response($image->data, 500, ['Content-type' => $image->contentType()]);
        }

        if (Debug::isEnabled()) {
            return response('<img src="' . $image->inline() . '" alt="graph" />');
        }

        $headers = [
            'Content-type' => $image->contentType(),
        ];

        if ($request->input('output') == 'base64') {
            return response($image->base64(), 200, $headers);
        }

        return response($image->data, 200, $headers);
    }
}
