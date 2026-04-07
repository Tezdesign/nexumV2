<?php

namespace App\Controller\chat;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class MessageAttachmentController extends AbstractController
{
	#[Route('/apps-chat/attachments', name: 'apps-chat-attachments', methods: ['POST'])]
	public function upload(Request $request): JsonResponse
	{
		return $this->json([
			'success' => true,
			'filename' => $request->files->all() ? array_key_first($request->files->all()) : null,
		]);
	}
}
