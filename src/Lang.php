<?php
/**
 * File Manager plugin — translations.
 *
 * Strings are written in English (SLiMS convention). When the admin locale
 * has a dictionary in ../lang/<locale>.php it is used; otherwise the string
 * goes through SLiMS' own __() so core translations still apply.
 */

namespace SlimsFileManager;

defined('INDEX_AUTH') or exit('Direct access is not allowed!');

final class Lang
{
    private static ?array $dictionary = null;

    public static function get(string $text): string
    {
        if (self::$dictionary === null) {
            self::$dictionary = [];
            $locale = (string)($GLOBALS['sysconf']['default_lang'] ?? 'en_US');
            if (preg_match('/^[a-z]{2}_[A-Z]{2}$/', $locale)) {
                $file = dirname(__DIR__) . '/lang/' . $locale . '.php';
                if (is_file($file)) {
                    $loaded = require $file;
                    if (is_array($loaded)) self::$dictionary = $loaded;
                }
            }
        }
        return self::$dictionary[$text] ?? __($text);
    }

    /**
     * [decimal separator, thousands separator] for the admin locale.
     */
    public static function separators(): array
    {
        $locale = (string)($GLOBALS['sysconf']['default_lang'] ?? 'en_US');
        $commaDecimal = ['id', 'de', 'nl', 'es', 'it', 'pt', 'tr', 'fr', 'ru'];
        return in_array(substr($locale, 0, 2), $commaDecimal, true) ? [',', '.'] : ['.', ','];
    }
}

/**
 * Translate (and optionally sprintf) a UI string.
 */
function t(string $text, ...$args): string
{
    $translated = Lang::get($text);
    return $args ? vsprintf($translated, $args) : $translated;
}
