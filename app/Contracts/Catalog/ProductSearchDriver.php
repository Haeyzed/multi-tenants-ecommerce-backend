<?php

declare(strict_types=1);

namespace App\Contracts\Catalog;

use App\Models\Tenant\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Backend that resolves product search queries.
 *
 * The database driver ships by default; a search engine driver can be bound in its
 * place without touching callers.
 */
interface ProductSearchDriver
{
    /**
     * Resolve a paginated product result set for the given search parameters.
     *
     * @param  array<string, mixed>  $params
     * @return LengthAwarePaginator<int, Product>
     */
    public function search(array $params = []): LengthAwarePaginator;
}
