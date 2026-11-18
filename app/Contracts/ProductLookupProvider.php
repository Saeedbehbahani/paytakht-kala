<?php

namespace App\Contracts;

interface ProductLookupProvider
{
    /** @return array{name?:string,brand?:string,model?:string,image_url?:string}|null */
    public function lookup(string $barcode): ?array;
}
