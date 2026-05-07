<?php

namespace App\Service;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class FirebaseNotificationService
{
    private ?\Kreait\Firebase\Contract\Messaging $messaging = null;
    private LoggerInterface $logger;
    private string $devFcmToken;

    public function __construct(ParameterBagInterface $params, LoggerInterface $logger)
    {
        $this->logger = $logger;
        
        $projectDir = $params->get('kernel.project_dir');
        $projectDir = is_string($projectDir) ? $projectDir : '';
        
        $credentialsPath = $projectDir . '/nexum-aca87-firebase-adminsdk-fbsvc-476323cec1.json';
        
        // Read token from .env. If not set, push will gracefully abort.
        $this->devFcmToken = $_ENV['DEV_FCM_TOKEN'] ?? '';

        if (file_exists($credentialsPath)) {
            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $this->messaging = $factory->createMessaging();
        } else {
            $this->logger->warning('Firebase credentials JSON not found at: ' . $credentialsPath);
        }
    }

    public function sendPushNotification(string $title, string $body): bool
    {
        if (!$this->messaging) {
            $this->logger->error('Firebase Messaging not initialized (missing JSON key). Cannot send push.');
            return false;
        }

        if (empty($this->devFcmToken)) {
            $this->logger->info('DEV_FCM_TOKEN not set in .env. Skipping push notification.');
            return false;
        }

        try {
            $notification = Notification::create($title, $body);
            $message = CloudMessage::withTarget('token', $this->devFcmToken)
                ->withNotification($notification);

            $this->messaging->send($message);
            $this->logger->info('Successfully sent FCM push notification to dev device.');
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send FCM push notification: ' . $e->getMessage());
            return false;
        }
    }
}
