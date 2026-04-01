<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Label;

use App\Actions\Label\CreateLabel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new label with a name and hex color.')]
class CreateLabelTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $label = CreateLabel::execute($request->user()->currentWorkspace, $validated);

        return Response::structured($label->toArray());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required()->description('The label name.'),
            'color' => $schema->string()->required()->description('Hex color code (e.g. #FF5733).'),
        ];
    }
}
