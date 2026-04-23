<?php

namespace App\Controller\ResourcesManagement;

use App\Repository\ResourcesManagement\ResourceAssignmentRepository;
use App\Service\Sms\InfobipSmsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NotificationController extends AbstractController
{
    #[Route('/admin/notify/overdue/{id}', name: 'notify_overdue_sms')]
    public function notifyOverdue(
        int $id,
        ResourceAssignmentRepository $repo,
        InfobipSmsService $smsService
    ): Response {
        $assignment = $repo->find($id);

        if (!$assignment) {
            throw $this->createNotFoundException('Assignment not found');
        }

        $user = $assignment->getUtilisateur();
        if ($user === null) {
            $this->addFlash('danger', 'No user is linked to this assignment.');

            return $this->redirectToRoute('admin_calendar');
        }

        $phone = trim((string) $user->getTelephone());
        if ($phone === '') {
            $this->addFlash('danger', 'User has no phone number.');

            return $this->redirectToRoute('admin_calendar');
        }

        $normalizedPhone = preg_replace('/[\s\-\(\)]/', '', $phone) ?? $phone;
        if (!preg_match('/^\+?[1-9]\d{7,14}$/', $normalizedPhone)) {
            $this->addFlash('danger', sprintf('Invalid phone number format: %s', $phone));

            return $this->redirectToRoute('admin_calendar');
        }

        $resourceId = $assignment->getResourceId();

        try {
            $result = $smsService->sendSms(
                $normalizedPhone,
                "Reminder: Your resource #$resourceId is OVERDUE. Please return it ASAP."
            );

            $statusCode = (int) ($result['statusCode'] ?? 0);
            $rawBody = (string) ($result['body'] ?? '');
            $body = json_decode($rawBody, true);

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->addFlash('success', 'SMS sent successfully to ' . $normalizedPhone);
            } else {
                $errorText = $body['requestError']['serviceException']['text']
                    ?? $body['requestError']['text']
                    ?? $body['error']['message']
                    ?? $rawBody
                    ?? 'Unknown Infobip error.';

                $this->addFlash('danger', 'SMS failed: ' . $errorText);
            }
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'SMS sending failed: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_calendar');
    }
}
