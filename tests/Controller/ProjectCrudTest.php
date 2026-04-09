<?php

namespace App\Tests\Controller;

use App\Entity\Projects\Project;
use App\Tests\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ProjectCrudTest extends DatabaseWebTestCase
{
    public function testProjectCrudLifecycle(): void
    {
        $manager = $this->seedUser('Alex', 'Manager', 'alex.manager@example.test', 'ROLE_MANAGER');

        $crawler = $this->client->request('GET', '/project/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'New Project');

        $form = $crawler->selectButton('Save')->form();
        $form['project[name]'] = 'Q4 Marketing Campaign';
        $form['project[created_by]'] = (string) $manager->getId();

        $this->client->submit($form);
        self::assertResponseRedirects('/project', Response::HTTP_SEE_OTHER);
        $this->client->followRedirect();

        /** @var Project|null $project */
        $project = $this->entityManager->getRepository(Project::class)->findOneBy([
            'name' => 'Q4 Marketing Campaign',
        ]);
        self::assertNotNull($project);
        self::assertSame($manager->getId(), $project->getCreatedBy());

        $projectId = $project->getId();
        self::assertNotNull($projectId);

        $this->client->request('GET', '/project/' . $projectId);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Q4 Marketing Campaign');

        $crawler = $this->client->request('GET', '/project/' . $projectId . '/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Update')->form();
        $form['project[name]'] = 'Q4 Marketing Campaign Updated';
        $this->client->submit($form);
        self::assertResponseRedirects('/project', Response::HTTP_SEE_OTHER);

        $this->entityManager->refresh($project);
        self::assertSame('Q4 Marketing Campaign Updated', $project->getName());

        $crawler = $this->client->request('GET', '/project/' . $projectId . '/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Delete')->form();
        $this->client->submit($form);
        self::assertResponseRedirects('/project', Response::HTTP_SEE_OTHER);
        $this->entityManager->clear();
        self::assertNull($this->entityManager->getRepository(Project::class)->find($projectId));
    }
}
