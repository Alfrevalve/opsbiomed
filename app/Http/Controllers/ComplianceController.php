<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ComplianceController extends Controller
{
    public function aiPolicy(): View
    {
        return view('compliance.ai-policy');
    }
}
