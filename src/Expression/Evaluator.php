<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Expression;

use Nalabdou\Algebra\Expression\Node\ArrayNode;
use Nalabdou\Algebra\Expression\Node\BinaryNode;
use Nalabdou\Algebra\Expression\Node\CallNode;
use Nalabdou\Algebra\Expression\Node\LiteralNode;
use Nalabdou\Algebra\Expression\Node\NameNode;
use Nalabdou\Algebra\Expression\Node\Node;
use Nalabdou\Algebra\Expression\Node\PropertyNode;
use Nalabdou\Algebra\Expression\Node\SubscriptNode;
use Nalabdou\Algebra\Expression\Node\TernaryNode;
use Nalabdou\Algebra\Expression\Node\UnaryNode;

/**
 * Walks an AST produced by {@see Parser} and evaluates it against a context.
 *
 * The context is an associative array of variable bindings built by
 * {@see ExpressionEvaluator}: `item` holds the full row, and each top-level
 * array key is also bound directly for shorthand access.
 *
 * Built-in functions:
 *
 * | Name | Signature | Description |
 * |------|-----------|-------------|
 * | `length` | `length(v)` | strlen or count |
 * | `lower` / `upper` | `lower(v)` | Case conversion |
 * | `trim` / `ltrim` / `rtrim` | `trim(v)` | Whitespace trimming |
 * | `abs` / `round` / `ceil` / `floor` | | Numeric operations |
 * | `contains` / `starts` / `ends` | `contains(h, n)` | String search |
 * | `substr` | `substr(s, start, len?)` | Substring extraction |
 * | `replace` | `replace(s, find, replace)` | String replacement |
 * | `split` / `join` | `split(s, sep)` | String/array conversion |
 * | `int` / `float` / `str` / `bool` | `int(v)` | Type casting |
 * | `count` | `count(v)` | Array count |
 * | `min` / `max` / `clamp` | `clamp(v, min, max)` | Range operations |
 * | `date` / `now` | `date(fmt, ts?)` | Date formatting |
 * | `isset` / `empty` | | Null/empty checks |
 *
 * @internal
 */
final class Evaluator
{
    /**
     * Evaluate an AST node against a variable context.
     *
     * @param Node                 $node    the root AST node
     * @param array<string, mixed> $context variable bindings
     *
     * @throws \RuntimeException on division by zero or unknown function name
     */
    public function eval(Node $node, array $context): mixed
    {
        return match (true) {
            $node instanceof LiteralNode => $node->value,
            $node instanceof NameNode => $context[$node->name] ?? null,
            $node instanceof SubscriptNode => $this->evalSubscript($node, $context),
            $node instanceof PropertyNode => $this->evalProperty($node, $context),
            $node instanceof BinaryNode => $this->evalBinary($node, $context),
            $node instanceof UnaryNode => $this->evalUnary($node, $context),
            $node instanceof CallNode => $this->evalCall($node, $context),
            $node instanceof TernaryNode => $this->evalTernary($node, $context),
            $node instanceof ArrayNode => $this->evalArray($node, $context),
            default => throw new \RuntimeException('Unknown AST node type: '.$node::class),
        };
    }

    private function evalSubscript(SubscriptNode $node, array $context): mixed
    {
        $obj = $this->eval($node->object, $context);
        $key = $this->eval($node->key, $context);

        return match (true) {
            \is_array($obj) => $obj[$key] ?? null,
            \is_object($obj) => $obj->{$key} ?? null,
            default => null,
        };
    }

    private function evalProperty(PropertyNode $node, array $context): mixed
    {
        $obj = $this->eval($node->object, $context);

        if (\is_array($obj)) {
            return $obj[$node->property] ?? null;
        }

        if (\is_object($obj)) {
            foreach (['get', 'is', 'has', ''] as $prefix) {
                $method = $prefix.\ucfirst($node->property);
                if (\method_exists($obj, $method)) {
                    return $obj->{$method}();
                }
            }

            return \property_exists($obj, $node->property) ? $obj->{$node->property} : null;
        }

        return null;
    }

    private function evalBinary(BinaryNode $node, array $context): mixed
    {
        if ('and' === $node->operator || '&&' === $node->operator) {
            return (bool) $this->eval($node->left, $context)
                ? (bool) $this->eval($node->right, $context)
                : false;
        }

        if ('or' === $node->operator || '||' === $node->operator) {
            return (bool) $this->eval($node->left, $context)
                ? true
                : (bool) $this->eval($node->right, $context);
        }

        if ('in' === $node->operator) {
            $value = $this->eval($node->left, $context);
            $list = $this->eval($node->right, $context);

            return \is_array($list) && \in_array($value, $list, strict: false);
        }

        $left = $this->eval($node->left, $context);
        $right = $this->eval($node->right, $context);

        return match ($node->operator) {
            '==' => $left == $right,
            '!=' => $left != $right,
            '>' => $left > $right,
            '>=' => $left >= $right,
            '<' => $left < $right,
            '<=' => $left <= $right,
            '+' => $left + $right,
            '-' => $left - $right,
            '*' => $left * $right,
            '/' => 0 == $right ? (throw new \RuntimeException('Division by zero in expression')) : $left / $right,
            '%' => 0 == $right ? (throw new \RuntimeException('Modulo by zero in expression')) : $left % $right,
            '**' => $left ** $right,
            '~' => (string) $left.(string) $right,
            default => throw new \RuntimeException("Unknown binary operator: {$node->operator}"),
        };
    }

    private function evalUnary(UnaryNode $node, array $context): mixed
    {
        $val = $this->eval($node->operand, $context);

        return match ($node->operator) {
            'not', '!' => !(bool) $val,
            '-' => -$val,
            default => throw new \RuntimeException("Unknown unary operator: {$node->operator}"),
        };
    }

    private function evalCall(CallNode $node, array $context): mixed
    {
        $args = \array_map(fn (Node $arg) => $this->eval($arg, $context), $node->arguments);
        $a = static fn (int $i): mixed => $args[$i] ?? null;

        return match ($node->name) {
            'length' => \is_array($a(0)) ? \count($a(0)) : \strlen((string) $a(0)),
            'lower' => \strtolower((string) $a(0)),
            'upper' => \strtoupper((string) $a(0)),
            'trim' => \trim((string) $a(0)),
            'ltrim' => \ltrim((string) $a(0)),
            'rtrim' => \rtrim((string) $a(0)),
            'abs' => \abs((float) $a(0)),
            'round' => \round((float) $a(0), (int) ($a(1) ?? 2)),
            'ceil' => \ceil((float) $a(0)),
            'floor' => \floor((float) $a(0)),
            'contains' => \str_contains((string) $a(0), (string) $a(1)),
            'starts' => \str_starts_with((string) $a(0), (string) $a(1)),
            'ends' => \str_ends_with((string) $a(0), (string) $a(1)),
            'substr' => isset($args[2])
                ? \substr((string) $a(0), (int) $a(1), (int) $a(2))
                : \substr((string) $a(0), (int) $a(1)),
            'replace' => \str_replace((string) $a(1), (string) $a(2), (string) $a(0)),
            'split' => \explode((string) $a(1), (string) $a(0)),
            'join' => \implode((string) $a(1), (array) $a(0)),
            'int' => (int) $a(0),
            'float' => (float) $a(0),
            'str' => (string) $a(0),
            'bool' => (bool) $a(0),
            'count' => \is_array($a(0)) ? \count($a(0)) : 0,
            'min' => \min($a(0), $a(1)),
            'max' => \max($a(0), $a(1)),
            'clamp' => \min(\max($a(0), $a(1)), $a(2)),
            'date' => \date((string) $a(0), (int) ($a(1) ?? \time())),
            'now' => \time(),
            'isset' => null !== $a(0),
            'empty' => empty($a(0)),
            default => throw new \RuntimeException("Unknown function '{$node->name}'. ".'Available: length, lower, upper, trim, ltrim, rtrim, abs, round, ceil, floor, contains, starts, ends, substr, replace, split, join, int, float, str, bool, count, min, max, clamp, date, now, isset, empty'),
        };
    }

    private function evalTernary(TernaryNode $node, array $context): mixed
    {
        return (bool) $this->eval($node->condition, $context)
            ? $this->eval($node->then, $context)
            : $this->eval($node->else, $context);
    }

    private function evalArray(ArrayNode $node, array $context): array
    {
        return \array_map(fn (Node $el) => $this->eval($el, $context), $node->elements);
    }
}
