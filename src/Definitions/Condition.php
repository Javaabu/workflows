<?php

namespace Javaabu\Workflows\Definitions;

use Javaabu\Workflows\Exceptions\WorkflowException;

/**
 * One rule a transition checks against the instance: `days > 5`,
 * `subject.amount >= 1000`, `leave_type in [annual, sick]`, `reason filled`.
 * A field without a prefix reads the workflow data (`data.days`); `subject.` and
 * `starter.` read attributes of the subject and of whoever started it.
 */
class Condition
{
    /** @var array<string, string> operator => label */
    public const array OPERATORS = [
        '=' => 'is',
        '!=' => 'is not',
        '>' => 'is more than',
        '>=' => 'is at least',
        '<' => 'is less than',
        '<=' => 'is at most',
        'in' => 'is one of',
        'not_in' => 'is not one of',
        'contains' => 'contains',
        'filled' => 'is filled',
        'blank' => 'is blank',
    ];

    /** Operators that don't take a value */
    public const array UNARY = ['filled', 'blank'];

    public function __construct(
        protected string $field,
        protected string $operator = '=',
        protected mixed $value = null,
    ) {
        if (! array_key_exists($operator, self::OPERATORS)) {
            throw WorkflowException::invalidDefinition(["Unknown condition operator \"{$operator}\"."]);
        }
    }

    public static function make(string $field, string $operator = '=', mixed $value = null): static
    {
        return new static($field, $operator, $value);
    }

    /**
     * `when('days', 5)` is `days = 5`; `when('days', '>', 5)` is `days > 5`
     */
    public static function parse(string $field, mixed $operator = null, mixed $value = null, int $arguments = 3): static
    {
        if ($arguments === 1) {
            return new static($field, 'filled');
        }

        if ($arguments === 2) {
            return is_string($operator) && array_key_exists($operator, self::OPERATORS) && in_array($operator, self::UNARY, true)
                ? new static($field, $operator)
                : new static($field, is_array($operator) ? 'in' : '=', $operator);
        }

        return new static($field, (string) $operator, $value);
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * @return array{field: string, operator: string, value?: mixed}
     */
    public function toArray(): array
    {
        $array = ['field' => $this->field, 'operator' => $this->operator];

        if (! in_array($this->operator, self::UNARY, true)) {
            $array['value'] = $this->value;
        }

        return $array;
    }

    /**
     * @param  array{field: string, operator?: string, value?: mixed}  $array
     */
    public static function fromArray(array $array): static
    {
        return new static((string) ($array['field'] ?? ''), (string) ($array['operator'] ?? '='), $array['value'] ?? null);
    }
}
