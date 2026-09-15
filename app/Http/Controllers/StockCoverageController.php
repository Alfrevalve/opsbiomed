<?php

namespace App\Http\Controllers;

use App\Services\Inventory\SurgeryCoverageService;
use Illuminate\Contracts\View\View;

class StockCoverageController extends Controller
{
    public function __invoke(SurgeryCoverageService $coverageService): View
    {
        abort_unless(auth()->user()?->can('inventory.view'), 403);

        return view('inventory.coverage', [
            'coverage' => $coverageService->summary(),
        ]);
    }
}
