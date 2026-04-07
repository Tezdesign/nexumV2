<?php

namespace App\Controller;

use App\Service\Chat\ConversationSidebarProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ConversationController extends AbstractController
{
    private const SESSION_CURRENT_USER_ID = 44;

    #[Route('/apps-chat', name: 'apps-chat')]
    public function index(ConversationSidebarProvider $sidebarProvider): Response
    {
        $sidebarData = $sidebarProvider->getSidebarData(self::SESSION_CURRENT_USER_ID);

        return $this->render('chat/apps-chat.html.twig', [
            'currentUser' => $sidebarData['currentUser'],
            'conversations' => $sidebarData['conversations'],
        ]);
    }
}
