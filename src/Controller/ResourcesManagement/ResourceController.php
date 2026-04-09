<?php

namespace App\Controller\ResourcesManagement;

use App\Entity\ResourcesManagement\Resource;
use App\Form\ResourcesManagement\ResourceType;
use App\Repository\ResourcesManagement\ResourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/resources')]
final class ResourceController extends AbstractController
{
    /**
     * List all resources
     */
    #[Route('/', name: 'app_resource_management_index', methods: ['GET'])]
    public function index(ResourceRepository $resourceRepository): Response
    {
        $resources = $resourceRepository->findAll();

        return $this->render('resources-management/apps-resources-management.html.twig', [
            'resources' => $resources,
        ]);
    }

    /**
     * Create a new resource
     */
    #[Route('/add', name: 'app_resource_management_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $resource = new Resource();
        $form = $this->createForm(ResourceType::class, $resource);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Set default fields
            $resource->setStatus('AVAILABLE');
            $resource->setAvailableQuantity($resource->getTotalQuantity() ?? 0);

            // Handle image upload
            $imageFile = $form->get('image_path')->getData();
            if ($imageFile) {
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads',
                    $newFilename
                );
                $resource->setImagePath('uploads/' . $newFilename);
            }

            if ($form->isValid()) {
                $entityManager->persist($resource);
                $entityManager->flush();

                $this->addFlash('success', 'Resource added successfully!');
                return $this->redirectToRoute('app_resource_management_index');
            } else {
                // Collect PHP validation errors
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
                $this->addFlash('danger', implode('<br>', $errors));
            }
        }

        return $this->render('resources-management/apps-resources-add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Edit an existing resource
     */
    #[Route('/{resource_id}/edit', name: 'app_resources_management_resource_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Resource $resource, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ResourceType::class, $resource);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Handle image upload
            $imageFile = $form->get('image_path')->getData();
            if ($imageFile) {
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads',
                    $newFilename
                );
                $resource->setImagePath('uploads/' . $newFilename);
            }

            if ($form->isValid()) {
                // Update available quantity based on total_quantity
                $resource->setAvailableQuantity($resource->getTotalQuantity() ?? $resource->getAvailableQuantity());

                $entityManager->flush();

                $this->addFlash('success', 'Resource updated successfully!');
                return $this->redirectToRoute('app_resource_management_index');
            } else {
                // Collect PHP validation errors
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
                $this->addFlash('danger', implode('<br>', $errors));
            }
        }

        return $this->render('resources-management/apps-resources-add.html.twig', [
            'form' => $form->createView(),
            'resource' => $resource,
            'is_edit' => true,
        ]);
    }

    /**
     * Delete a resource
     */
    #[Route('/{resource_id}', name: 'app_resources_management_resource_delete', methods: ['POST'])]
    public function delete(Request $request, Resource $resource, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $resource->getResourceId(), $request->request->get('_token'))) {
            $entityManager->remove($resource);
            $entityManager->flush();
            $this->addFlash('success', 'Resource deleted successfully!');
        }

        return $this->redirectToRoute('app_resource_management_index');
    }
}