<?php

declare(strict_types=1);

namespace App\Mcp\Tools\SocialAccount;

use App\Actions\SocialAccount\ToggleSocialAccount;
use App\Models\SocialAccount;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Toggle a social account active/inactive. Returns the updated account.')]
class ToggleSocialAccountTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'account_id' => ['required', 'string', 'uuid'],
        ]);

        $workspace = $request->user()->currentWorkspace;
        $account = SocialAccount::where('workspace_id', $workspace->id)
            ->where('id', data_get($validated, 'account_id'))
            ->first();

        if (! $account) {
            return Response::error('Account not found.');
        }

        ToggleSocialAccount::execute($account);

        return Response::structured([
            'id' => $account->id,
            'platform' => $account->platform->value,
            'display_name' => $account->display_name,
            'username' => $account->username,
            'is_active' => $account->is_active,
            'status' => $account->status->value,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'account_id' => $schema->string()->required()->description('The UUID of the social account to toggle.'),
        ];
    }
}
