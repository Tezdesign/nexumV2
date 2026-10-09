<?php

namespace App\Service\ResourcesManagement;

use App\Entity\ResourcesManagement\Resource;
use App\Entity\ResourcesManagement\ResourceAssignment;
use App\Repository\ResourcesManagement\ResourceAssignmentRepository;
use App\Repository\ResourcesManagement\ResourceRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Single owner of `Resource::available_quantity`.
 *
 * The stock is never adjusted with +/- any more: it is derived as
 * "total quantity minus what accepted, not yet returned assignments hold". Calling
 * `recalculate()` after any change keeps it right whatever the previous value was,
 * so accepting twice, declining a pending request, returning an item or editing a
 * resource cannot make it drift.
 */
final class ResourceStockService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ResourceAssignmentRepository $assignments,
        private readonly ResourceRepository $resources,
    ) {
    }

    /** True while the assignment takes units out of the stock. */
    public function holdsStock(ResourceAssignment $assignment): bool
    {
        return $assignment->getStatus() === 'ACCEPTED' && !$assignment->isReturned();
    }

    /** Units of the resource currently lent out. */
    public function heldQuantity(Resource $resource): int
    {
        return $this->assignments->sumHeldQuantity((int) $resource->getResourceId());
    }

    /**
     * Rewrites the available quantity from the assignments. Flush the assignment change first:
     * the sum is read from the database.
     */
    public function recalculate(Resource $resource): void
    {
        $resource->setAvailableQuantity(max(0, (int) $resource->getTotalQuantity() - $this->heldQuantity($resource)));
    }

    /** Locks the resource row and reloads it, so concurrent requests see each other's stock changes. */
    public function lock(Resource $resource): void
    {
        $this->em->refresh($resource, LockMode::PESSIMISTIC_WRITE);
    }

    /** @return int number of resources whose stored value was wrong and has been corrected */
    public function recalculateAll(): int
    {
        $fixed = 0;
        foreach ($this->resources->findAll() as $resource) {
            $before = $resource->getAvailableQuantity();
            $this->recalculate($resource);
            if ($before !== $resource->getAvailableQuantity()) {
                ++$fixed;
            }
        }
        $this->em->flush();

        return $fixed;
    }
}
