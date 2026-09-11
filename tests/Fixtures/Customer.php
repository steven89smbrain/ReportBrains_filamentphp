<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'city'])]
class Customer extends Model
{
    public $timestamps = false;

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
