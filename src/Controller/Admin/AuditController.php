<?php

namespace App\Controller\Admin;

use App\Controller\Trait\ValidationFlashTrait;
use App\Service\AuthService;
use App\Service\AIAuditService;
use App\Repository\UserHandling\UtilisateurRepository;
use App\Repository\UserHandling\ReclamationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/audit')]
class AuditController extends AbstractController
{
    use ValidationFlashTrait;

    public function __construct(
        private readonly AuthService $authService,
        private readonly AIAuditService $aiAuditService,
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly ReclamationRepository $reclamationRepository,
    ) {
    }

    private function ensureAdmin(): ?Response
    {
        if (!$this->authService->isLoggedIn()) {
            return $this->redirectToRoute('welcome');
        }
        if (!$this->authService->isAdmin()) {
            $this->addFlash('error', 'You do not have access to the administration area.');

            return $this->redirectToRoute('dashboard');
        }

        return null;
    }

    #[Route('', name: 'admin_audit_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        if ($r = $this->ensureAdmin()) {
            return $r;
        }

        // Real statistics from database
        $allUsers = $this->utilisateurRepository->findAll();
        $totalUsers = count($allUsers);
        
        // Active users: users with a valid status (not suspended)
        $activeUsers = count(array_filter($allUsers, function($user) {
            return strtolower($user->getStatut() ?? '') !== 'suspended';
        }));
        
        // Suspended users
        $suspendedUsers = count(array_filter($allUsers, function($user) {
            return strtolower($user->getStatut() ?? '') === 'suspended';
        }));
        
        // New users this month
        $thisMonth = new \DateTime('first day of this month');
        $newUsersThisMonth = count(array_filter($allUsers, function($user) use ($thisMonth) {
            return $user->getDateInscription() && $user->getDateInscription() >= $thisMonth;
        }));

        $userStats = [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'suspended_users' => $suspendedUsers,
            'new_users_this_month' => $newUsersThisMonth
        ];

        // Reclamation statistics from database
        $allReclamations = $this->reclamationRepository->findAll();
        $totalReclamations = count($allReclamations);
        
        // Pending reclamations
        $pendingReclamations = count(array_filter($allReclamations, function($reclamation) {
            return strtolower($reclamation->getStatut() ?? '') === 'pending';
        }));
        
        // Resolved reclamations
        $resolvedReclamations = count(array_filter($allReclamations, function($reclamation) {
            return strtolower($reclamation->getStatut() ?? '') === 'resolved';
        }));
        
        // Closed reclamations
        $closedReclamations = count(array_filter($allReclamations, function($reclamation) {
            return strtolower($reclamation->getStatut() ?? '') === 'closed';
        }));

        $reclamationStats = [
            'total_reclamations' => $totalReclamations,
            'pending_reclamations' => $pendingReclamations,
            'resolved_reclamations' => $resolvedReclamations,
            'closed_reclamations' => $closedReclamations
        ];

        // Generate AI insights
        $aiInsights = $this->aiAuditService->generateAISummary([
            'users' => $userStats,
            'reclamations' => $reclamationStats
        ]);

        return $this->render('admin/audit/dashboard.html.twig', [
            'userStats' => $userStats,
            'reclamationStats' => $reclamationStats,
            'aiInsights' => $aiInsights
        ]);
    }

    #[Route('/users', name: 'admin_audit_users', methods: ['GET'])]
    public function userAudit(): Response
    {
        if ($r = $this->ensureAdmin()) {
            return $r;
        }

        // Real user data for audit
        $allUsers = $this->utilisateurRepository->findAll();
        $totalUsers = count($allUsers);
        
        $activeUsers = count(array_filter($allUsers, function($user) {
            return strtolower($user->getStatut() ?? '') !== 'suspended';
        }));
        
        $suspendedUsers = count(array_filter($allUsers, function($user) {
            return strtolower($user->getStatut() ?? '') === 'suspended';
        }));
        
        $thisMonth = new \DateTime('first day of this month');
        $newUsersThisMonth = count(array_filter($allUsers, function($user) use ($thisMonth) {
            return $user->getDateInscription() && $user->getDateInscription() >= $thisMonth;
        }));

        $userData = [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'suspended_users' => $suspendedUsers,
            'new_users_this_month' => $newUsersThisMonth
        ];

        // Generate AI insights for user audit
        $aiInsights = $this->aiAuditService->generateUserAuditInsights($userData);

        return $this->render('admin/audit/user_audit.html.twig', [
            'userData' => $userData,
            'aiInsights' => $aiInsights
        ]);
    }

    #[Route('/reclamations', name: 'admin_audit_reclamations', methods: ['GET'])]
    public function reclamationAudit(): Response
    {
        if ($r = $this->ensureAdmin()) {
            return $r;
        }

        // Real reclamation data for audit
        $allReclamations = $this->reclamationRepository->findAll();
        $totalReclamations = count($allReclamations);
        
        $pendingReclamations = count(array_filter($allReclamations, function($reclamation) {
            return strtolower($reclamation->getStatut() ?? '') === 'pending';
        }));
        
        $resolvedReclamations = count(array_filter($allReclamations, function($reclamation) {
            return strtolower($reclamation->getStatut() ?? '') === 'resolved';
        }));
        
        $closedReclamations = count(array_filter($allReclamations, function($reclamation) {
            return strtolower($reclamation->getStatut() ?? '') === 'closed';
        }));

        $reclamationData = [
            'total_reclamations' => $totalReclamations,
            'pending_reclamations' => $pendingReclamations,
            'resolved_reclamations' => $resolvedReclamations,
            'closed_reclamations' => $closedReclamations
        ];

        // Generate AI insights for reclamation audit
        $aiInsights = $this->aiAuditService->generateReclamationAuditInsights($reclamationData);

        return $this->render('admin/audit/reclamation_audit.html.twig', [
            'reclamationData' => $reclamationData,
            'aiInsights' => $aiInsights
        ]);
    }

    #[Route('/anomalies', name: 'admin_audit_anomalies', methods: ['GET'])]
    public function anomalySearch(): Response
    {
        if ($r = $this->ensureAdmin()) {
            return $r;
        }

        // Sample system data for anomaly detection
        $systemData = [
            'user_activity' => 'Normal patterns with slight increase in logins',
            'performance' => 'Response times within acceptable ranges',
            'error_rates' => 'Error rate at 0.8%, slightly above baseline',
            'login_patterns' => 'Multiple login attempts detected from unusual locations'
        ];

        // Generate AI insights for anomaly detection
        $aiInsights = $this->aiAuditService->detectAnomalies($systemData);

        return $this->render('admin/audit/anomaly_search.html.twig', [
            'systemData' => $systemData,
            'aiInsights' => $aiInsights
        ]);
    }
}
