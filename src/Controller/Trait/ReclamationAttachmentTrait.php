<?php

namespace App\Controller\Trait;

use App\Entity\UserHandling\Reclamation;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** Reclamation files are blobs uploaded by users and opened by admins, so they are checked in and locked down on the way out. */
trait ReclamationAttachmentTrait
{
    private const ATTACHMENT_MAX_BYTES = 5 * 1024 * 1024;

    /** Types accepted on upload (detected from the content, not from the file name). */
    private const ATTACHMENT_ALLOWED = [
        'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'application/pdf', 'text/plain',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    /** Types the browser may display; everything else is downloaded. */
    private const ATTACHMENT_INLINE = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'application/pdf'];

    /** The checked file content, or null (with a flash message) when there is no usable upload. */
    private function uploadedAttachment(Request $request): ?string
    {
        $file = $request->files->get('fichier');
        if (!$file instanceof UploadedFile || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file->getError() !== UPLOAD_ERR_OK || ($file->getSize() ?: 0) > self::ATTACHMENT_MAX_BYTES) {
            $this->addFlash('warning', 'Attachment rejected: 5 MB maximum.');

            return null;
        }

        $binary = @file_get_contents($file->getRealPath() ?: $file->getPathname());
        if ($binary === false || $binary === '') {
            $this->addFlash('warning', 'Attachment could not be read.');

            return null;
        }
        if (!in_array((new \finfo(FILEINFO_MIME_TYPE))->buffer($binary), self::ATTACHMENT_ALLOWED, true)) {
            $this->addFlash('warning', 'Attachment rejected: use an image, PDF, text or Office file.');

            return null;
        }

        return $binary;
    }

    /** Saves the checked file on a reclamation that already has an id (the blob is written with an explicit UPDATE: Doctrine skips LONGBLOB changes). */
    private function storeAttachment(Reclamation $rec, string $binary): void
    {
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE reclamation SET fichier = ? WHERE idRec = ?',
            [$binary, $rec->getIdRec()]
        );
        $rec->setFichier($binary);
    }

    private function attachmentResponse(Reclamation $rec): Response
    {
        $data = $rec->getFichier();
        if (!\is_string($data) || $data === '') {
            throw $this->createNotFoundException('No attachment.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($data) ?: 'application/octet-stream';
        $inline = in_array($mime, self::ATTACHMENT_INLINE, true);

        return new Response($data, Response::HTTP_OK, [
            'Content-Type' => $inline ? $mime : 'application/octet-stream',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment') . '; filename="reclamation-' . (int) $rec->getIdRec() . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
