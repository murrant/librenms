<?php

namespace App\Http\Controllers\Select;

use App\Facades\LibrenmsConfig;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OsController extends Controller
{
    private const MAX_DISTANCE = 15; // Maximum Levenshtein distance for a match to be included

    public function __invoke(Request $request): JsonResponse
    {
        $this->validate($request, [
            'limit' => 'int',
            'page' => 'int',
            'term' => 'nullable|string',
        ]);

        $results = array_map(fn ($os) => [
            'id' => $os['os'],
            'text' => $os['text'],
        ], LibrenmsConfig::get('os', []));

        if ($term = $request->input('term')) {
            $results = $this->sortAndFilterBySimilarity($term, $results);
        }

        return response()->json([
            'results' => array_values($results),
            'pagination' => ['more' => false],
        ]);
    }

    private function sortAndFilterBySimilarity(string $term, array $items): array
    {
        $term = strtolower($term);
        $itemsWithDistances = array_map(function ($item) use ($term) {
            $distance = min(
                levenshtein($term, strtolower($item['id']), 1, 10, 10),
                levenshtein($term, strtolower($item['text']), 1, 10, 10)
            );

            return ['item' => $item, 'distance' => $distance];
        }, $items);

        // Filter by similarity threshold
        $filtered = array_filter($itemsWithDistances, fn ($entry) => $entry['distance'] <= self::MAX_DISTANCE);

        // Sort by distance
        usort($filtered, fn ($a, $b) => $a['distance'] <=> $b['distance']);

        // Extract sorted items
        return array_column($filtered, 'item');
    }
}
