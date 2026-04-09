<?php

namespace App\Tests\Controller;

use App\Entity\Tasks\Task;
use App\Tests\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class TaskCrudTest extends DatabaseWebTestCase
{
    public function testTaskCrudLifecycle(): void
    {
        $manager = $this->seedUser('Alex', 'Manager', 'alex.manager@example.test', 'ROLE_MANAGER');
        $project = $this->seedProject(
            'Launch Project',
            (int) $manager->getId(),
            (int) $manager->getId()
        );

        $crawler = $this->client->request('GET', '/task/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Create new Task');

        $form = $crawler->selectButton('Save')->form();
        $form['task[title]'] = 'Build backend API';
        $form['task[project_id]'] = (string) $project->getId();
        $form['task[status]'] = 'todo';
        $form['task[priority]'] = 'high';
        $form['task[created_by]'] = (string) $manager->getId();
        $form['task[assigned_to]'] = (string) $manager->getId();

        $this->client->submit($form);
        self::assertResponseRedirects('/task', Response::HTTP_SEE_OTHER);
        $this->client->followRedirect();

        /** @var Task|null $task */
        $task = $this->entityManager->getRepository(Task::class)->findOneBy([
            'title' => 'Build backend API',
        ]);
        self::assertNotNull($task);
        self::assertSame((int) $project->getId(), (int) $task->getProjectId());
        self::assertSame((int) $manager->getId(), (int) $task->getCreatedBy());

        $taskId = $task->getId();
        self::assertNotNull($taskId);

        $this->client->request('GET', '/task/' . $taskId);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Build backend API');

        $crawler = $this->client->request('GET', '/task/' . $taskId . '/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save')->form();
        $form['task_manager_update[title]'] = 'Build backend API v2';
        $this->client->submit($form);
        self::assertResponseRedirects('/task', Response::HTTP_SEE_OTHER);

        $this->entityManager->refresh($task);
        self::assertSame('Build backend API v2', $task->getTitle());

        $crawler = $this->client->request('GET', '/task/' . $taskId);
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Delete Task')->form();
        $this->client->submit($form);
        self::assertResponseRedirects('/task', Response::HTTP_SEE_OTHER);
        $this->entityManager->clear();
        self::assertNull($this->entityManager->getRepository(Task::class)->find($taskId));
    }
}
