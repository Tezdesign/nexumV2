<?php

namespace App\Controller\Trait;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Profile photos are stored as blobs and shown on every page, so only real small pictures get in. */
trait ProfilePhotoTrait
{
    private const PHOTO_MAX_BYTES = 2 * 1024 * 1024;

    /** Types detected from the content (never from the file name); SVG is left out on purpose. */
    private const PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /** The checked image bytes, or null (with a flash message) when there is no usable upload. */
    private function uploadedPhoto(mixed $file): ?string
    {
        if (!$file instanceof UploadedFile || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file->getError() !== UPLOAD_ERR_OK || ($file->getSize() ?: 0) > self::PHOTO_MAX_BYTES) {
            $this->addFlash('warning', 'The profile photo was not saved: 2 MB maximum.');

            return null;
        }

        $binary = @file_get_contents($file->getRealPath() ?: $file->getPathname());
        if ($binary === false || $binary === '' || !in_array((new \finfo(FILEINFO_MIME_TYPE))->buffer($binary), self::PHOTO_TYPES, true)) {
            $this->addFlash('warning', 'The profile photo was not saved: use a JPEG, PNG, GIF or WebP image.');

            return null;
        }

        return $binary;
    }
}
