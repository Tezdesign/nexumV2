<?php

namespace App\Controller\Admin;

use App\Controller\Trait\ValidationFlashTrait;
use App\Service\AIAuditService;
use App\Repository\UserHandling\UtilisateurRepository;
use App\Repository\UserHandling\ReclamationRepository;
use App\Attribute\RequireAdmin;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/audit')]
#[RequireAdmin]
class AuditController extends AbstractController
{
    use ValidationFlashTrait;

    public function __construct(
        private readonly AIAuditService $aiAuditService,
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly ReclamationRepository $reclamationRepository,
    ) {
    }

    #[Route('', name: 'admin_audit_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
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
}
