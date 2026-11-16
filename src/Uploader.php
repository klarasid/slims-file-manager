<?php
/**
 * File Manager plugin — upload validation and storage.
 *
 * The uploaded file is validated while it is still in PHP's temporary
 * directory; it only reaches the repository once every check has passed.
 */

namespace SlimsFileManager;

defined('INDEX_AUTH') or exit('Direct access is not allowed!');

final class Uploader
{
    /**
     * Extensions that are never accepted, even if listed in allowed_file_att.
     * Also checked against every "inner" extension (e.g. shell.php.pdf).
     */
    private const DENIED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'phtml', 'phar', 'phps', 'inc',
        'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'bat', 'cmd', 'com', 'exe', 'dll', 'msi', 'jar',
        'js', 'mjs', 'jsp', 'asp', 'aspx', 'html', 'htm', 'xhtml', 'shtml', 'svg', 'svgz',
        'htaccess', 'htpasswd', 'ini', 'swf',
    ];

    /** MIME types detected from content that are never accepted. */
    private const DENIED_MIMES = [
        'text/html', 'application/xhtml+xml', 'image/svg+xml', 'application/javascript',
        'text/javascript', 'application/x-php', 'text/x-php', 'application/x-httpd-php',
        'application/x-sh', 'text/x-shellscript', 'application/x-executable',
        'application/x-dosexec', 'application/x-msdownload',
    ];

    /**
     * Accepted content MIME types per extension. The first entry is the
     * canonical type stored in files.mime_type.
     */
    private const MIME_MAP = [
        'pdf' => ['application/pdf'],
        'rtf' => ['application/rtf', 'text/rtf'],
        'txt' => ['text/plain'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'odp' => ['application/vnd.oasis.opendocument.presentation', 'application/zip'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
        'doc' => ['application/msword', 'application/CDFV2', 'application/x-ole-storage', 'application/vnd.ms-office'],
        'xls' => ['application/vnd.ms-excel', 'application/CDFV2', 'application/x-ole-storage', 'application/vnd.ms-office'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/CDFV2', 'application/x-ole-storage', 'application/vnd.ms-office'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
        'avi' => ['video/x-msvideo', 'video/avi', 'video/msvideo'],
        'mpeg' => ['video/mpeg'],
        'mp4' => ['video/mp4', 'audio/mp4', 'application/mp4'],
        'flv' => ['video/x-flv'],
        'mkv' => ['video/x-matroska', 'application/x-matroska', 'video/webm'],
        'ogg' => ['audio/ogg', 'video/ogg', 'application/ogg'],
        'mp3' => ['audio/mpeg', 'audio/mp3', 'audio/x-mpeg'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'xml' => ['application/xml', 'text/xml'],
        'mrc' => ['application/marc', 'application/octet-stream', 'text/plain'],
    ];

    /** Types the browser may display inline when previewing. */
    private const INLINE_MIMES = [
        'application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'text/plain',
        'video/mp4', 'video/ogg', 'audio/ogg', 'audio/mpeg', 'video/webm',
    ];

    /**
     * Validate $_FILES[$field].
     *
     * @return array{tmp:string, original:string, ext:string, mime:string, size:int}
     */
    public static function validate(string $field): array
    {
        $file = $_FILES[$field] ?? null;
        if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
            throw new ValidationException(t('Please choose a file to upload.'), [$field => t('Please choose a file to upload.')]);
        }

        switch ((int)$file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new ValidationException(self::sizeMessage(), [$field => self::sizeMessage()]);
            case UPLOAD_ERR_NO_FILE:
                throw new ValidationException(t('Please choose a file to upload.'), [$field => t('Please choose a file to upload.')]);
            case UPLOAD_ERR_PARTIAL:
                throw new ValidationException(t('The file was only partially uploaded. Please try again.'));
            default:
                throw new ValidationException(t('The server could not store the uploaded file.'));
        }

        $tmp = (string)$file['tmp_name'];
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new ValidationException(t('Invalid upload.'));
        }

        $size = (int)@filesize($tmp);
        if ($size <= 0) {
            throw new ValidationException(t('The uploaded file is empty.'), [$field => t('The uploaded file is empty.')]);
        }
        if ($size > self::maxBytes()) {
            throw new ValidationException(self::sizeMessage(), [$field => self::sizeMessage()]);
        }

        $original = self::cleanOriginalName((string)$file['name']);
        $segments = explode('.', strtolower($original));
        $ext = count($segments) > 1 ? (string)end($segments) : '';

        if ($ext === '' || !preg_match('/^[a-z0-9]{1,10}$/', $ext)) {
            throw new ValidationException(t('The file has no valid extension.'), [$field => t('The file has no valid extension.')]);
        }

        // Every extension segment (not only the last one) must be harmless.
        foreach (array_slice($segments, 1) as $segment) {
            if (in_array($segment, self::DENIED_EXTENSIONS, true)) {
                throw new ValidationException(t('This file type is not allowed.'), [$field => t('This file type is not allowed.')]);
            }
        }

        if (!in_array($ext, self::allowedExtensions(), true)) {
            $msg = sprintf(t('Extension .%s is not allowed. Allowed: %s'), $ext, implode(', ', self::allowedExtensions()));
            throw new ValidationException($msg, [$field => $msg]);
        }

        $detected = self::detectMime($tmp);
        if ($detected === '' || in_array($detected, self::DENIED_MIMES, true)) {
            throw new ValidationException(t('The file content is not allowed.'), [$field => t('The file content is not allowed.')]);
        }

        $accepted = self::acceptedMimes($ext);
        if ($accepted !== [] && !in_array($detected, $accepted, true)) {
            $msg = t('The file content does not match its extension.');
            throw new ValidationException($msg, [$field => $msg]);
        }

        return [
            'tmp' => $tmp,
            'original' => $original,
            'ext' => $ext,
            'mime' => self::canonicalMime($ext, $detected),
            'size' => $size,
        ];
    }

    /**
     * Move a validated upload into the repository directory $dir.
     * Returns the stored (random) file name.
     */
    public static function store(array $upload, string $dir): string
    {
        $target = Disk::directoryPath($dir);
        if ($target === null) {
            throw new ValidationException(t('The destination folder is not valid.'), ['fileDir' => t('The destination folder is not valid.')]);
        }
        if (!is_writable($target)) {
            throw new ValidationException(t('The destination folder is not writable.'));
        }

        $name = bin2hex(random_bytes(16)) . '.' . $upload['ext'];
        $path = $target . DIRECTORY_SEPARATOR . $name;

        if (!move_uploaded_file($upload['tmp'], $path)) {
            throw new ValidationException(t('The server could not store the uploaded file.'));
        }
        @chmod($path, 0644);

        if (in_array($upload['ext'], ['jpg', 'jpeg', 'png', 'gif'], true) && !self::reencodeImage($path, $upload['ext'])) {
            @unlink($path);
            throw new ValidationException(t('The image file is corrupt or not a real image.'));
        }

        return $name;
    }

    /**
     * MIME type to send when streaming a stored file.
     */
    public static function mimeForName(string $name, ?string $stored = null): string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (isset(self::MIME_MAP[$ext])) return self::MIME_MAP[$ext][0];
        $configured = $GLOBALS['sysconf']['mimetype'][$ext] ?? '';
        if (is_string($configured) && preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#i', $configured)
            && !in_array(strtolower($configured), self::DENIED_MIMES, true)) {
            return $configured;
        }
        return 'application/octet-stream';
    }

    public static function isInline(string $mime): bool
    {
        return in_array($mime, self::INLINE_MIMES, true);
    }

    /** @return string[] lower-case extensions without dot */
    public static function allowedExtensions(): array
    {
        $list = config('allowed_file_att', $GLOBALS['sysconf']['allowed_file_att'] ?? []);
        if (!is_array($list)) $list = [];
        $out = [];
        foreach ($list as $ext) {
            $ext = strtolower(ltrim(trim((string)$ext), '.'));
            if ($ext !== '' && preg_match('/^[a-z0-9]{1,10}$/', $ext) && !in_array($ext, self::DENIED_EXTENSIONS, true)) {
                $out[$ext] = $ext;
            }
        }
        return array_values($out);
    }

    public static function maxBytes(): int
    {
        $kb = (int)($GLOBALS['sysconf']['max_upload'] ?? 0);
        return $kb > 0 ? $kb * 1024 : 2 * 1024 * 1024;
    }

    private static function sizeMessage(): string
    {
        return sprintf(t('The file is larger than the maximum allowed size (%s).'), View::bytes(self::maxBytes()));
    }

    private static function detectMime(string $path): string
    {
        if (!class_exists(\finfo::class)) return '';
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);
        return is_string($mime) ? strtolower($mime) : '';
    }

    /** @return string[] lower-case MIME list; [] means "anything not denied" */
    private static function acceptedMimes(string $ext): array
    {
        if (isset(self::MIME_MAP[$ext])) return array_map('strtolower', self::MIME_MAP[$ext]);
        $configured = $GLOBALS['sysconf']['mimetype'][$ext] ?? '';
        return is_string($configured) && $configured !== '' ? [strtolower($configured)] : [];
    }

    private static function canonicalMime(string $ext, string $detected): string
    {
        if (isset(self::MIME_MAP[$ext])) return self::MIME_MAP[$ext][0];
        $configured = $GLOBALS['sysconf']['mimetype'][$ext] ?? '';
        return is_string($configured) && $configured !== '' ? $configured : $detected;
    }

    /**
     * Strip directory parts and control characters from the client file name.
     */
    public static function cleanOriginalName(string $name): string
    {
        $name = str_replace('\\', '/', $name);
        $name = basename($name);
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? '';
        $name = trim($name, " .\t");
        return mb_substr($name, 0, 200);
    }

    /**
     * Re-encode an image with GD: strips metadata (EXIF/GPS) and any payload
     * appended to the image (polyglot files). Returns false if not an image.
     */
    private static function reencodeImage(string $path, string $ext): bool
    {
        $info = @getimagesize($path);
        if ($info === false) return false;

        $expected = ['jpg' => IMAGETYPE_JPEG, 'jpeg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG, 'gif' => IMAGETYPE_GIF][$ext];
        if ((int)$info[2] !== $expected) return false;

        // Without GD the type check above is all we can do. GIFs are not
        // re-encoded because GD would drop their animation frames.
        if (!function_exists('imagecreatefromstring') || $expected === IMAGETYPE_GIF) return true;

        // Refuse decompression bombs.
        if ((int)$info[0] * (int)$info[1] > 40000000) return false;

        $image = @imagecreatefromstring((string)file_get_contents($path));
        if ($image === false) return false;

        $ok = match ($expected) {
            IMAGETYPE_JPEG => imagejpeg($image, $path, 90),
            IMAGETYPE_PNG => (function () use ($image, $path) {
                imagesavealpha($image, true);
                return imagepng($image, $path, 6);
            })(),
        };
        imagedestroy($image);
        return (bool)$ok;
    }
}
