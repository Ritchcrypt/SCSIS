<?php

namespace App\Support;

final class SqlLikePattern
{
    private const ESCAPE_CHARACTER = '!';

    /**
     * Normalize a user-supplied search term before it reaches a query.
     */
    public static function normalize(
        mixed $value,
        int $maximumLength = 200
    ): ?string {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim(
            (string) $value
        );

        if ($value === '') {
            return null;
        }

        /*
        | Remove control characters that can create inconsistent database,
        | logging, or HTML behaviour. Newlines and tabs are normalized to spaces.
        */
        $value = preg_replace(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
            ' ',
            $value
        );

        $value = preg_replace(
            '/[\r\n\t]+/u',
            ' ',
            (string) $value
        );

        $value = trim(
            preg_replace(
                '/\s{2,}/u',
                ' ',
                (string) $value
            )
        );

        if ($value === '') {
            return null;
        }

        $maximumLength = max(
            1,
            $maximumLength
        );

        if (function_exists('mb_substr')) {
            $value = mb_substr(
                $value,
                0,
                $maximumLength,
                'UTF-8'
            );
        } else {
            $value = substr(
                $value,
                0,
                $maximumLength
            );
        }

        return $value !== ''
            ? $value
            : null;
    }

    /**
     * Build a literal "contains" pattern.
     *
     * Percent and underscore are escaped so they are treated as user data,
     * not as SQL wildcard operators.
     */
    public static function contains(
        mixed $value,
        int $maximumLength = 200
    ): ?string {
        $value = self::normalize(
            $value,
            $maximumLength
        );

        if ($value === null) {
            return null;
        }

        $escaped = str_replace(
            [
                self::ESCAPE_CHARACTER,
                '%',
                '_',
            ],
            [
                self::ESCAPE_CHARACTER
                    . self::ESCAPE_CHARACTER,
                self::ESCAPE_CHARACTER . '%',
                self::ESCAPE_CHARACTER . '_',
            ],
            $value
        );

        return '%' . $escaped . '%';
    }

    /**
     * Add a bound LIKE condition with an explicitly declared escape character.
     */
    public static function whereContains(
        mixed $query,
        string $column,
        string $pattern
    ): mixed {
        return $query->whereRaw(
            SafeDatabaseIdentifier::wrap($column)
                . " LIKE ? ESCAPE '"
                . self::ESCAPE_CHARACTER
                . "'",
            [
                $pattern,
            ]
        );
    }

    /**
     * Add a bound OR LIKE condition with an explicitly declared escape
     * character.
     */
    public static function orWhereContains(
        mixed $query,
        string $column,
        string $pattern
    ): mixed {
        return $query->orWhereRaw(
            SafeDatabaseIdentifier::wrap($column)
                . " LIKE ? ESCAPE '"
                . self::ESCAPE_CHARACTER
                . "'",
            [
                $pattern,
            ]
        );
    }
}
