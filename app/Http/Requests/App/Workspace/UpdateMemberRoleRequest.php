<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Workspace;

use App\Enums\UserWorkspace\Role as WorkspaceRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(array_column(WorkspaceRole::cases(), 'value'))],
        ];
    }
}
