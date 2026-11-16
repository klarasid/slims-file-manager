<?php
/**
 * File Manager plugin — view helpers.
 *
 * Every value that reaches HTML must go through View::e() (or View::attr()).
 */

namespace SlimsFileManager;

defined('INDEX_AUTH') or exit('Direct access is not allowed!');

final class View
{
    /** @var string Absolute path of the views directory */
    private static string $viewDir = '';

    public static function setViewDir(string $dir): void
    {
        self::$viewDir = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Escape any scalar for HTML text and attribute context.
     */
    public static function e($value): string
    {
        if ($value === null) return '';
        if (is_bool($value)) $value = $value ? '1' : '0';
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    /**
     * Encode data for safe embedding inside a <script> block or data-* attribute.
     */
    public static function json($value): string
    {
        return (string)json_encode(
            $value,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    /**
     * Build a URL to this plugin page. The base (mod & id) always comes from the
     * values already validated by admin/plugin_container.php, never from PHP_SELF.
     */
    public static function url(array $params = []): string
    {
        $base = [
            'mod' => self::pluginParam('mod'),
            'id' => self::pluginParam('id'),
        ];
        $params = array_filter($params, fn($v) => $v !== null && $v !== '');
        return AWB . 'plugin_container.php?' . http_build_query($base + $params, '', '&', PHP_QUERY_RFC3986);
    }

    private static function pluginParam(string $key): string
    {
        $value = (string)($_GET[$key] ?? '');
        $pattern = $key === 'id' ? '/^[a-f0-9]{32}$/' : '/^[a-z0-9_]{1,64}$/';
        return preg_match($pattern, $value) ? $value : '';
    }

    /**
     * Asset URL inside this plugin directory.
     */
    public static function asset(string $file): string
    {
        $pluginDir = basename(dirname(__DIR__));
        return SWB . 'plugins/' . rawurlencode($pluginDir) . '/assets/' . $file . '?v=' . FM_VERSION;
    }

    /**
     * Render an inline Lucide icon (https://lucide.dev, ISC license).
     */
    public static function icon(string $name, string $class = ''): string
    {
        $paths = Icons::get($name);
        if ($paths === '') return '';
        return '<svg class="fm-icon ' . self::e($class) . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" '
            . 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" '
            . 'aria-hidden="true" focusable="false">' . $paths . '</svg>';
    }

    /**
     * Button/link content: icon + escaped label.
     */
    public static function label(string $icon, string $text): string
    {
        return self::icon($icon) . '<span>' . self::e($text) . '</span>';
    }

    public static function bytes(?int $bytes): string
    {
        if ($bytes === null) return '—';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $size = (float)$bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        if ($i === 0) return (string)(int)$size . ' ' . $units[$i];
        [$dec, $thousands] = Lang::separators();
        return number_format($size, 1, $dec, $thousands) . ' ' . $units[$i];
    }

    /**
     * Integer with the locale's thousands separator (40,700 / 40.700).
     */
    public static function number(int $value): string
    {
        [$dec, $thousands] = Lang::separators();
        return number_format($value, 0, $dec, $thousands);
    }

    public static function date(?string $datetime): string
    {
        if (empty($datetime) || str_starts_with($datetime, '0000')) return '—';
        $ts = strtotime($datetime);
        if (!$ts) return '—';
        // Month names go through the translator (Mei, Agu, Okt, Des in id_ID).
        return date('d', $ts) . ' ' . t(date('M', $ts)) . ' ' . date('Y H:i', $ts);
    }

    /**
     * Short, human friendly label for a MIME type (PDF, DOCX, URL, …).
     */
    public static function mimeLabel(?string $mime, ?string $fileName = null): string
    {
        $mime = strtolower(trim((string)$mime));
        $known = [
            'application/pdf' => 'PDF',
            'text/uri-list' => 'URL',
            'application/msword' => 'DOC',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'DOCX',
            'application/vnd.ms-excel' => 'XLS',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'XLSX',
            'application/vnd.ms-powerpoint' => 'PPT',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'PPTX',
            'application/vnd.oasis.opendocument.text' => 'ODT',
            'application/vnd.oasis.opendocument.spreadsheet' => 'ODS',
            'application/vnd.oasis.opendocument.presentation' => 'ODP',
            'application/rtf' => 'RTF', 'text/rtf' => 'RTF',
            'text/plain' => 'TXT',
            'application/xml' => 'XML', 'text/xml' => 'XML',
            'application/marc' => 'MARC',
            'image/jpeg' => 'JPG', 'image/png' => 'PNG', 'image/gif' => 'GIF',
            'video/mp4' => 'MP4', 'video/x-msvideo' => 'AVI', 'video/mpeg' => 'MPEG',
            'video/x-flv' => 'FLV', 'video/x-matroska' => 'MKV',
            'audio/mpeg' => 'MP3', 'audio/ogg' => 'OGG', 'video/ogg' => 'OGG',
            'application/zip' => 'ZIP',
        ];
        if (isset($known[$mime])) return $known[$mime];

        $ext = strtoupper(pathinfo((string)$fileName, PATHINFO_EXTENSION));
        if ($ext !== '' && preg_match('/^[A-Z0-9]{1,6}$/', $ext)) return $ext;
        if ($mime === '') return '—';

        $sub = substr($mime, (int)strpos($mime, '/') + 1);
        $sub = preg_replace('/^(x-|vnd\.)/', '', $sub) ?? $sub;
        return strtoupper(self::truncate($sub, 10));
    }

    /**
     * Shorten text to $max characters (multibyte safe) with an ellipsis.
     */
    public static function truncate(?string $text, int $max): string
    {
        $text = (string)$text;
        return mb_strlen($text) > $max ? rtrim(mb_substr($text, 0, $max - 1)) . '…' : $text;
    }

    /**
     * Render a template from the views directory with an isolated scope.
     */
    public static function render(string $template, array $data = []): void
    {
        if (!preg_match('/^[a-z_]+$/', $template)) {
            throw new \InvalidArgumentException('Invalid template name');
        }
        $file = self::$viewDir . $template . '.php';
        if (!is_file($file)) {
            throw new \InvalidArgumentException('Template not found');
        }
        (static function (string $__file, array $__data) {
            extract($__data, EXTR_SKIP);
            require $__file;
        })($file, $data);
    }
}
