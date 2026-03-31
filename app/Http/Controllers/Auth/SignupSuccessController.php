<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SignupSuccessController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('auth/SignupSuccess', [
            'authProvider' => $request->session()->get('auth_provider', 'email'),
        ]);
    }
}
