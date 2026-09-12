<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Http\UploadedFile;

trait ValidatesFileUploads
{
    /**
     * Accepts a multipart UploadedFile, an existing http(s) URL, or a base64/data-URI string,
     * matching {@see \App\Helpers\FileUploadHelper::smartSingleFileUpload()}.
     *
     * @param  list<string>  $allowedMimeTypes
     */
    protected function fileUploadRule(array $allowedMimeTypes, int $maxBytes, string $fieldLabel): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($allowedMimeTypes, $maxBytes, $fieldLabel): void {
            if ($value === null || $value === '') {
                return;
            }

            if ($value instanceof UploadedFile) {
                if (! $value->isValid()) {
                    $fail("The {$fieldLabel} upload failed. Please try again.");

                    return;
                }

                if (! in_array($value->getMimeType(), $allowedMimeTypes, true)) {
                    $fail("The {$fieldLabel} must be one of the following types: ".implode(', ', $allowedMimeTypes).'.');

                    return;
                }

                if ($value->getSize() > $maxBytes) {
                    $fail(sprintf('The %s must not be larger than %dMB.', $fieldLabel, intdiv($maxBytes, 1024 * 1024)));
                }

                return;
            }

            if (! is_string($value)) {
                $fail("The {$fieldLabel} must be an uploaded file, a URL, or a base64-encoded file.");
            }
        };
    }
}
