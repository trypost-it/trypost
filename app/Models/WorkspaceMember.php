<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class WorkspaceMember extends Pivot
{
    use HasUuids;

    protected $table = 'workspace_members';

    public $incrementing = false;

    protected $keyType = 'string';
}
