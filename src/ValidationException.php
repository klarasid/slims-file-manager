<?php
/**
 * File Manager plugin — user-facing validation error.
 * Its message is safe to show to the user; any other exception is not.
 */

namespace SlimsFileManager;

defined('INDEX_AUTH') or exit('Direct access is not allowed!');

final class ValidationException extends \RuntimeException
{
    /** @var array<string,string> field => message */
    private array $errors;

    public function __construct(string $message, array $errors = [])
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
