<?php
namespace App\Repository\Chat;

use App\Entity\Chat\ConversationParticipant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
/**
 * @extends ServiceEntityRepository<ConversationParticipant>
 */
class ConversationParticipantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConversationParticipant::class);
    }

    public function findConversationIdsForUser(int $userId): array
    {
        $rows = $this->createQueryBuilder('cp')
            ->select('DISTINCT cp.conversation_id AS conversationId')
            ->innerJoin('App\\Entity\\Chat\\Conversation', 'c', 'WITH', 'c.id = cp.conversation_id')
            ->andWhere('cp.user_id = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('c.last_message_at', 'DESC')
            ->addOrderBy('c.created_at', 'DESC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): int => (int) $row['conversationId'], $rows);
    }

    public function findOtherParticipant(int $conversationId, int $userId): ?ConversationParticipant
    {
        return $this->createQueryBuilder('cp')
            ->andWhere('cp.conversation_id = :conversationId')
            ->andWhere('cp.user_id != :userId')
            ->setParameter('conversationId', $conversationId)
            ->setParameter('userId', $userId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findFirstUserId(): ?int
    {
        $row = $this->createQueryBuilder('cp')
            ->select('cp.user_id AS userId')
            ->andWhere('cp.left_at IS NULL')
            ->orderBy('cp.user_id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($row === null) {
            return null;
        }

        return (int) $row['userId'];
    }

    public function isActiveParticipant(int $conversationId, int $userId): bool
    {
        $count = $this->createQueryBuilder('cp')
            ->select('COUNT(cp.user_id)')
            ->andWhere('cp.conversation_id = :conversationId')
            ->andWhere('cp.user_id = :userId')
            ->andWhere('cp.left_at IS NULL')
            ->setParameter('conversationId', $conversationId)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    public function addOrReactivateParticipant(int $conversationId, int $userId, int $addedBy): void
    {
        $participant = $this->findOneBy([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
        ]);

        if ($participant instanceof ConversationParticipant) {
            if ($participant->getLeftAt() === null) {
                throw new InvalidArgumentException('This user is already in the conversation.');
            }

            $this->getEntityManager()->remove($participant);
            $this->getEntityManager()->flush();
        }

        $participant = (new ConversationParticipant())
            ->setConversation_id($conversationId)
            ->setUser_id($userId)
            ->setRole('member')
            ->setNickname(null)
            ->setAdded_by($addedBy)
            ->setJoined_at(new \DateTime())
            ->setLeft_at(null);

        $this->getEntityManager()->persist($participant);
        $this->getEntityManager()->flush();
    }

    public function removeParticipant(int $conversationId, int $userId): void
    {
        $participant = $this->findOneBy([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'left_at' => null,
        ]);

        if (!$participant instanceof ConversationParticipant) {
            throw new InvalidArgumentException('Member not found in this conversation.');
        }

        $this->getEntityManager()->remove($participant);
        $this->getEntityManager()->flush();
    }

    /**
     * @return ConversationParticipant[]
     */
    public function findActiveByConversationId(int $conversationId): array
    {
        return $this->createQueryBuilder('cp')
            ->andWhere('cp.conversation_id = :conversationId')
            ->andWhere('cp.left_at IS NULL')
            ->setParameter('conversationId', $conversationId)
            ->orderBy('cp.last_read_message_id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return ConversationParticipant[] Returns an array of ConversationParticipant objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?ConversationParticipant
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
