<?php

namespace App\Casts;

use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Like Eloquent's enum cast, but an unknown stored value comes back as the raw string instead of
 * throwing, for immutable history (audit logs) that may hold values written by another branch.
 *
 * @implements CastsAttributes<BackedEnum|string|null, BackedEnum|string|null>
 */
class LenientEnum implements CastsAttributes
{
    /** @param  class-string<BackedEnum>  $enum */
    public function __construct(private readonly string $enum) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): BackedEnum|string|null
    {
        if ($value === null) {
            return null;
        }

        return ($this->enum)::tryFrom((string) $value) ?? (string) $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string|int|null
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
