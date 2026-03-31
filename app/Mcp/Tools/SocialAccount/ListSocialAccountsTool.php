<?php

declare(strict_types=1);

namespace App\Mcp\Tools\SocialAccount;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List all connected social accounts for the current workspace.')]
class ListSocialAccountsTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $accounts = $request->user()->currentWorkspace
            ->socialAccounts()
            ->orderBy('platform')
            ->get()
            ->map(fn ($account) => [
                'id' => $account->id,
                'platform' => $account->platform->value,
                'display_name' => $account->display_name,
                'username' => $account->username,
                'is_active' => $account->is_active,
                'status' => $account->status->value,
            ]);

        return Response::structured($accounts->toArray());
    }
}
