<?php

namespace App\Services\Access;

use App\Enums\ScopeType;

/**
 * Where a permission applies: global, or one division / department / store.
 */
final class Scope
{
    public function __construct(
        public readonly ScopeType $type,
        public readonly ?int $id = null,
    ) {}

    public static function global(): self
    {
        return new self(ScopeType::Global);
    }

    public static function division(int $id): self
    {
        return new self(ScopeType::Division, $id);
    }

    public static function department(int $id): self
    {
        return new self(ScopeType::Department, $id);
    }

    public static function store(int $id): self
    {
        return new self(ScopeType::Store, $id);
    }

    public static function from(ScopeType|string|null $type, ?int $id): self
    {
        $type = $type instanceof ScopeType ? $type : ScopeType::from($type ?? ScopeType::Global->value);

        return new self($type, $type === ScopeType::Global ? null : $id);
    }

    public function isGlobal(): bool
    {
        return $this->type === ScopeType::Global;
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id;
    }

    public function toArray(): array
    {
        return ['scope_type' => $this->type->value, 'scope_id' => $this->id];
    }
}
