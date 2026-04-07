<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'dashboard')]
    public function index(): Response
    {
        return $this->render('index.html.twig');
    }

    #[Route('/apps-chat', name: 'apps-chat')]
    public function chat(): Response
    {
        return $this->render('chat/apps-chat.html.twig');
    }

    #[Route('/apps-projects', name: 'apps-projects')]
    public function projects(): Response
    {
        return $this->render('project-management/apps-projects.html.twig');
    }

    #[Route('/apps-kanban', name: 'apps-kanban')]
    public function kanban(): Response
    {
        return $this->render('project-management/apps-kanban.html.twig');
    }

    #[Route('/apps-task-details', name: 'apps-task-details')]
    public function taskDetails(): Response
    {
        return $this->render('project-management/apps-task-details.html.twig');
    }

    #[Route('/apps-training', name: 'apps-training')]
    public function training(): Response
    {
        return $this->render('training/apps-training.html.twig');
    }

    #[Route('/apps-resources-management', name: 'apps-resources-management')]
    public function resourcesManagement(): Response
    {
        return $this->render('resources-management/apps-resources-management.html.twig');
    }
}
