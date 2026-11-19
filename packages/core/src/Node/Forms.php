<?php

declare(strict_types=1);

namespace Phactorial\Node;

use Phactorial\OperatorDictionary;

/**
 * Operator forms by position. An operator is prefix when it is first in its row or follows another
 * operator or an opening fence; postfix when it is last and follows an operand, or is a closing
 * fence; infix otherwise. An operator whose source fixes its form (mo form=) keeps it. A closing fence counts as an operand for what comes after it, so ") - b" is infix.
 *
 * As in TeX, where a Close atom leaves a Rel or Bin as it is, a postfix-only operator that follows an operand
 * (`!`, `′`) is postfix and also counts as an operand: "n! + b" and "n!!" resolve as ") + b" does. A relation
 * (thick space on both sides, from lspace and rspace when set, else the dictionary) is never prefix unless it
 * is the first item of its row, so after another operator it is infix: "x ∼ ∼ b".
 *
 * The bars `|` and `‖` are the same character on both sides, so within a row they pair up: the 1st, 3rd, ...
 * of a kind opens (prefix) and the 2nd, 4th, ... closes (postfix, an operand for what follows). A last bar
 * with no partner ("a | b") is a relation: infix.
 *
 * @internal Engine detail, not public API. It may change in any release.
 */
final class Forms
{
    /**
     * @param list<Node> $children
     * @return list<?string> per child: prefix, infix or postfix for operators, null for anything else
     */
    public static function of(array $children): array
    {
        $forms = [];
        $count = count($children);
        $prevIsOperand = false;
        $barTotals = [];
        foreach ($children as $child) {
            $child = Fallback::unwrap($child);
            if ($child instanceof Operator && $child->form === null && self::isBar($child->char)) {
                $barTotals[$child->char] = ($barTotals[$child->char] ?? 0) + 1;
            }
        }
        $barSeen = [];
        $first = true;
        // Space-like children (mspace) take no part in inferring forms: they are skipped, as in MathML Core.
        $last = $count - 1;
        while ($last > 0 && self::spaceLike($children[$last])) {
            $last--;
        }
        foreach ($children as $i => $child) {
            $child = Fallback::unwrap($child);
            $wasFirst = $first;
            $first = $first && self::spaceLike($child);
            if (! ($child instanceof Operator)) {
                $forms[] = null;
                $prevIsOperand = $prevIsOperand || ! self::spaceLike($child);
                continue;
            }
            if ($child->form !== null) {
                $forms[] = $child->form;
                $prevIsOperand = $child->form === OperatorDictionary::FORM_POSTFIX;
                continue;
            }
            if (self::isBar($child->char)) {
                $k = $barSeen[$child->char] = ($barSeen[$child->char] ?? 0) + 1;
                if ($k === $barTotals[$child->char] && $k % 2 === 1) {
                    $forms[] = $prevIsOperand ? OperatorDictionary::FORM_INFIX : OperatorDictionary::FORM_PREFIX;
                    $prevIsOperand = false;
                } elseif ($k % 2 === 1) {
                    $forms[] = OperatorDictionary::FORM_PREFIX;
                    $prevIsOperand = false;
                } else {
                    $forms[] = OperatorDictionary::FORM_POSTFIX;
                    $prevIsOperand = true;
                }
                continue;
            }
            $closing = OperatorDictionary::isClosing($child->char) || ($prevIsOperand && self::postfixOnly($child->char));
            $forms[] = match (true) {
                $closing => OperatorDictionary::FORM_POSTFIX,
                ! $prevIsOperand && ($wasFirst || ! self::isRelation($child)) => OperatorDictionary::FORM_PREFIX,
                $i >= $last => OperatorDictionary::FORM_POSTFIX,
                default => OperatorDictionary::FORM_INFIX,
            };
            $prevIsOperand = $closing;
        }

        return $forms;
    }

    /** A Space, or a Phantom of space-like content: occupies room but is no operand. */
    public static function spaceLike(Node $n): bool
    {
        $n = Fallback::unwrap($n);

        return $n instanceof Space || ($n instanceof Phantom && self::spaceLike($n->content));
    }

    /** An operator that is only ever postfix, like `!`: it ends an operand and leaves one behind. */
    private static function postfixOnly(string $char): bool
    {
        return OperatorDictionary::hasForm($char, OperatorDictionary::FORM_POSTFIX)
            && ! OperatorDictionary::hasForm($char, OperatorDictionary::FORM_INFIX);
    }

    /** Thick space on both sides, the explicit lspace and rspace when set, else the infix entry. */
    private static function isRelation(Operator $op): bool
    {
        if ($op->lspace !== null && $op->rspace !== null) {
            return $op->lspace >= 5 && $op->rspace >= 5;
        }
        if (! OperatorDictionary::hasForm($op->char, OperatorDictionary::FORM_INFIX)) {
            return false;
        }
        $entry = OperatorDictionary::lookup($op->char, OperatorDictionary::FORM_INFIX);

        return ($op->lspace ?? $entry['lspace']) >= 5 && ($op->rspace ?? $entry['rspace']) >= 5;
    }

    private static function isBar(string $char): bool
    {
        return $char === '|' || $char === '‖';
    }
}
