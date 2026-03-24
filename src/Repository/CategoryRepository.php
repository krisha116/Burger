<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * Match a canonical menu label (Burger, Fries, Drinks) to a Category entity by name.
     */
    public function findBestMatchForMenuLabel(string $canonicalLabel): ?Category
    {
        $canonicalLabel = trim($canonicalLabel);
        if (!in_array($canonicalLabel, ['Burger', 'Fries', 'Drinks'], true)) {
            return null;
        }

        foreach ($this->findAll() as $cat) {
            $n = trim($cat->getName() ?? '');
            if ($n !== '' && strcasecmp($n, $canonicalLabel) === 0) {
                return $cat;
            }
        }

        $patterns = [
            'Burger' => '/burger/i',
            'Fries' => '/fries|french/i',
            'Drinks' => '/drink|beverage|tea|coffee|juice|soda|smoothie|shake|milk|boba/i',
        ];
        $re = $patterns[$canonicalLabel] ?? null;
        if ($re === null) {
            return null;
        }

        foreach ($this->findAll() as $cat) {
            if (preg_match($re, $cat->getName() ?? '')) {
                return $cat;
            }
        }

        return null;
    }

    //    /**
    //     * @return Category[] Returns an array of Category objects
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

    //    public function findOneBySomeField($value): ?Category
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
