<?php

declare(strict_types=1);

use ReportBrains\ReportDesigner\Exceptions\InvalidExpression;
use ReportBrains\ReportDesigner\Expressions\EvaluationContext;
use ReportBrains\ReportDesigner\Expressions\ExpressionEvaluator;

beforeEach(function () {
    $this->evaluator = app(ExpressionEvaluator::class);

    $this->context = new EvaluationContext(
        row: ['total' => 150, 'name' => 'Acme', 'customer.name' => 'Acme Corp'],
        rows: [
            ['total' => 100, 'name' => 'A'],
            ['total' => 250, 'name' => 'B'],
            ['total' => null, 'name' => 'C'],
        ],
        parameters: ['from' => '2026-09-01'],
        group: ['field' => 'branch', 'value' => 'North'],
    );
});

describe('resolving values', function () {
    it('reads a field from the current row', function () {
        expect($this->evaluator->render('Total: {{ total }}', $this->context))->toBe('Total: 150');
    });

    it('reads a field whose name contains a dot without treating it as a path', function () {
        expect($this->evaluator->render('{{ customer.name }}', $this->context))->toBe('Acme Corp');
    });

    it('reads a report parameter', function () {
        expect($this->evaluator->render('From {{ params.from }}', $this->context))->toBe('From 2026-09-01');
    });

    it('reads the current group value', function () {
        expect($this->evaluator->render('{{ group.value }}', $this->context))->toBe('North');
    });

    it('replaces several placeholders in one string', function () {
        expect($this->evaluator->render('{{ name }} owes {{ total }}', $this->context))
            ->toBe('Acme owes 150');
    });

    it('renders a null value as an empty string', function () {
        $context = new EvaluationContext(row: ['note' => null]);

        expect($this->evaluator->render('[{{ note }}]', $context))->toBe('[]');
    });
});

describe('aggregates', function () {
    it('sums the rows in scope', function () {
        expect($this->evaluator->render('{{ sum(total) }}', $this->context))->toBe('350');
    });

    it('averages, ignoring non-numeric values', function () {
        expect($this->evaluator->render('{{ avg(total) }}', $this->context))->toBe('175');
    });

    it('counts non-null values', function () {
        expect($this->evaluator->render('{{ count(total) }}', $this->context))->toBe('2');
    });

    it('takes the minimum and maximum', function () {
        expect($this->evaluator->render('{{ min(total) }}/{{ max(total) }}', $this->context))->toBe('100/250');
    });

    it('sums to zero when no rows are in scope', function () {
        expect($this->evaluator->render('{{ sum(total) }}', new EvaluationContext))->toBe('0');
    });
});

describe('formatters', function () {
    it('formats a number as currency', function () {
        expect($this->evaluator->render('{{ sum(total) | currency }}', $this->context))->toBe('$350.00');
    });

    it('honours the configured currency settings', function () {
        config()->set('report-designer.formatting.currency_symbol', 'Rp ');
        config()->set('report-designer.formatting.decimal_separator', ',');
        config()->set('report-designer.formatting.thousands_separator', '.');
        config()->set('report-designer.formatting.currency_decimals', 0);

        expect($this->evaluator->render('{{ sum(total) | currency }}', $this->context))->toBe('Rp 350');
    });

    it('formats a date', function () {
        config()->set('report-designer.formatting.date_format', 'd/m/Y');

        expect($this->evaluator->render('{{ params.from | date }}', $this->context))->toBe('01/09/2026');
    });

    it('leaves a value that is not a date untouched', function () {
        $context = new EvaluationContext(row: ['label' => 'not a date']);

        expect($this->evaluator->render('{{ label | date }}', $context))->toBe('not a date');
    });

    it('changes case', function () {
        expect($this->evaluator->render('{{ name | upper }}', $this->context))->toBe('ACME');
    });
});

describe('the sandbox', function () {
    it('rejects an unknown function', function () {
        $this->evaluator->evaluate('exec(total)', $this->context);
    })->throws(InvalidExpression::class, 'Unknown function [exec]');

    it('rejects an unknown formatter', function () {
        $this->evaluator->evaluate('total | shell', $this->context);
    })->throws(InvalidExpression::class, 'Unknown formatter [shell]');

    it('rejects a reference that exists nowhere in the context', function () {
        $this->evaluator->evaluate('secret_note', $this->context);
    })->throws(InvalidExpression::class, 'which is not a field, parameter or group value');

    it('rejects PHP that is not an expression at all', function () {
        $this->evaluator->evaluate('phpinfo();', $this->context);
    })->throws(InvalidExpression::class);

    it('rejects arithmetic, which the language does not have', function () {
        $this->evaluator->evaluate('total * 1.11', $this->context);
    })->throws(InvalidExpression::class);

    it('rejects a method call', function () {
        $this->evaluator->evaluate('row->getKey()', $this->context);
    })->throws(InvalidExpression::class);

    it('rejects a static call', function () {
        $this->evaluator->evaluate('DB::table(users)', $this->context);
    })->throws(InvalidExpression::class);

    it('rejects nested calls', function () {
        $this->evaluator->evaluate('sum(sum(total))', $this->context);
    })->throws(InvalidExpression::class);

    it('does not execute anything when a template merely contains PHP-looking text', function () {
        $context = new EvaluationContext(row: ['note' => '<?php echo "x"; ?>']);

        expect($this->evaluator->render('{{ note }}', $context))->toBe('<?php echo "x"; ?>');
    });
});
