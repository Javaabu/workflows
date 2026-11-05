<?php

namespace Javaabu\Workflows\Engine;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Javaabu\Workflows\Models\WorkflowInstance;

/**
 * Reads the values conditions and actions use. A path without a prefix (or
 * with `data.`) reads the workflow's data; `subject.` reads the subject (its
 * attributes and relations), `starter.` whoever started the instance.
 *
 * The scope is an instance, or for the tester an array:
 * `['data' => [...], 'subject' => Model|array|null, 'starter' => Model|array|null]`.
 */
class DataResolver
{
    public const string PLACEHOLDER = '/\{\{\s*([A-Za-z0-9_.*]+)\s*\}\}/';

    /**
     * @param  WorkflowInstance|array<string, mixed>  $scope
     */
    public function get(WorkflowInstance|array $scope, string $path): mixed
    {
        [$root, $key] = $this->split($path);

        $source = $this->source($scope, $root);

        if ($key === '') {
            return $source;
        }

        return data_get($source, $key);
    }

    /**
     * Fill `{{ path }}` placeholders in a string, or every string in an array.
     * A string that's only a placeholder becomes the value itself.
     *
     * @param  WorkflowInstance|array<string, mixed>  $scope
     */
    public function interpolate(WorkflowInstance|array $scope, mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->interpolate($scope, $item), $value);
        }

        if (! is_string($value) || ! str_contains($value, '{{')) {
            return $value;
        }

        if (preg_match('/^\s*\{\{\s*([A-Za-z0-9_.*]+)\s*\}\}\s*$/', $value, $match)) {
            return $this->plain($this->get($scope, $match[1]));
        }

        return (string) preg_replace_callback(self::PLACEHOLDER, function (array $match) use ($scope) {
            $resolved = $this->plain($this->get($scope, $match[1]));

            return is_array($resolved) ? (string) json_encode($resolved) : (string) ($resolved ?? '');
        }, $value);
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function split(string $path): array
    {
        foreach (['data', 'subject', 'starter'] as $root) {
            if ($path === $root) {
                return [$root, ''];
            }

            if (str_starts_with($path, "{$root}.")) {
                return [$root, substr($path, strlen($root) + 1)];
            }
        }

        return ['data', $path];
    }

    /**
     * @param  WorkflowInstance|array<string, mixed>  $scope
     */
    protected function source(WorkflowInstance|array $scope, string $root): mixed
    {
        if (is_array($scope)) {
            return $scope[$root] ?? ($root === 'data' ? [] : null);
        }

        return match ($root) {
            'subject' => $scope->subject,
            'starter' => $scope->starter,
            default => $scope->data ?? [],
        };
    }

    protected function plain(mixed $value): mixed
    {
        if ($value instanceof Model) {
            return $value->getKey();
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value instanceof Arrayable) {
            return $value->toArray();
        }

        return $value;
    }
}
