<?php

declare(strict_types=1);

namespace App\Services\Notification;

use InvalidArgumentException;

/**
 * Renders notification templates by replacing {{variable}} placeholders.
 */
class TemplateRenderer
{
    /**
     * Replace declared variables in a template string.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $allowedVariables
     */
    public function render(?string $template, array $data, array $allowedVariables = []): string
    {
        if ($template === null || $template === '') {
            return '';
        }

        $this->assertNoUnknownPlaceholders($template, $allowedVariables);

        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/',
            function (array $matches) use ($data): string {
                $key = $matches[1];
                $value = data_get($data, $key);

                if ($value === null) {
                    return '';
                }

                if (is_scalar($value) || $value instanceof \Stringable) {
                    return (string) $value;
                }

                return '';
            },
            $template,
        );
    }

    /**
     * Ensure the template only uses declared variables.
     *
     * @param  list<string>  $allowedVariables
     *
     * @throws InvalidArgumentException
     */
    public function assertNoUnknownPlaceholders(string $template, array $allowedVariables): void
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', $template, $matches);

        $used = array_unique($matches[1] ?? []);
        $unknown = array_values(array_diff($used, $allowedVariables));

        if ($unknown !== []) {
            throw new InvalidArgumentException(
                'Unknown template placeholders: '.implode(', ', $unknown)
            );
        }
    }

    /**
     * Validate all channel content fields against declared variables.
     *
     * @param  array<string, string|null>  $fields
     * @param  list<string>  $allowedVariables
     *
     * @throws InvalidArgumentException
     */
    public function validateFields(array $fields, array $allowedVariables): void
    {
        foreach ($fields as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            try {
                $this->assertNoUnknownPlaceholders($value, $allowedVariables);
            } catch (InvalidArgumentException $exception) {
                throw new InvalidArgumentException(
                    "Invalid placeholders in {$field}: ".$exception->getMessage(),
                    previous: $exception,
                );
            }
        }
    }
}
