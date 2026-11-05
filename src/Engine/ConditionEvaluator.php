<?php

namespace Javaabu\Workflows\Engine;

use BackedEnum;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Javaabu\Workflows\Definitions\Condition;
use Javaabu\Workflows\Definitions\Conditions;
use Javaabu\Workflows\Models\WorkflowInstance;
use Throwable;

/**
 * Whether a transition's conditions hold for an instance (or the tester's sample data)
 */
class ConditionEvaluator
{
    public function __construct(
        protected DataResolver $data,
    ) {}

    /**
     * @param  WorkflowInstance|array<string, mixed>  $scope
     */
    public function passes(Conditions $conditions, WorkflowInstance|array $scope): bool
    {
        if ($conditions->isEmpty()) {
            return true;
        }

        foreach ($conditions->getRules() as $rule) {
            $passes = $this->rule($rule, $scope);

            if ($conditions->getMatch() === Conditions::ANY && $passes) {
                return true;
            }

            if ($conditions->getMatch() === Conditions::ALL && ! $passes) {
                return false;
            }
        }

        return $conditions->getMatch() === Conditions::ALL;
    }

    /**
     * @param  WorkflowInstance|array<string, mixed>  $scope
     */
    public function rule(Condition $rule, WorkflowInstance|array $scope): bool
    {
        $actual = $this->normalize($this->data->get($scope, $rule->getField()));
        $expected = $this->normalize($rule->getValue());

        return match ($rule->getOperator()) {
            '=' => $this->equals($actual, $expected),
            '!=' => ! $this->equals($actual, $expected),
            '>' => ($this->compare($actual, $expected) ?? -1) > 0,
            '>=' => ($this->compare($actual, $expected) ?? -1) >= 0,
            '<' => ($this->compare($actual, $expected) ?? 1) < 0,
            '<=' => ($this->compare($actual, $expected) ?? 1) <= 0,
            'in' => $this->in($actual, (array) $expected),
            'not_in' => ! $this->in($actual, (array) $expected),
            'contains' => $this->contains($actual, $expected),
            'filled' => ! blank($actual),
            'blank' => blank($actual),
            default => false,
        };
    }

    protected function normalize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof Model => $value->getKey(),
            $value instanceof Collection => $value->all(),
            default => $value,
        };
    }

    protected function equals(mixed $actual, mixed $expected): bool
    {
        if (is_array($actual)) {
            return $this->in($expected, $actual);
        }

        if (is_bool($actual) || is_bool($expected)) {
            return $this->toBool($actual) === $this->toBool($expected);
        }

        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual === (float) $expected;
        }

        if ($actual instanceof DateTimeInterface) {
            return $this->compare($actual, $expected) === 0;
        }

        return (string) $actual === (string) $expected;
    }

    /**
     * @return int|null -1, 0 or 1 (numbers, then dates, then text), or null when
     *                  there's nothing to compare, so neither > nor < holds
     */
    protected function compare(mixed $actual, mixed $expected): ?int
    {
        if ($actual === null || $actual === '' || is_array($actual) || $expected === null || is_array($expected)) {
            return null;
        }

        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual <=> (float) $expected;
        }

        $actual_date = $this->toDate($actual);
        $expected_date = $this->toDate($expected);

        if ($actual_date && $expected_date) {
            return $actual_date->getTimestamp() <=> $expected_date->getTimestamp();
        }

        return strcmp((string) $actual, (string) $expected) <=> 0;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    protected function in(mixed $actual, array $values): bool
    {
        foreach ((array) $actual as $item) {
            foreach ($values as $value) {
                if ($this->equals($item, $this->normalize($value))) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function contains(mixed $actual, mixed $expected): bool
    {
        if (is_array($actual)) {
            return $this->in($expected, $actual);
        }

        if ($actual === null || $expected === null || $expected === '') {
            return false;
        }

        return mb_stripos((string) $actual, (string) $expected) !== false;
    }

    protected function toBool(mixed $value): bool
    {
        if (is_string($value)) {
            return ! in_array(strtolower($value), ['', '0', 'false', 'no', 'off'], true);
        }

        return (bool) $value;
    }

    protected function toDate(mixed $value): ?Carbon
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
