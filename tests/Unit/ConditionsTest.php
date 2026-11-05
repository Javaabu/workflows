<?php

namespace Javaabu\Workflows\Tests\Unit;

use Javaabu\Workflows\Definitions\Condition;
use Javaabu\Workflows\Definitions\Conditions;
use Javaabu\Workflows\Definitions\Transition;
use Javaabu\Workflows\Engine\ConditionEvaluator;
use Javaabu\Workflows\Engine\DataResolver;
use Javaabu\Workflows\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ConditionsTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: mixed, 3: bool}>
     */
    public static function rules(): array
    {
        return [
            'equal numbers' => ['days', '=', '5', true],
            'equal text' => ['type', '=', 'ސަލާމް', true],
            'not equal' => ['type', '!=', 'sick', true],
            'more than' => ['days', '>', 4, true],
            'not more than' => ['days', '>', 5, false],
            'at least' => ['days', '>=', 5, true],
            'less than' => ['days', '<', 10, true],
            'at most' => ['days', '<=', 4, false],
            'missing is not less' => ['missing', '<', 10, false],
            'missing is not more' => ['missing', '>', 10, false],
            'dates' => ['from', '>', '2026-11-01', true],
            'in' => ['type', 'in', ['ސަލާމް', 'sick'], true],
            'not in' => ['type', 'not_in', ['sick'], true],
            'list in' => ['tags', 'in', ['b'], true],
            'contains in a list' => ['tags', 'contains', 'a', true],
            'contains text' => ['type', 'contains', 'ލާ', true],
            'filled' => ['type', 'filled', null, true],
            'blank' => ['missing', 'blank', null, true],
            'empty list is blank' => ['empty', 'blank', null, true],
            'boolean' => ['urgent', '=', true, true],
            'boolean as text' => ['urgent', '=', 'false', false],
            'subject attribute' => ['subject.amount', '>=', 1000, true],
            'starter attribute' => ['starter.name', '=', 'އަޙްމަދު', true],
            'nested data' => ['data.items.0.name', '=', 'laptop', true],
        ];
    }

    #[Test]
    #[DataProvider('rules')]
    public function it_checks_a_rule(string $field, string $operator, mixed $value, bool $expected): void
    {
        $scope = [
            'data' => [
                'days' => 5,
                'type' => 'ސަލާމް',
                'tags' => ['a', 'b'],
                'empty' => [],
                'from' => '2026-11-06',
                'urgent' => true,
                'items' => [['name' => 'laptop']],
            ],
            'subject' => ['amount' => 1500],
            'starter' => ['name' => 'އަޙްމަދު'],
        ];

        $this->assertSame($expected, app(ConditionEvaluator::class)->rule(Condition::make($field, $operator, $value), $scope));
    }

    #[Test]
    public function it_combines_rules_with_all_or_any(): void
    {
        $evaluator = app(ConditionEvaluator::class);
        $scope = ['data' => ['days' => 3, 'type' => 'sick']];

        $all = new Conditions([Condition::make('days', '>', 2), Condition::make('type', '=', 'annual')]);
        $this->assertFalse($evaluator->passes($all, $scope));
        $this->assertTrue($evaluator->passes($all->match(Conditions::ANY), $scope));
        $this->assertTrue($evaluator->passes(new Conditions, $scope));
    }

    #[Test]
    public function transitions_parse_when(): void
    {
        $transition = Transition::make('long')
            ->when('days', '>', 5)
            ->when('type', ['annual', 'sick'])
            ->when('reason')
            ->when('notes', 'blank')
            ->when('subject.status', 'pending')
            ->whenAny();

        $this->assertSame([
            'match' => 'any',
            'rules' => [
                ['field' => 'days', 'operator' => '>', 'value' => 5],
                ['field' => 'type', 'operator' => 'in', 'value' => ['annual', 'sick']],
                ['field' => 'reason', 'operator' => 'filled'],
                ['field' => 'notes', 'operator' => 'blank'],
                ['field' => 'subject.status', 'operator' => '=', 'value' => 'pending'],
            ],
        ], $transition->getConditions()->toArray());

        $this->assertEquals($transition->toArray(), Transition::fromArray($transition->toArray())->toArray());
    }

    #[Test]
    public function it_fills_placeholders(): void
    {
        $data = app(DataResolver::class);
        $scope = ['data' => ['days' => 5, 'items' => ['a', 'b']], 'subject' => ['title' => 'ޗުއްޓީ']];

        $this->assertSame('ޗުއްޓީ: 5 days', $data->interpolate($scope, '{{ subject.title }}: {{ days }} days'));
        $this->assertSame(5, $data->interpolate($scope, '{{ days }}'));
        $this->assertSame(['a', 'b'], $data->interpolate($scope, '{{data.items}}'));
        $this->assertSame(['status' => 'ok', 'days' => 5], $data->interpolate($scope, ['status' => 'ok', 'days' => '{{ days }}']));
        $this->assertSame('none: ', $data->interpolate($scope, 'none: {{ missing }}'));
    }
}
