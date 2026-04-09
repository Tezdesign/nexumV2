<?php

namespace App\Tests;

use App\Entity\Projects\Project;
use App\Entity\Projects\ProjectAssignment;
use App\Entity\Tasks\Task;
use App\Entity\UserHandling\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class DatabaseWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = static::createClient();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();

        $this->resetSchema();
    }

    protected function resetSchema(): void
    {
        $classes = [
            $this->entityManager->getClassMetadata(Utilisateur::class),
            $this->entityManager->getClassMetadata(Project::class),
            $this->entityManager->getClassMetadata(ProjectAssignment::class),
            $this->entityManager->getClassMetadata(Task::class),
        ];

        $tool = new SchemaTool($this->entityManager);
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }

    protected function seedUser(
        string $prenom,
        string $nom,
        string $email,
        string $role,
        int $score = 0,
        ?string $statut = 'active'
    ): Utilisateur {
        $user = new Utilisateur();
        $user->setPrenom($prenom);
        $user->setNom($nom);
        $user->setEmail($email);
        $user->setRole($role);
        $user->setDate_inscription(new \DateTime('2026-01-01'));
        $user->setPassword('secret');
        $user->setScore($score);
        $user->setStatut($statut);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    protected function seedProject(
        string $name,
        int $createdBy,
        ?int $assignedTo = null,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): Project {
        $project = new Project();
        $project->setName($name);
        $project->setDescription('Seed project');
        $project->setCreatedBy($createdBy);
        $project->setAssignedTo($assignedTo);
        $project->setStartDate($startDate);
        $project->setEndDate($endDate);

        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return $project;
    }

    protected function seedTask(
        string $title,
        int $projectId,
        int $createdBy,
        ?int $assignedTo = null,
        string $status = 'todo',
        string $priority = 'medium'
    ): Task {
        $task = new Task();
        $task->setTitle($title);
        $task->setDescription('Seed task');
        $task->setProjectId($projectId);
        $task->setCreatedBy($createdBy);
        $task->setAssignedTo($assignedTo);
        $task->setStatus($status);
        $task->setPriority($priority);
        $task->setStartDate(new \DateTime('2026-04-01'));
        $task->setDueDate(new \DateTime('2026-04-10'));
        $task->setEstimatedTime(3);
        $task->setActualTime(1);

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return $task;
    }
}
