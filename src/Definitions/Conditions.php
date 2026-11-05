<?php

namespace Javaabu\Workflows\Definitions;

/**
 * The rules of a transition, combined with `all` (every rule holds) or `any`
 */
class Conditions
{
    public const string ALL = 'all';

    public const string ANY = 'any';

    /**
     * @param  array<int, Condition>  $rules
     */
    public function __construct(
        protected array $rules = [],
        protected string $match = self::ALL,
    ) {}

    public function add(Condition $condition): static
    {
        $this->rules[] = $condition;

        return $this;
    }

    public function match(string $match): static
    {
        $this->match = $match === self::ANY ? self::ANY : self::ALL;

        return $this;
    }

    public function getMatch(): string
    {
        return $this->match;
    }

    /**
     * @return array<int, Condition>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    public function isEmpty(): bool
    {
        return $this->rules === [];
    }

    /**
     * @return array{match: string, rules: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'match' => $this->match,
            'rules' => array_map(fn (Condition $rule) => $rule->toArray(), $this->rules),
        ];
    }

    /**
     * Read `{"match": "any", "rules": [...]}`, or a plain list of rules (all must hold)
     *
     * @param  array<mixed>|null  $array
     */
    public static function fromArray(?array $array): static
    {
        if (! $array) {
            return new static;
        }

        $rules = array_is_list($array) ? $array : ($array['rules'] ?? []);
        $match = array_is_list($array) ? self::ALL : (string) ($array['match'] ?? self::ALL);

        return new static(
            array_map(fn (array $rule) => Condition::fromArray($rule), array_values(array_filter((array) $rules, 'is_array'))),
            $match === self::ANY ? self::ANY : self::ALL,
        );
    }
}
