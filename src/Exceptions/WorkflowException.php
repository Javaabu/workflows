<?php

namespace Javaabu\Workflows\Exceptions;

use RuntimeException;

class WorkflowException extends RuntimeException
{
    /**
     * @param  array<int|string, string|array<int, string>>  $errors
     */
    public static function invalidDefinition(array $errors): static
    {
        $messages = [];

        array_walk_recursive($errors, function ($message) use (&$messages) {
            $messages[] = $message;
        });

        return new static('The workflow definition is invalid: ' . implode(' ', $messages));
    }

    public static function workflowNotFound(string $key): static
    {
        return new static("There's no workflow with the key \"{$key}\".");
    }

    public static function notPublished(string $key): static
    {
        return new static("The workflow \"{$key}\" has no published version.");
    }

    public static function inactive(string $key): static
    {
        return new static("The workflow \"{$key}\" isn't taking new instances.");
    }

    public static function locked(string $key): static
    {
        return new static("The workflow \"{$key}\" is defined in code and can't be changed in the builder.");
    }

    public static function versionPublished(int $number): static
    {
        return new static("Version {$number} is published and can't be changed.");
    }

    public static function versionInUse(int $number): static
    {
        return new static("Version {$number} has instances and can't be deleted.");
    }

    public static function wrongSubject(string $key, string $expected, string $given): static
    {
        return new static("The workflow \"{$key}\" is for {$expected}, not {$given}.");
    }

    public static function notActive(int $instance): static
    {
        return new static("Instance {$instance} isn't active.");
    }

    public static function unknownTransition(string $transition, ?string $step): static
    {
        return new static("There's no transition \"{$transition}\" from the step \"{$step}\".");
    }

    public static function noWayOut(string $step): static
    {
        return new static("No transition out of the step \"{$step}\" matches.");
    }

    public static function tooManySteps(int $limit): static
    {
        return new static("The workflow went through more than {$limit} automatic steps in a row. Check it for loops.");
    }

    public static function unknownAction(string $key): static
    {
        return new static("There's no action \"{$key}\".");
    }

    public static function unknownAssignee(string $type): static
    {
        return new static("There's no assignee type \"{$type}\".");
    }
}
