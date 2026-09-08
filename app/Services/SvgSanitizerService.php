<?php

namespace App\Services;

use enshrined\svgSanitize\Sanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Strip scriptable content from uploaded SVG before it is stored on the public disk.
 */
class SvgSanitizerService
{
    public function isSvg(UploadedFile $file): bool
    {
        $mime = strtolower((string) $file->getMimeType());
        $ext = strtolower((string) $file->getClientOriginalExtension());

        return $mime === 'image/svg+xml' || $ext === 'svg';
    }

    public function sanitizeUploadedFile(UploadedFile $file): void
    {
        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            $this->fail('Unable to read the SVG upload.');
        }

        $dirty = file_get_contents($path);
        if ($dirty === false || $dirty === '') {
            $this->fail('The SVG file is empty.');
        }

        $clean = $this->sanitize($dirty);
        if (file_put_contents($path, $clean) === false) {
            $this->fail('Unable to store the sanitized SVG.');
        }
    }

    public function sanitize(string $svg): string
    {
        $sanitizer = new Sanitizer();
        $sanitizer->removeRemoteReferences(true);
        $clean = $sanitizer->sanitize($svg);

        if (! is_string($clean) || trim($clean) === '') {
            $this->fail('The SVG file could not be sanitized.');
        }

        if ($this->containsDangerousMarkup($clean)) {
            $this->fail('The SVG file contains disallowed content.');
        }

        return $clean;
    }

    public function containsDangerousMarkup(string $svg): bool
    {
        return (bool) preg_match(
            '/<\s*script\b|javascript\s*:|\bon[a-z]+\s*=|<\s*foreignObject\b/i',
            $svg
        );
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages([
            'file' => [$message],
        ]);
    }
}
