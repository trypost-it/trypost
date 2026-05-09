<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Ai\Agents\BrainAssistant;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BrainController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'context' => 'nullable|array',
        ]);

        $workspace = Auth::user()->currentWorkspace();

        if (!$workspace) {
            return response()->json(['message' => 'No active workspace found.'], 400);
        }

        $agent = new BrainAssistant($workspace, $request->input('context', []));
        
        $response = $agent->prompt($request->input('message'));

        return response()->json($response);
    }
}
