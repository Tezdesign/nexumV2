<?php

namespace App\Controller\chat;

use App\Entity\Chat\Conversation;
use App\Entity\Chat\Message;
use App\Entity\Chat\MessageAttachment;
use App\Repository\Chat\ConversationParticipantRepository;
use App\Repository\Chat\MessageAttachmentRepository;
use App\Repository\Chat\MessageRepository;
use App\Repository\UserHandling\UtilisateurRepository;
use App\Service\AuthService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
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
	public function __construct(
		private readonly AuthService $authService,
	) {
	}

	#[Route('/apps-chat/attachments', name: 'apps-chat-attachments', methods: ['POST'])]
	public function upload(
		Request $request,
		UtilisateurRepository $utilisateurRepository,
		ConversationParticipantRepository $participantRepository,
		EntityManagerInterface $entityManager,
	): JsonResponse {
		$conversationId = (int) $request->request->get('conversationId', 0);
		if ($conversationId <= 0) {
			return $this->json([
				'success' => false,
				'error' => 'Invalid conversation id.',
			], 400);
		}

		if (!$participantRepository->isActiveParticipant($conversationId, $this->currentUserId())) {
			return $this->json([
				'success' => false,
				'error' => 'Access denied.',
			], 403);
		}

		$conversation = $entityManager->getRepository(Conversation::class)->find($conversationId);
		if (!$conversation instanceof Conversation) {
			return $this->json([
				'success' => false,
				'error' => 'Conversation not found.',
			], 404);
		}

		$files = $this->normalizeUploadedFiles($request);
		if (empty($files)) {
			return $this->json([
				'success' => false,
				'error' => 'No files uploaded.',
			], 400);
		}

		$sender = $utilisateurRepository->find($this->currentUserId());
		$messagesPayload = [];
		$attachments = [];
		foreach ($files as $file) {
			$mimeType = $file->getMimeType() ?: 'application/octet-stream';
			$fileContent = file_get_contents($file->getRealPath());

			if ($fileContent === false) {
				return $this->json([
					'success' => false,
					'error' => 'Could not read uploaded file.',
				], 422);
			}

			$message = new Message();
			$now = new \DateTime();
			try {
				$message->setConversationId($conversationId)
					->setSenderId($this->currentUserId())
					->setBody($file->getClientOriginalName())
					->setKind('ATTACHMENT')
					->setCreatedAt($now);
			} catch (InvalidArgumentException $exception) {
				return $this->json([
					'success' => false,
					'error' => $exception->getMessage(),
				], 422);
			}

			$entityManager->persist($message);
			$entityManager->flush();

			$attachment = new MessageAttachment();
			try {
				$attachment->setMessageId((int) $message->getId())
					->setFileName($file->getClientOriginalName())
					->setMimeType($mimeType)
					->setSizeBytes(strlen($fileContent))
					->setData($fileContent)
					->setCreatedAt($now);
			} catch (InvalidArgumentException $exception) {
				return $this->json([
					'success' => false,
					'error' => $exception->getMessage(),
				], 422);
			}

			$entityManager->persist($attachment);
			$entityManager->flush();

			$conversation->setLastMessageId($message->getId());
			$conversation->setLastMessageAt($message->getCreatedAt());
			$entityManager->flush();

			$messagesPayload[] = [
				'id' => $message->getId(),
				'body' => $message->getBody() ?? '',
				'kind' => 'ATTACHMENT',
				'senderId' => $this->currentUserId(),
				'senderName' => $sender !== null
					? trim(sprintf('%s %s', (string) $sender->getPrenom(), (string) $sender->getNom()))
					: 'Unknown User',
				'senderAvatarSrc' => $sender !== null ? $this->toDataUri($sender->getImagelink(), 'image/jpeg') : null,
				'isOwn' => true,
				'createdAt' => $message->getCreatedAt()?->format(DATE_ATOM),
				'timeLabel' => $message->getCreatedAt()?->format('g:ia') ?? '--',
			];

			$attachments[] = [
				'id' => $attachment->getId(),
				'messageId' => $message->getId(),
				'fileName' => $attachment->getFileName(),
				'mimeType' => $attachment->getMimeType(),
				'sizeBytes' => $attachment->getSizeBytes(),
				'url' => $this->generateUrl('apps-chat-attachment-show', ['attachmentId' => $attachment->getId()]),
			];
		}

		return $this->json([
			'success' => true,
			'messages' => $messagesPayload,
			'attachments' => $attachments,
		]);
	}

	private function currentUserId(): int
	{
		return (int) ($this->authService->getCurrentUserId() ?? 0);
	}

	/**
	 * @return UploadedFile[]
	 */
	private function normalizeUploadedFiles(Request $request): array
	{
		$all = $request->files->all();
		$files = [];

		foreach ($all as $value) {
			if ($value instanceof UploadedFile) {
				$files[] = $value;
				continue;
			}

			if (is_array($value)) {
				foreach ($value as $nestedValue) {
					if ($nestedValue instanceof UploadedFile) {
						$files[] = $nestedValue;
					}
				}
			}
		}

		return $files;
	}

	private function toDataUri(mixed $blobValue, string $mime): ?string
	{
		if ($blobValue === null || $blobValue === '') {
			return null;
		}

		return sprintf('data:%s;base64,%s', $mime, base64_encode((string) $blobValue));
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

		if (!$participantRepository->isActiveParticipant($message->getConversationId(), $this->currentUserId())) {
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

		if (!$participantRepository->isActiveParticipant($message->getConversationId(), $this->currentUserId())) {
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
