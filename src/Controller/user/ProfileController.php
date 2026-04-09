<?php

namespace App\Controller\user;

use App\Entity\UserHandling\Utilisateur;
use App\Repository\UserHandling\UtilisateurRepository;
use App\Service\AuthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends AbstractController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/account', name: 'user_account', methods: ['GET', 'POST'])]
    public function account(Request $request): Response
    {
        if (!$this->authService->isLoggedIn()) {
            return $this->redirectToRoute('welcome');
        }

        $userId = $this->authService->getCurrentUserId();
        $utilisateur = $this->utilisateurRepository->find($userId);
        if (!$utilisateur instanceof Utilisateur) {
            $this->authService->logout();
            $this->addFlash('error', 'Your session is no longer valid. Please sign in again.');

            return $this->redirectToRoute('welcome');
        }

        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email', ''));
            $nom = trim((string) $request->request->get('nom', ''));
            $prenom = trim((string) $request->request->get('prenom', ''));
            $telephone = trim((string) $request->request->get('telephone', ''));
            $departement = trim((string) $request->request->get('departement', ''));
            $newPassword = (string) $request->request->get('new_password', '');
            $confirmPassword = (string) $request->request->get('confirm_password', '');

            $newProfileImageBinary = null;
            $profileFile = $request->files->get('imagelink');
            if ($profileFile instanceof UploadedFile) {
                if ($profileFile->getError() === UPLOAD_ERR_OK) {
                    $path = $profileFile->getRealPath() ?: $profileFile->getPathname();
                    $newProfileImageBinary = @file_get_contents($path);
                    if ($newProfileImageBinary === false || $newProfileImageBinary === '') {
                        $newProfileImageBinary = null;
                        $this->addFlash('warning', 'The profile photo could not be read. Please try another image.');
                    }
                } elseif ($profileFile->getError() !== UPLOAD_ERR_NO_FILE) {
                    $this->addFlash('warning', 'Photo upload failed: '.$profileFile->getErrorMessage());
                }
            }

            if ($nom === '' || $prenom === '' || $email === '') {
                $this->addFlash('error', 'First name, last name, and email are required.');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Please enter a valid email address.');
            } elseif ($this->utilisateurRepository->existsOtherUserWithEmail($email, $utilisateur->getId())) {
                $this->addFlash('error', 'This email address is already used by another account.');
            } elseif ($newPassword !== '' && $newPassword !== $confirmPassword) {
                $this->addFlash('error', 'The new password and confirmation do not match.');
            } elseif ($newPassword !== '' && strlen($newPassword) < 6) {
                $this->addFlash('error', 'The new password must be at least 6 characters.');
            } else {
                $utilisateur->setEmail($email);
                $utilisateur->setNom($nom);
                $utilisateur->setPrenom($prenom);
                $utilisateur->setTelephone($telephone !== '' ? $telephone : null);
                $utilisateur->setDepartement($departement !== '' ? $departement : null);

                if ($newPassword !== '') {
                    $utilisateur->setPassword($newPassword);
                }

                $this->entityManager->flush();

                // imagelink is a LONGBLOB: Doctrine often skips BLOB in change sets for managed entities.
                // Persist the binary with an explicit UPDATE so the row always matches MySQL `imagelink` longblob.
                $photoUpdated = false;
                if ($newProfileImageBinary !== null) {
                    // Untyped binding: most reliable for LONGBLOB across PHP/MySQL drivers.
                    $this->entityManager->getConnection()->executeStatement(
                        'UPDATE utilisateurs SET imagelink = ? WHERE id = ?',
                        [$newProfileImageBinary, $utilisateur->getId()]
                    );
                    $utilisateur->setImagelink($newProfileImageBinary);
                    $photoUpdated = true;
                }

                $this->authService->refreshSessionUser($utilisateur, $photoUpdated);
                $this->addFlash('success', 'Your account has been updated successfully.');

                return $this->redirectToRoute('user_account');
            }
        }

        return $this->render('user/account.html.twig', [
            'utilisateur' => $utilisateur,
        ]);
    }

    #[Route('/account/avatar', name: 'user_account_avatar', methods: ['GET'])]
    public function avatar(): Response
    {
        if (!$this->authService->isLoggedIn()) {
            return $this->redirect('/images/users/avatar-1.jpg');
        }

        $utilisateur = $this->utilisateurRepository->find($this->authService->getCurrentUserId());
        if (!$utilisateur instanceof Utilisateur) {
            return $this->redirect('/images/users/avatar-1.jpg');
        }

        $blob = $utilisateur->getImagelink();
        if ($blob === null) {
            return $this->redirect('/images/users/avatar-1.jpg');
        }

        $binary = \is_resource($blob) ? stream_get_contents($blob) : $blob;
        if ($binary === false || $binary === '') {
            return $this->redirect('/images/users/avatar-1.jpg');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($binary) ?: 'image/jpeg';

        return new Response($binary, Response::HTTP_OK, [
            'Content-Type' => $mime,
            // Same URL for every user — browsers cache aggressively; pair with ?v=avatar_v in Twig.
            'Cache-Control' => 'private, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
