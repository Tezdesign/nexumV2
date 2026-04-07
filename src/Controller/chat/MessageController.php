<?php

namespace App\Controller\chat;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class MessageController extends AbstractController
{
	#[Route('/apps-chat/messages', name: 'apps-chat-messages', methods: ['POST'])]
	public function store(Request $request): JsonResponse
	{
		return $this->json([
			'success' => true,
			'message' => $request->request->get('message', ''),
		]);
	}
}
