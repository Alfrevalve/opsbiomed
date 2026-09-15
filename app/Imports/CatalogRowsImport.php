<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

class CatalogRowsImport implements ToArray
{
    public function array(array $array): void {}
}
