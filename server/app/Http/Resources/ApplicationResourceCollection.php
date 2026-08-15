<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Arr;

class ApplicationResourceCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = ApplicationResource::class;

    /**
     * Flatten Laravel's default paginated resource envelope so the SPA reads
     * pagination metadata at the top level (matching the Paginated<T> client
     * contract) instead of digging into a nested "meta" object.
     *
     * @param  array<string, mixed>  $paginated
     * @param  array<string, mixed>  $default
     * @return array<string, mixed>
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return Arr::except($paginated, ['data', 'links']);
    }
}
