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
use DateTimeImmutable;
use DateTimeInterface;
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
		try {
			foreach ($files as $file) {
				$safeFileName = $this->normalizeAttachmentFileName($file->getClientOriginalName());

				if (!$file->isValid()) {
					return $this->json([
						'success' => false,
						'error' => $this->formatUploadFailureMessage($file, MessageAttachment::MAX_FILE_SIZE_BYTES),
					], 422);
				}

				if ($file->getSize() !== null && $file->getSize() > MessageAttachment::MAX_FILE_SIZE_BYTES) {
					return $this->json([
						'success' => false,
						'error' => 'File is too big. Maximum size is 30 MB.',
					], 422);
				}

				$mimeType = 'application/octet-stream';
				try {
					$detectedMimeType = $file->getMimeType() ?: $mimeType;
					$mimeType = $this->normalizeAttachmentMimeType($detectedMimeType, $safeFileName);
				} catch (\Throwable) {
					// Keep default MIME when finfo cannot inspect the temporary file.
					$mimeType = $this->normalizeAttachmentMimeType($mimeType, $safeFileName);
				}

				$path = $file->getRealPath() ?: $file->getPathname();
				$fileStream = @fopen($path, 'rb');
				if ($fileStream === false) {
					return $this->json([
						'success' => false,
						'error' => 'Could not read uploaded file.',
					], 422);
				}

				$fileSize = (int) ($file->getSize() ?? 0);
				if ($fileSize <= 0) {
					$stats = @fstat($fileStream);
					$fileSize = is_array($stats) && isset($stats['size']) ? (int) $stats['size'] : 0;
				}

				$message = new Message();
				$now = new \DateTime();
				try {
					$message->setConversationId($conversationId)
						->setSenderId($this->currentUserId())
						->setBody($safeFileName)
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
						->setFileName($safeFileName)
						->setMimeType($mimeType)
						->setSizeBytes($fileSize)
						->setData($fileStream)
						->setCreatedAt($now);
				} catch (InvalidArgumentException $exception) {
					@fclose($fileStream);
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
					'timeLabel' => $this->formatMessageTimeLabel($message->getCreatedAt()),
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
		} catch (\Throwable $exception) {
			$errorMessage = trim($exception->getMessage());
			if ($errorMessage === '') {
				$errorMessage = 'Unexpected attachment upload error.';
			}

			return $this->json([
				'success' => false,
				'error' => $errorMessage,
			], 500);
		}

		return $this->json([
			'success' => true,
			'messages' => $messagesPayload,
			'attachments' => $attachments,
		]);
	}

	#[Route('/apps-chat/gifs', name: 'apps-chat-gifs', methods: ['POST'])]
	public function storeGif(
		Request $request,
		UtilisateurRepository $utilisateurRepository,
		ConversationParticipantRepository $participantRepository,
		EntityManagerInterface $entityManager,
	): JsonResponse {
		$conversationId = (int) $request->request->get('conversationId', 0);
		$gifUrl = trim((string) $request->request->get('gifUrl', ''));

		if ($conversationId <= 0) {
			return $this->json([
				'success' => false,
				'error' => 'Invalid conversation id.',
			], 400);
		}

		if ($gifUrl === '') {
			return $this->json([
				'success' => false,
				'error' => 'GIF URL is required.',
			], 422);
		}

		if (!$this->isAllowedGifSourceUrl($gifUrl)) {
			return $this->json([
				'success' => false,
				'error' => 'Unsupported GIF source.',
			], 422);
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

		try {
			[$gifBinary, $mimeType, $fileName, $fileSize] = $this->downloadRemoteGif($gifUrl);
		} catch (
			\Throwable $exception
		) {
			$errorMessage = trim($exception->getMessage());
			if ($errorMessage === '') {
				$errorMessage = 'Could not download GIF.';
			}

			return $this->json([
				'success' => false,
				'error' => $errorMessage,
			], 422);
		}

		$sender = $utilisateurRepository->find($this->currentUserId());
		$now = new \DateTime();

		try {
			$message = new Message();
			$message->setConversationId($conversationId)
				->setSenderId($this->currentUserId())
				->setBody('GIF')
				->setKind('ATTACHMENT')
				->setCreatedAt($now);

			$entityManager->persist($message);
			$entityManager->flush();

			$attachment = new MessageAttachment();
			$attachment->setMessageId((int) $message->getId())
				->setFileName($fileName)
				->setMimeType($mimeType)
				->setSizeBytes($fileSize)
				->setData($gifBinary)
				->setCreatedAt($now);

			$entityManager->persist($attachment);
			$entityManager->flush();

			$conversation->setLastMessageId($message->getId());
			$conversation->setLastMessageAt($message->getCreatedAt());
			$entityManager->flush();
		} catch (\Throwable $exception) {
			$errorMessage = trim($exception->getMessage());
			if ($errorMessage === '') {
				$errorMessage = 'Unexpected GIF upload error.';
			}

			return $this->json([
				'success' => false,
				'error' => $errorMessage,
			], 500);
		}

		return $this->json([
			'success' => true,
			'message' => [
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
				'timeLabel' => $this->formatMessageTimeLabel($message->getCreatedAt()),
			],
			'attachments' => [[
				'id' => $attachment->getId(),
				'messageId' => $attachment->getMessageId(),
				'fileName' => $attachment->getFileName(),
				'mimeType' => $attachment->getMimeType(),
				'sizeBytes' => $attachment->getSizeBytes(),
				'url' => $this->generateUrl('apps-chat-attachment-show', ['attachmentId' => $attachment->getId()]),
			]],
		]);
	}

	private function formatMessageTimeLabel(?DateTimeInterface $createdAt): string
	{
		if ($createdAt === null) {
			return '--';
		}

		$now = new DateTimeImmutable('now', $createdAt->getTimezone());
		$seconds = max(0, $now->getTimestamp() - $createdAt->getTimestamp());

		if ($seconds < 60) {
			return 'now';
		}

		if ($seconds < 3600) {
			$minutes = (int) floor($seconds / 60);
			return $minutes . ' min';
		}

		if ($seconds < 86400) {
			$hours = (int) floor($seconds / 3600);
			return $hours . ' h';
		}

		if ($seconds < 604800) {
			$days = (int) floor($seconds / 86400);
			return $days . ' day' . ($days === 1 ? '' : 's');
		}

		if ($seconds < 1209600) {
			return '1 week ago';
		}

		return $createdAt->format('M j');
	}
	private function currentUserId(): int
	{
		return (int) ($this->authService->getCurrentUserId() ?? 0);
	}

	private function isAllowedGifSourceUrl(string $gifUrl): bool
	{
		$scheme = strtolower((string) parse_url($gifUrl, PHP_URL_SCHEME));
		$host = strtolower((string) parse_url($gifUrl, PHP_URL_HOST));
		if ($scheme === '' || $host === '') {
			return false;
		}

		if (!in_array($scheme, ['http', 'https'], true)) {
			return false;
		}

		if (
			$host === 'localhost'
			|| $host === '127.0.0.1'
			|| $host === '::1'
			|| str_starts_with($host, '10.')
			|| str_starts_with($host, '192.168.')
			|| str_starts_with($host, '169.254.')
		) {
			return false;
		}

		if (preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $host) === 1) {
			return false;
		}

		if (str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
			return false;
		}

		return true;
	}

	/**
	 * @return array{0:string,1:string,2:string,3:int}
	 */
	private function downloadRemoteGif(string $gifUrl): array
	{
		$context = stream_context_create([
			'http' => [
				'method' => 'GET',
				'timeout' => 12,
				'follow_location' => 1,
				'ignore_errors' => true,
				'header' => "User-Agent: NexumChat/1.0\r\nAccept: image/gif,image/*;q=0.9,*/*;q=0.8\r\n",
			],
			'https' => [
				'method' => 'GET',
				'timeout' => 12,
				'follow_location' => 1,
				'ignore_errors' => true,
				'verify_peer' => true,
				'verify_peer_name' => true,
				'header' => "User-Agent: NexumChat/1.0\r\nAccept: image/gif,image/*;q=0.9,*/*;q=0.8\r\n",
			],
		]);

		$binary = @file_get_contents($gifUrl, false, $context);
		if ($binary === false || $binary === '') {
			throw new InvalidArgumentException('Could not download GIF.');
		}

		$mimeType = $this->extractResponseMimeType($http_response_header ?? []) ?: 'image/gif';
		if (!str_starts_with($mimeType, 'image/')) {
			$mimeType = 'image/gif';
		}

		$fileName = $this->buildGifAttachmentFileName($gifUrl);
		$fileSize = strlen($binary);
		if ($fileSize <= 0) {
			throw new InvalidArgumentException('Could not download GIF.');
		}

		return [$binary, $mimeType, $fileName, $fileSize];
	}

	private function buildGifAttachmentFileName(string $gifUrl): string
	{
		$path = (string) parse_url($gifUrl, PHP_URL_PATH);
		$baseName = basename($path);
		if ($baseName !== '' && str_contains($baseName, '.')) {
			return $baseName;
		}

		return sprintf('gif-%s.gif', bin2hex(random_bytes(6)));
	}

	/**
	 * @param array<int,string> $headers
	 */
	private function extractResponseMimeType(array $headers): ?string
	{
		foreach ($headers as $header) {
			if (!is_string($header)) {
				continue;
			}

			if (stripos($header, 'Content-Type:') !== 0) {
				continue;
			}

			$mimeType = trim(substr($header, strlen('Content-Type:')));
			if ($mimeType === '') {
				return null;
			}

			$mimeParts = explode(';', $mimeType, 2);
			return trim($mimeParts[0]);
		}

		return null;
	}

	private function formatUploadFailureMessage(UploadedFile $file, ?int $appLimitBytes = null): string
	{
		$errorCode = $file->getError();
		if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
			$serverLimit = trim((string) ini_get('upload_max_filesize'));
			$message = 'Upload failed: file is larger than the server upload limit';
			if ($serverLimit !== '') {
				$message .= sprintf(' (upload_max_filesize=%s)', $serverLimit);
			}

			if ($appLimitBytes !== null) {
				$appLimitMb = (int) round($appLimitBytes / 1048576);
				$message .= sprintf(' and must not exceed %d MB in this application.', $appLimitMb);
			}

			return $message;
		}

		return 'Upload failed: ' . $file->getErrorMessage();
	}

	private function normalizeAttachmentFileName(?string $originalName): string
	{
		$name = trim((string) $originalName);
		if ($name === '') {
			return 'attachment.bin';
		}

		$extension = pathinfo($name, PATHINFO_EXTENSION);
		$baseName = pathinfo($name, PATHINFO_FILENAME);

		if ($extension !== '') {
			$maxBaseLength = max(1, 255 - mb_strlen($extension) - 1);
			$baseName = mb_substr($baseName, 0, $maxBaseLength);
			return $baseName . '.' . $extension;
		}

		return mb_substr($name, 0, 255);
	}

	private function normalizeAttachmentMimeType(string $mimeType, string $fileName): string
	{
		$normalizedMimeType = strtolower(trim($mimeType));
		if ($normalizedMimeType !== '' && $normalizedMimeType !== 'application/octet-stream') {
			return $normalizedMimeType;
		}

		$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
		return match ($extension) {
			'mp4', 'm4v' => 'video/mp4',
			'webm' => 'video/webm',
			'ogv' => 'video/ogg',
			'mov' => 'video/quicktime',
			'avi' => 'video/x-msvideo',
			'mkv' => 'video/x-matroska',
			'3gp' => 'video/3gpp',
			'3g2' => 'video/3gpp2',
			'ogg', 'oga' => 'audio/ogg',
			'mp3' => 'audio/mpeg',
			'wav' => 'audio/wav',
			'm4a' => 'audio/mp4',
			'aac' => 'audio/aac',
			'flac' => 'audio/flac',
			default => $normalizedMimeType !== '' ? $normalizedMimeType : 'application/octet-stream',
		};
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
