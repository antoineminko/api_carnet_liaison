<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Pagination optionnelle rétrocompatible.
 * - Sans ?page= : comportement legacy inchangé
 * - Avec ?page= : ajoute meta + enveloppe data si nécessaire
 */
class OptionalPaginator
{
    public static function wantsPagination(Request $request): bool
    {
        return $request->has('page') || $request->boolean('paginate');
    }

    public static function perPage(Request $request, int $default = 20, int $max = 100): int
    {
        $perPage = (int) $request->input('per_page', $default);
        return max(1, min($perPage, $max));
    }

    /**
     * @param  EloquentBuilder|QueryBuilder  $query
     * @param  callable|null  $map  fn($item) => mixed
     * @param  string|null  $wrapKey  si null et legacy = array brut
     */
    public static function respond(
        Request $request,
        EloquentBuilder|QueryBuilder $query,
        ?string $wrapKey = null,
        bool $withSuccess = false,
        ?callable $map = null,
        int $defaultPerPage = 20,
        ?int $legacyLimit = null,
    ): JsonResponse {
        if (!self::wantsPagination($request)) {
            if ($legacyLimit !== null) {
                $query->limit($legacyLimit);
            }
            $items = $query->get();
            if ($map) {
                $items = $items->map($map)->values();
            }

            if ($wrapKey === null) {
                return response()->json($items);
            }

            $payload = [$wrapKey => $items];
            if ($withSuccess) {
                $payload = ['success' => true] + $payload;
            }
            return response()->json($payload);
        }

        $paginator = $query->paginate(self::perPage($request, $defaultPerPage));
        $collection = $paginator->getCollection();
        if ($map) {
            $collection = $collection->map($map)->values();
            $paginator->setCollection(Collection::make($collection->all()));
        }

        return self::paginatedResponse($paginator, $wrapKey, $withSuccess);
    }

    public static function paginatedResponse(
        LengthAwarePaginator $paginator,
        ?string $wrapKey = null,
        bool $withSuccess = false,
    ): JsonResponse {
        $items = $paginator->items();
        $meta = [
            'current_page' => $paginator->currentPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
            'last_page'    => $paginator->lastPage(),
        ];

        if ($wrapKey === null) {
            $payload = [
                'data' => array_values($items),
                'meta' => $meta,
            ];
            if ($withSuccess) {
                $payload = ['success' => true] + $payload;
            }
            return response()->json($payload);
        }

        $payload = [
            $wrapKey => array_values($items),
            'meta'   => $meta,
        ];
        if ($withSuccess) {
            $payload = ['success' => true] + $payload;
        }
        return response()->json($payload);
    }
}
