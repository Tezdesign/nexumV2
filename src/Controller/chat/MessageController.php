<?php

namespace App\Controller\chat;

use App\Entity\UserHandling\Utilisateur;
use App\Repository\Chat\ConversationParticipantRepository;
use App\Repository\Chat\MessageRepository;
use App\Repository\UserHandling\UtilisateurRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Annotation\Route;

class MessageController extends AbstractController
{
	private const LINK_PREVIEW_USER_AGENT = 'Mozilla/5.0 (compatible; NexumChatLinkPreview/1.0; +https://nexum.local)';

	public function __construct(
		private readonly HttpClientInterface $httpClient,
	) {
	}

	#[Route('/apps-chat/conversations/{conversationId}/messages', name: 'apps-chat-conversation-messages', methods: ['GET'])]
	public function index(
		int $conversationId,
		MessageRepository $messageRepository,
		ConversationParticipantRepository $participantRepository,
		UtilisateurRepository $utilisateurRepository,
	): JsonResponse {
		if ($conversationId <= 0) {
			return $this->json([
				'success' => false,
				'error' => 'Invalid conversation id.',
			], 400);
		}

		if (!$participantRepository->isActiveParticipant($conversationId, ConversationController::SESSION_CURRENT_USER_ID)) {
			return $this->json([
				'success' => false,
				'error' => 'Access denied for this conversation.',
			], 403);
		}

		$messages = $messageRepository->findByConversationOrdered($conversationId);
		$usersById = $this->mapUsersById($messages, $utilisateurRepository);

		$payload = array_map(function ($message) use ($usersById): array {
			$senderId = $message->getSenderId();
			$sender = $senderId !== null ? ($usersById[$senderId] ?? null) : null;
			$createdAt = $message->getCreatedAt();
			$messageId = $message->getId();

			return [
				'id' => $messageId,
				'body' => $message->getBody() ?? '',
				'kind' => strtoupper((string) $message->getKind()),
				'senderId' => $senderId,
				'senderName' => $this->buildUserName($sender),
				'senderAvatarSrc' => $sender !== null ? $this->toDataUri($sender->getImagelink(), 'image/jpeg') : null,
				'isOwn' => $senderId === ConversationController::SESSION_CURRENT_USER_ID,
				'createdAt' => $createdAt?->format(DATE_ATOM),
				'timeLabel' => $this->formatMessageTimeLabel($createdAt),
			];
		}, $messages);

		return $this->json([
			'success' => true,
			'messages' => $payload,
		]);
	}

	#[Route('/apps-chat/messages', name: 'apps-chat-messages', methods: ['POST'])]
	public function store(Request $request): JsonResponse
	{
		return $this->json([
			'success' => true,
			'message' => $request->request->get('message', ''),
		]);
	}

	#[Route('/apps-chat/link-preview', name: 'apps-chat-link-preview', methods: ['GET'])]
	public function linkPreview(Request $request): JsonResponse
	{
		$url = trim((string) $request->query->get('url', ''));
		if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
			return $this->json([
				'success' => false,
				'error' => 'Invalid URL.',
			], 400);
		}

		$parts = parse_url($url);
		$scheme = strtolower((string) ($parts['scheme'] ?? ''));
		$host = strtolower((string) ($parts['host'] ?? ''));

		if (!in_array($scheme, ['http', 'https'], true) || $host === '' || $this->isBlockedPreviewHost($host)) {
			return $this->json([
				'success' => false,
				'error' => 'Unsupported URL host.',
				'preview' => [
					'url' => $url,
					'displayUrl' => $this->shortenUrlLabel($url),
				],
			], 400);
		}

		$preview = [
			'url' => $url,
			'displayUrl' => $this->shortenUrlLabel($url),
			'host' => $host,
			'siteName' => $host,
			'title' => null,
			'description' => null,
			'image' => null,
		];

		try {
			$response = $this->httpClient->request('GET', $url, [
				'timeout' => 6.0,
				'max_redirects' => 5,
				'headers' => [
					'User-Agent' => self::LINK_PREVIEW_USER_AGENT,
					'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
				],
			]);

			$finalUrl = $response->getInfo('url') ?: $url;
			$contentType = strtolower((string) $response->getHeaders(false)['content-type'][0] ?? '');

			$preview['url'] = is_string($finalUrl) ? $finalUrl : $url;
			$preview['displayUrl'] = $this->shortenUrlLabel($preview['url']);
			$finalHost = strtolower((string) (parse_url($preview['url'], PHP_URL_HOST) ?? $host));
			$preview['host'] = $finalHost;
			$preview['siteName'] = $finalHost !== '' ? $finalHost : $host;

			if (!str_contains($contentType, 'text/html')) {
				return $this->json([
					'success' => false,
					'preview' => $preview,
				]);
			}

			$html = $response->getContent(false);
			if (!is_string($html) || trim($html) === '') {
				return $this->json([
					'success' => false,
					'preview' => $preview,
				]);
			}

			$meta = $this->extractLinkMetadata($html, $preview['url']);
			$preview['title'] = $meta['title'] ?? null;
			$preview['description'] = $meta['description'] ?? null;
			$preview['siteName'] = $meta['siteName'] ?? $preview['siteName'];
			$preview['image'] = $meta['image'] ?? null;

			return $this->json([
				'success' => ($preview['title'] ?? null) !== null || ($preview['description'] ?? null) !== null,
				'preview' => $preview,
			]);
		} catch (\Throwable) {
			return $this->json([
				'success' => false,
				'preview' => $preview,
			]);
		}
	}

	/**
	 * @param array<int, mixed> $messages
	 *
	 * @return array<int, Utilisateur>
	 */
	private function mapUsersById(array $messages, UtilisateurRepository $utilisateurRepository): array
	{
		$senderIds = [];
		foreach ($messages as $message) {
			$senderId = $message->getSenderId();
			if ($senderId !== null) {
				$senderIds[$senderId] = $senderId;
			}
		}

		if ($senderIds === []) {
			return [];
		}

		$users = $utilisateurRepository->findBy(['id' => array_values($senderIds)]);
		$mapped = [];
		foreach ($users as $user) {
			$id = $user->getId();
			if ($id !== null) {
				$mapped[$id] = $user;
			}
		}

		return $mapped;
	}

	private function buildUserName(?Utilisateur $user): string
	{
		if ($user === null) {
			return 'Unknown User';
		}

		$name = trim(sprintf('%s %s', (string) $user->getPrenom(), (string) $user->getNom()));
		return $name !== '' ? $name : 'Unknown User';
	}

	private function toDataUri(mixed $blobValue, string $mime): ?string
	{
		if ($blobValue === null || $blobValue === '') {
			return null;
		}

		return sprintf('data:%s;base64,%s', $mime, base64_encode($blobValue));
	}

	private function formatMessageTimeLabel(?DateTimeInterface $createdAt): string
	{
		if ($createdAt === null) {
			return '--';
		}

		$now = new DateTimeImmutable('now', $createdAt->getTimezone());
		$secondsDiff = $now->getTimestamp() - $createdAt->getTimestamp();

		if ($secondsDiff < 24 * 60 * 60) {
			return $createdAt->format('g:ia');
		}

		return $createdAt->format('m/d g:ia');
	}

	private function isBlockedPreviewHost(string $host): bool
	{
		if ($host === 'localhost' || str_ends_with($host, '.local')) {
			return true;
		}

		$ip = filter_var($host, FILTER_VALIDATE_IP);
		if ($ip !== false) {
			$flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
			return filter_var($ip, FILTER_VALIDATE_IP, $flags) === false;
		}

		return false;
	}

	private function shortenUrlLabel(string $url): string
	{
		$host = (string) (parse_url($url, PHP_URL_HOST) ?? '');
		$path = (string) (parse_url($url, PHP_URL_PATH) ?? '');

		if ($host === '') {
			return strlen($url) > 56 ? substr($url, 0, 53) . '...' : $url;
		}

		$cleanPath = trim($path, '/');
		if ($cleanPath === '') {
			return $host;
		}

		$shortPath = strlen($cleanPath) > 28 ? substr($cleanPath, 0, 25) . '...' : $cleanPath;
		return $host . '/' . $shortPath;
	}

	/**
	 * @return array{title:?string,description:?string,siteName:?string,image:?string}
	 */
	private function extractLinkMetadata(string $html, string $baseUrl): array
	{
		$metadata = [
			'title' => null,
			'description' => null,
			'siteName' => null,
			'image' => null,
		];

		libxml_use_internal_errors(true);
		$document = new \DOMDocument();
		$loaded = @$document->loadHTML($html);
		libxml_clear_errors();

		if ($loaded !== true) {
			return $metadata;
		}

		$xpath = new \DOMXPath($document);
		$ogTitle = $this->xpathMetaContent($xpath, 'property', 'og:title');
		$title = $ogTitle ?? trim((string) $xpath->evaluate('string(//title)'));

		$metadata['title'] = $title !== '' ? $title : null;
		$metadata['description'] = $this->xpathMetaContent($xpath, 'property', 'og:description')
			?? $this->xpathMetaContent($xpath, 'name', 'description');
		$metadata['siteName'] = $this->xpathMetaContent($xpath, 'property', 'og:site_name');

		$image = $this->xpathMetaContent($xpath, 'property', 'og:image');
		if ($image !== null && $image !== '') {
			$metadata['image'] = $this->resolveUrl($baseUrl, $image);
		}

		return $metadata;
	}

	private function xpathMetaContent(\DOMXPath $xpath, string $attrName, string $attrValue): ?string
	{
		$query = sprintf('//meta[translate(@%s, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="%s"]/@content', $attrName, strtolower($attrValue));
		$value = trim((string) $xpath->evaluate(sprintf('string(%s)', $query)));
		return $value !== '' ? $value : null;
	}

	private function resolveUrl(string $baseUrl, string $url): string
	{
		if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
			return $url;
		}

		$baseParts = parse_url($baseUrl);
		if (!is_array($baseParts) || !isset($baseParts['scheme'], $baseParts['host'])) {
			return $url;
		}

		$prefix = $baseParts['scheme'] . '://' . $baseParts['host'];
		if (isset($baseParts['port'])) {
			$prefix .= ':' . (int) $baseParts['port'];
		}

		if (str_starts_with($url, '//')) {
			return $baseParts['scheme'] . ':' . $url;
		}

		if (str_starts_with($url, '/')) {
			return $prefix . $url;
		}

		$basePath = (string) ($baseParts['path'] ?? '/');
		$directory = rtrim(str_replace('\\', '/', dirname($basePath)), '/');
		$directory = $directory === '' || $directory === '.' ? '' : $directory;

		return $prefix . '/' . ltrim($directory . '/' . ltrim($url, '/'), '/');
	}
}
