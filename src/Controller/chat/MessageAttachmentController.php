<?php

namespace App\Controller\chat;

use App\Entity\Chat\MessageAttachment;
use App\Repository\Chat\ConversationParticipantRepository;
use App\Repository\Chat\MessageAttachmentRepository;
use App\Repository\Chat\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MessageAttachmentController extends AbstractController
{
	#[Route('/apps-chat/attachments', name: 'apps-chat-attachments', methods: ['POST'])]
	public function upload(
		Request $request,
		MessageRepository $messageRepository,
		ConversationParticipantRepository $participantRepository,
		EntityManagerInterface $entityManager,
	): JsonResponse {
		$messageId = $request->request->get('messageId');
		$message = $messageId ? $messageRepository->find((int)$messageId) : null;

		if (!$message || !$message->getConversationId()) {
			return $this->json([
				'success' => false,
				'error' => 'Message not found or invalid.',
			], 404);
		}

		if (!$participantRepository->isActiveParticipant($message->getConversationId(), ConversationController::SESSION_CURRENT_USER_ID)) {
			return $this->json([
				'success' => false,
				'error' => 'Access denied.',
			], 403);
		}

		$files = $request->files->all();
		if (empty($files)) {
			return $this->json([
				'success' => false,
				'error' => 'No files uploaded.',
			], 400);
		}

		$attachments = [];
		foreach ($files as $file) {
			if (!$file instanceof UploadedFile) {
				continue;
			}

			$mimeType = $file->getMimeType() ?: 'application/octet-stream';
			$fileContent = file_get_contents($file->getRealPath());

			if ($fileContent === false) {
				continue;
			}

			$attachment = new MessageAttachment();
			$attachment->setMessageId($message->getId());
			$attachment->setFileName($file->getClientOriginalName());
			$attachment->setMimeType($mimeType);
			$attachment->setSizeBytes(strlen($fileContent));
			$attachment->setData($fileContent);
			$attachment->setCreatedAt(new \DateTime());

			$entityManager->persist($attachment);
			$entityManager->flush();

			$attachments[] = [
				'id' => $attachment->getId(),
				'fileName' => $attachment->getFileName(),
				'mimeType' => $attachment->getMimeType(),
				'sizeBytes' => $attachment->getSizeBytes(),
				'url' => $this->generateUrl('apps-chat-attachment-show', ['attachmentId' => $attachment->getId()]),
			];
		}

		return $this->json([
			'success' => true,
			'attachments' => $attachments,
		]);
	}

	#[Route('/apps-chat/messages/{messageId}/attachments', name: 'apps-chat-message-attachments', methods: ['GET'])]
	public function listByMessage(
		int $messageId,
		MessageRepository $messageRepository,
		MessageAttachmentRepository $attachmentRepository,
		ConversationParticipantRepository $participantRepository,
	): JsonResponse {
		$message = $messageRepository->find($messageId);
		if ($message === null || $message->getConversationId() === null) {
			return $this->json([
				'success' => false,
				'error' => 'Message not found.',
			], 404);
		}

		if (!$participantRepository->isActiveParticipant($message->getConversationId(), ConversationController::SESSION_CURRENT_USER_ID)) {
			return $this->json([
				'success' => false,
				'error' => 'Access denied for this attachment list.',
			], 403);
		}

		$attachments = $attachmentRepository->findByMessageId($messageId);

		return $this->json([
			'success' => true,
			'messageId' => $messageId,
			'attachments' => array_map(function ($attachment): array {
				return [
					'id' => $attachment->getId(),
					'messageId' => $attachment->getMessageId(),
					'fileName' => $attachment->getFileName(),
					'mimeType' => $attachment->getMimeType(),
					'sizeBytes' => $attachment->getSizeBytes(),
					'url' => $attachment->getId() !== null
						? $this->generateUrl('apps-chat-attachment-show', ['attachmentId' => $attachment->getId()])
						: null,
				];
			}, $attachments),
		]);
	}

	#[Route('/apps-chat/attachments/{attachmentId}', name: 'apps-chat-attachment-show', methods: ['GET'])]
	public function show(
		int $attachmentId,
		Request $request,
		MessageAttachmentRepository $attachmentRepository,
		MessageRepository $messageRepository,
		ConversationParticipantRepository $participantRepository,
	): Response {
		$attachment = $attachmentRepository->find($attachmentId);
		if ($attachment === null || $attachment->getMessageId() === null) {
			throw $this->createNotFoundException('Attachment not found.');
		}

		$message = $messageRepository->find($attachment->getMessageId());
		if ($message === null || $message->getConversationId() === null) {
			throw $this->createNotFoundException('Message not found.');
		}

		if (!$participantRepository->isActiveParticipant($message->getConversationId(), ConversationController::SESSION_CURRENT_USER_ID)) {
			throw $this->createAccessDeniedException('Access denied for this attachment.');
		}

		$mimeType = $attachment->getMimeType() ?: 'application/octet-stream';
		$fileName = $attachment->getFileName() ?: sprintf('attachment-%d', $attachment->getId() ?? 0);
		$isInlineMedia = $this->isInlineMediaMimeType($mimeType);
		$binary = $attachment->getData();
		$contentLength = $attachment->getSizeBytes() ?? $this->guessSize($binary);
		$range = $request->headers->get('Range');

		if ($contentLength <= 0) {
			$contentLength = 0;
		}

		[$statusCode, $start, $end] = $this->resolveRange($range, $contentLength);
		$lengthToSend = max(0, $end - $start + 1);

		$response = new StreamedResponse(function () use ($binary, $start, $lengthToSend): void {
			$this->streamBinary($binary, $start, $lengthToSend);
		}, $statusCode);

		$response->headers->set('Content-Type', $mimeType);
		$response->headers->set('Accept-Ranges', 'bytes');
		$response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
			$isInlineMedia ? 'inline' : 'attachment',
			$fileName
		));
		$response->headers->set('Cache-Control', 'private, max-age=0, must-revalidate');
		$response->headers->set('Content-Length', (string) $lengthToSend);

		if ($statusCode === 206) {
			$response->headers->set('Content-Range', sprintf('bytes %d-%d/%d', $start, $end, $contentLength));
		}

		return $response;
	}

	private function isInlineMediaMimeType(string $mimeType): bool
	{
		return str_starts_with($mimeType, 'image/') || str_starts_with($mimeType, 'video/') || str_starts_with($mimeType, 'audio/');
	}

	/**
	 * @return array{0:int,1:int,2:int}
	 */
	private function resolveRange(?string $rangeHeader, int $contentLength): array
	{
		if ($contentLength <= 0 || $rangeHeader === null || !preg_match('/bytes=(\d*)-(\d*)/', $rangeHeader, $matches)) {
			return [200, 0, max(0, $contentLength - 1)];
		}

		$start = $matches[1] !== '' ? (int) $matches[1] : 0;
		$end = $matches[2] !== '' ? (int) $matches[2] : $contentLength - 1;

		if ($start > $end || $start >= $contentLength) {
			return [200, 0, max(0, $contentLength - 1)];
		}

		$end = min($end, $contentLength - 1);

		return [206, $start, $end];
	}

	private function streamBinary(mixed $binary, int $start, int $length): void
	{
		if ($length <= 0) {
			return;
		}

		if (is_resource($binary)) {
			@rewind($binary);
			if ($start > 0) {
				fseek($binary, $start);
			}

			$remaining = $length;
			while ($remaining > 0 && !feof($binary)) {
				$chunk = fread($binary, min(8192, $remaining));
				if ($chunk === false || $chunk === '') {
					break;
				}

				echo $chunk;
				$remaining -= strlen($chunk);
			}

			return;
		}

		if (!is_string($binary)) {
			return;
		}

		echo substr($binary, $start, $length);
	}

	private function guessSize(mixed $binary): int
	{
		if (is_string($binary)) {
			return strlen($binary);
		}

		if (is_resource($binary)) {
			$stats = fstat($binary);
			return is_array($stats) && isset($stats['size']) ? (int) $stats['size'] : 0;
		}

		return 0;
	}
}
