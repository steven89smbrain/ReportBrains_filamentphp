<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Expressions;

use ReportBrains\ReportDesigner\Exceptions\InvalidExpression;

/**
 * Evaluates the `{{ ... }}` placeholders in a report document.
 *
 * Report documents are user input that lives in the database. Rendering them
 * through Blade, or evaluating them with `eval`, would mean anyone who can edit
 * a template can run code on the server. So this is not a general expression
 * language: it understands exactly three forms and refuses everything else.
 *
 *   {{ total }}                  a field on the current row
 *   {{ params.from }}            a report parameter
 *   {{ group.value }}            the current group's value
 *   {{ sum(total) }}             an aggregate over the rows in scope
 *   {{ sum(total) | currency }}  either of the above, formatted
 *
 * There is no arithmetic, no nesting, no method calls and no way to reach the
 * container, a facade or the request.
 */
class ExpressionEvaluator
{
    /**
     * Aggregates that may appear in an expression.
     */
    private const FUNCTIONS = ['sum', 'avg', 'count', 'min', 'max'];

    private const PLACEHOLDER = '/\{\{(.+?)\}\}/';

    /**
     * Either `name(argument)` or a dotted path, each optionally piped into one
     * formatter. Anything else fails to match and is rejected.
     */
    private const EXPRESSION = '/^\s*(?:(?<function>[a-z_]+)\s*\(\s*(?<argument>[A-Za-z0-9_.]+)\s*\)|(?<path>[A-Za-z0-9_.]+))\s*(?:\|\s*(?<formatter>[a-z_]+)\s*)?$/';

    public function __construct(private readonly ValueFormatter $formatter) {}

    /**
     * Replace every placeholder in a string with its value.
     */
    public function render(string $template, EvaluationContext $context): string
    {
        $result = preg_replace_callback(
            self::PLACEHOLDER,
            fn (array $matches): string => $this->stringify($this->evaluate($matches[1], $context)),
            $template,
        );

        return $result ?? $template;
    }

    public function hasPlaceholders(string $template): bool
    {
        return preg_match(self::PLACEHOLDER, $template) === 1;
    }

    /**
     * @throws InvalidExpression
     */
    public function evaluate(string $expression, EvaluationContext $context): mixed
    {
        if (preg_match(self::EXPRESSION, $expression, $matches) !== 1) {
            throw InvalidExpression::syntax(trim($expression));
        }

        $value = ($matches['function'] ?? '') !== ''
            ? $this->aggregate($matches['function'], $matches['argument'], $context)
            : $this->resolve($matches['path'], $context);

        $formatter = $matches['formatter'] ?? '';

        return $formatter === '' ? $value : $this->formatter->format($value, $formatter);
    }

    /**
     * Look up a dotted path in the context, and nowhere else.
     */
    private function resolve(string $path, EvaluationContext $context): mixed
    {
        // Row keys are full field names and may themselves contain dots
        // ("customer.name"), so an exact key match is tried before any
        // traversal, or "customer.name" would be read as row[customer][name].
        if (array_key_exists($path, $context->row)) {
            return $context->row[$path];
        }

        if (str_starts_with($path, 'params.')) {
            return $context->parameters[substr($path, 7)] ?? null;
        }

        if (str_starts_with($path, 'group.')) {
            return $context->group[substr($path, 6)] ?? null;
        }

        // A path that matches nothing is a typo in the template, not missing
        // data, so it is reported rather than rendered as an empty string.
        throw InvalidExpression::unknownReference($path);
    }

    private function aggregate(string $function, string $field, EvaluationContext $context): mixed
    {
        if (! in_array($function, self::FUNCTIONS, strict: true)) {
            throw InvalidExpression::unknownFunction($function, self::FUNCTIONS);
        }

        $values = array_map(
            fn (array $row): mixed => $row[$field] ?? null,
            $context->rows,
        );

        if ($function === 'count') {
            return count(array_filter($values, fn (mixed $value): bool => $value !== null));
        }

        $numbers = array_values(array_map(
            floatval(...),
            array_filter($values, is_numeric(...)),
        ));

        if ($numbers === []) {
            return $function === 'sum' ? 0 : null;
        }

        return match ($function) {
            'sum' => array_sum($numbers),
            'avg' => array_sum($numbers) / count($numbers),
            'min' => min($numbers),
            'max' => max($numbers),
        };
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            $value instanceof \Stringable => (string) $value,
            default => '',
        };
    }
}
