<?php

namespace Javaabu\Workflows\Definitions;

use Illuminate\Database\Eloquent\Model;

/**
 * Who a task step is assigned to:
 *
 * | Type         | Value                                         | Assigned to                                   |
 * | ------------ | --------------------------------------------- | --------------------------------------------- |
 * | `starter`    |                                               | whoever started the instance                  |
 * | `user`       | `morph-alias:key`, e.g. `admin:4`             | that user                                     |
 * | `role`       | a role name                                   | anyone with the role (checked when they act)  |
 * | `permission` | a permission name                             | anyone with the permission                    |
 * | `subject`    | an attribute or relation of the subject       | the user(s) it returns, e.g. `manager`        |
 * | `step`       | a step key                                    | whoever completed that step last              |
 * | yours        | anything                                      | what `Workflows::assignee('yours', …)` returns |
 */
class Assignee
{
    public const string STARTER = 'starter';

    public const string USER = 'user';

    public const string ROLE = 'role';

    public const string PERMISSION = 'permission';

    public const string SUBJECT = 'subject';

    public const string STEP = 'step';

    public const array BUILT_IN = [self::STARTER, self::USER, self::ROLE, self::PERMISSION, self::SUBJECT, self::STEP];

    public function __construct(
        protected string $type,
        protected ?string $value = null,
    ) {}

    public static function make(string $type, ?string $value = null): static
    {
        return new static($type, $value);
    }

    public static function starter(): static
    {
        return new static(self::STARTER);
    }

    public static function user(Model|string $user): static
    {
        return new static(self::USER, $user instanceof Model ? $user->getMorphClass() . ':' . $user->getKey() : $user);
    }

    public static function role(string $role): static
    {
        return new static(self::ROLE, $role);
    }

    public static function permission(string $permission): static
    {
        return new static(self::PERMISSION, $permission);
    }

    public static function subject(string $attribute): static
    {
        return new static(self::SUBJECT, $attribute);
    }

    public static function step(string $step): static
    {
        return new static(self::STEP, $step);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    /**
     * @return array{type: string, value?: string}
     */
    public function toArray(): array
    {
        return array_filter(['type' => $this->type, 'value' => $this->value], fn ($value) => $value !== null);
    }

    /**
     * @param  array{type: string, value?: string|int|null}  $array
     */
    public static function fromArray(array $array): static
    {
        $value = $array['value'] ?? null;

        return new static((string) ($array['type'] ?? ''), $value === null || $value === '' ? null : (string) $value);
    }
}
