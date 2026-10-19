<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Support\Str;

class Permission extends SpatieRole
{
    protected function friendlyName(): Attribute
    {
        return Attribute::make(
            get: fn() => Str::of($this->name)->replace(['-', '_'], ' ')->title(),
        );
    }
}
