<?php
/**
 * File Manager plugin — CSRF protection (synchronizer token pattern).
 *
 * The token is a 256-bit value from a CSPRNG, bound to the admin session and
 * compared in constant time. It is accepted from the POST body or the
 * X-CSRF-Token header (fetch requests).
 */

namespace SlimsFileManager;

defined('INDEX_AUTH') or exit('Direct access is not allowed!');

final class Csrf
{
    private const SESSION_KEY = 'fm_csrf_token';
    public const FIELD = '_fm_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . View::e(self::token()) . '">';
    }

    public static function verify(): bool
    {
        $expected = $_SESSION[self::SESSION_KEY] ?? '';
        if (!is_string($expected) || $expected === '') return false;

        $submitted = $_POST[self::FIELD] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($submitted) || $submitted === '') return false;

        return hash_equals($expected, $submitted);
    }
}
