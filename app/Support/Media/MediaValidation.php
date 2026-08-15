<?php

declare(strict_types=1);

namespace App\Support\Media;

/**
 * Reusable Laravel validation rule fragments for media uploads.
 */
final class MediaValidation
{
    /**
     * Prevent instantiation.
     */
    private function __construct() {}

    /**
     * Image upload rules (avatar, logo, cover).
     *
     * @return list<string>
     */
    public static function image(bool $required = true): array
    {
        return self::rules(
            required: $required,
            maxKey: 'image',
            extensionsKey: 'image',
            mimesKey: 'image',
            extra: ['file', 'image'],
        );
    }

    /**
     * Document upload rules.
     *
     * @return list<string>
     */
    public static function document(bool $required = true): array
    {
        return self::rules(
            required: $required,
            maxKey: 'document',
            extensionsKey: 'document',
            mimesKey: 'document',
            extra: ['file'],
        );
    }

    /**
     * Video upload rules.
     *
     * @return list<string>
     */
    public static function video(bool $required = true): array
    {
        return self::rules(
            required: $required,
            maxKey: 'video',
            extensionsKey: 'video',
            mimesKey: 'video',
            extra: ['file'],
        );
    }

    /**
     * Audio upload rules.
     *
     * @return list<string>
     */
    public static function audio(bool $required = true): array
    {
        return self::rules(
            required: $required,
            maxKey: 'audio',
            extensionsKey: 'audio',
            mimesKey: 'audio',
            extra: ['file'],
        );
    }

    /**
     * @param  list<string>  $extra
     * @return list<string>
     */
    protected static function rules(
        bool $required,
        string $maxKey,
        string $extensionsKey,
        string $mimesKey,
        array $extra,
    ): array {
        $max = (int) config("media.upload_limits.{$maxKey}", 10240);
        $extensions = implode(',', config("media.extensions.{$extensionsKey}", []));
        $mimetypes = implode(',', config("media.mimes.{$mimesKey}", []));

        $rules = [$required ? 'required' : 'sometimes'];

        if (! $required) {
            $rules[] = 'nullable';
        }

        return [
            ...$rules,
            ...$extra,
            'mimes:'.$extensions,
            'mimetypes:'.$mimetypes,
            'max:'.$max,
        ];
    }
}
