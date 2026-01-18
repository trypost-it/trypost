<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Language extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'code',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
