<?php

namespace App\Services;

use App\Contracts\ProductLookupProvider;

class NullProductLookupProvider implements ProductLookupProvider
{
    public function lookup(string $barcode): ?array
    {
        return null;
    }
}
