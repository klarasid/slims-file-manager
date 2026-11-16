<?php
/**
 * File Manager plugin — safe access to the attachment repository (REPOBS).
 *
 * Every filesystem path is built from the repository root and then checked with
 * realpath() so it can never escape the root (path traversal, symlinks, null
 * bytes, absolute paths).
 */

namespace SlimsFileManager;

defined('INDEX_AUTH') or exit('Direct access is not allowed!');

final class Disk
{
    /** Files that belong to the repository itself and must never be touched. */
    private const PROTECTED_NAMES = ['index.html', 'index.php', '.htaccess', 'web.config'];

    private const MAX_DIR_DEPTH = 5;

    private static ?string $root = null;

    /**
     * Real path of the repository root, without trailing separator.
     */
    public static function root(): string
    {
        if (self::$root === null) {
            $real = realpath(REPOBS);
            if ($real === false || !is_dir($real)) {
                throw new \RuntimeException('Repository directory is not available');
            }
            self::$root = rtrim($real, DIRECTORY_SEPARATOR);
        }
        return self::$root;
    }

    /**
     * Normalise a stored `file_dir` value to "a/b/c" (no leading/trailing slash).
     * Returns null for anything suspicious.
     */
    public static function normalizeDir(?string $dir): ?string
    {
        $dir = (string)$dir;
        if (str_contains($dir, "\0")) return null;
        $dir = trim(str_replace('\\', '/', $dir), '/ ');
        if ($dir === '') return '';
        if (preg_match('#^[a-zA-Z]:#', $dir)) return null; // Windows drive letter

        $parts = explode('/', $dir);
        foreach ($parts as $part) {
            if ($part === '' || $part === '.' || $part === '..') return null;
        }
        return implode('/', $parts);
    }

    /**
     * Validate a bare file name (no directory component).
     */
    public static function isSafeName(?string $name): bool
    {
        $name = (string)$name;
        return $name !== ''
            && !str_contains($name, "\0")
            && !str_contains($name, '/')
            && !str_contains($name, '\\')
            && $name !== '.' && $name !== '..'
            && !in_array(strtolower($name), self::PROTECTED_NAMES, true);
    }

    /**
     * Absolute path of an existing file inside the repository, or null when the
     * file does not exist or would resolve outside of the repository.
     */
    public static function existingPath(?string $dir, ?string $name): ?string
    {
        $dir = self::normalizeDir($dir);
        if ($dir === null || !self::isSafeName($name)) return null;

        $candidate = self::root() . DIRECTORY_SEPARATOR
            . ($dir !== '' ? str_replace('/', DIRECTORY_SEPARATOR, $dir) . DIRECTORY_SEPARATOR : '')
            . $name;

        $real = realpath($candidate);
        if ($real === false || !is_file($real)) return null;
        if (!str_starts_with($real, self::root() . DIRECTORY_SEPARATOR)) return null;

        return $real;
    }

    /**
     * Absolute path of an existing directory inside the repository.
     */
    public static function directoryPath(string $dir): ?string
    {
        $dir = self::normalizeDir($dir);
        if ($dir === null) return null;
        if ($dir === '') return self::root();

        $real = realpath(self::root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir));
        if ($real === false || !is_dir($real)) return null;
        if (!str_starts_with($real, self::root() . DIRECTORY_SEPARATOR)) return null;
        return $real;
    }

    public static function size(?string $dir, ?string $name): ?int
    {
        $path = self::existingPath($dir, $name);
        if ($path === null) return null;
        $size = @filesize($path);
        return $size === false ? null : $size;
    }

    /**
     * Delete a file from the repository. Returns true when the file is gone.
     */
    public static function delete(?string $dir, ?string $name): bool
    {
        $path = self::existingPath($dir, $name);
        if ($path === null) return false;
        return @unlink($path);
    }

    /**
     * List of sub directories (relative, "a/b") the user may upload into.
     * '' represents the repository root.
     *
     * @return string[]
     */
    public static function directories(): array
    {
        $dirs = [''];
        $root = self::root();
        $walk = function (string $absolute, string $relative, int $depth) use (&$walk, &$dirs) {
            if ($depth > self::MAX_DIR_DEPTH) return;
            $entries = @scandir($absolute);
            if ($entries === false) return;
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) continue;
                $path = $absolute . DIRECTORY_SEPARATOR . $entry;
                if (!is_dir($path) || is_link($path)) continue;
                $rel = $relative === '' ? $entry : $relative . '/' . $entry;
                if (self::normalizeDir($rel) !== $rel) continue;
                $dirs[] = $rel;
                $walk($path, $rel, $depth + 1);
            }
        };
        $walk($root, '', 1);
        sort($dirs, SORT_NATURAL | SORT_FLAG_CASE);
        return $dirs;
    }

    /**
     * Scan the repository for regular files.
     *
     * @return array<int, array{dir:string,name:string,size:int,mtime:int}>
     */
    public static function scanFiles(int $limit = 5000): array
    {
        $files = [];
        $root = self::root();
        $walk = function (string $absolute, string $relative, int $depth) use (&$walk, &$files, $limit) {
            if ($depth > self::MAX_DIR_DEPTH + 1 || count($files) >= $limit) return;
            $entries = @scandir($absolute);
            if ($entries === false) return;
            foreach ($entries as $entry) {
                if (count($files) >= $limit) return;
                if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) continue;
                $path = $absolute . DIRECTORY_SEPARATOR . $entry;
                if (is_link($path)) continue;
                if (is_dir($path)) {
                    $walk($path, $relative === '' ? $entry : $relative . '/' . $entry, $depth + 1);
                    continue;
                }
                if (!is_file($path) || !self::isSafeName($entry)) continue;
                $files[] = [
                    'dir' => $relative,
                    'name' => $entry,
                    'size' => (int)@filesize($path),
                    'mtime' => (int)@filemtime($path),
                ];
            }
        };
        $walk($root, '', 1);
        return $files;
    }

    /**
     * Key used to compare database rows with files on disk.
     */
    public static function key(?string $dir, ?string $name): string
    {
        $dir = self::normalizeDir($dir) ?? '';
        return ($dir === '' ? '' : $dir . '/') . (string)$name;
    }
}
