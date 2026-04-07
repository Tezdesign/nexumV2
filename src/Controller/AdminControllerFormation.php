<?php

namespace App\Controller;

use App\Entity\Formation;
use App\Entity\Participer;
use App\Repository\FormationRepository;
use App\Repository\ParticiperRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class AdminControllerFormation extends AbstractController
{
    #[Route('', name: 'admin_dashboard_formation')]
    public function index(
        Request $request,
        FormationRepository $formationRepository,
        EntityManagerInterface $em,
        PaginatorInterface $paginator
    ): Response {

       

        // 🔥 STATS
        $totalFormations = $em->getRepository(Formation::class)->count([]);
        $totalParticipations = $em->getRepository(Participer::class)->count([]);
        $totalReussi = $em->getRepository(Participer::class)->count(['statut' => 'REUSSI']);
        $totalQuiz = $em->getRepository(Participer::class)->count(['statut' => 'PRET_QUIZ']);

        $successRate = $totalParticipations > 0
            ? round(($totalReussi / $totalParticipations) * 100)
            : 0;

       

        return $this->render('formation/dashboard.html.twig', [
             'stats' => [
                'formations' => $totalFormations,
                'participations' => $totalParticipations,
                'reussi' => $totalReussi,
                'quiz' => $totalQuiz,
                'successRate' => $successRate
            ]
        ]);
    }

    #[Route('/chart', name: 'admin_chart')]
    public function chart(EntityManagerInterface $em): JsonResponse
    {
        $totalParticipations = $em->getRepository(Participer::class)->count([]);
        $totalReussi = $em->getRepository(Participer::class)->count(['statut' => 'REUSSI']);
        $totalQuiz = $em->getRepository(Participer::class)->count(['statut' => 'PRET_QUIZ']);
        $enCours = max(0, $totalParticipations - $totalReussi - $totalQuiz);

        return new JsonResponse([
            'labels' => ['En cours', 'Quiz', 'Réussi'],
            'data' => [$enCours, $totalQuiz, $totalReussi]
        ]);
    }

    #[Route('/chart/monthly', name: 'admin_chart_monthly')]
public function monthlyChart(ParticiperRepository $repo): JsonResponse
{
    $data = $repo->getMonthlyInscriptions();

    $labels = array_column($data, 'month');
    $values = array_map(fn($d) => (int)$d['total'], $data);

    return new JsonResponse([
        'labels' => $labels,
        'data' => $values
    ]);
}

#[Route('/chart/top-formations', name: 'admin_chart_top')]
public function topFormations(FormationRepository $repo): JsonResponse
{
    $data = $repo->getTopFormationsStats();

    $labels = array_column($data, 'titre');
    $participants = array_map(fn($d) => (int)$d['participants'], $data);

    return new JsonResponse([
        'labels' => $labels,
        'data' => $participants
    ]);
}
}