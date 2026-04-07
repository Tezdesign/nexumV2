<?php

namespace App\Controller\chat;

use App\Entity\UserHandling\Utilisateur;
use App\Repository\Chat\ConversationParticipantRepository;
use App\Repository\Chat\MessageRepository;
use App\Repository\UserHandling\UtilisateurRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class MessageController extends AbstractController
{
	private const SESSION_CURRENT_USER_ID = 44;

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

		if (!$participantRepository->isActiveParticipant($conversationId, self::SESSION_CURRENT_USER_ID)) {
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

			return [
				'id' => $message->getId(),
				'body' => $message->getBody() ?? '',
				'senderId' => $senderId,
				'senderName' => $this->buildUserName($sender),
				'senderAvatarSrc' => $sender !== null ? $this->toDataUri($sender->getImagelink(), 'image/jpeg') : null,
				'isOwn' => $senderId === self::SESSION_CURRENT_USER_ID,
				'createdAt' => $message->getCreatedAt()?->format(DATE_ATOM),
				'timeLabel' => $message->getCreatedAt()?->format('g:ia') ?? '--',
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
}
