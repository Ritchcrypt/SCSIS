<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SafeDatabaseIdentifier
{
    private const IDENTIFIER_PATTERN =
        '/\A[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*\z/D';

    /**
     * Validate and quote an internal table or column identifier.
     *
     * SQL parameter bindings cannot bind table or column names. Any identifier
     * used inside a raw SQL fragment must therefore be constrained before it is
     * quoted by the active database grammar.
     */
    public static function validate(
        string $identifier
    ): string {
        $identifier = trim(
            $identifier
        );

        if (
            $identifier === ''
            || preg_match(
                self::IDENTIFIER_PATTERN,
                $identifier
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Unsafe database identifier rejected.'
            );
        }

        return $identifier;
    }

    /**
     * Require an identifier to be both syntactically safe and explicitly
     * approved by the caller.
     *
     * @param array<int, string> $allowedIdentifiers
     */
    public static function approved(
        string $identifier,
        array $allowedIdentifiers
    ): string {
        $identifier = self::validate(
            $identifier
        );

        $allowedIdentifiers = array_map(
            static fn (string $allowed): string => self::validate(
                $allowed
            ),
            $allowedIdentifiers
        );

        if (! in_array(
            $identifier,
            $allowedIdentifiers,
            true
        )) {
            throw new InvalidArgumentException(
                'Unapproved database identifier rejected.'
            );
        }

        return $identifier;
    }

    public static function wrap(
        string $identifier
    ): string {
        return DB::connection()
            ->getQueryGrammar()
            ->wrap(
                self::validate(
                    $identifier
                )
            );
    }
}
