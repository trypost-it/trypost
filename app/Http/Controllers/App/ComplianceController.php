<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Plan\DetectPlanViolations;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ComplianceController extends Controller
{
    public function index(Request $request): Response
    {
        $account = $request->user()->account;
        $violations = DetectPlanViolations::execute($account);

        return Inertia::render('compliance/Index', [
            'violations' => $violations,
            'planName' => $account->plan?->name,
        ]);
    }
}
