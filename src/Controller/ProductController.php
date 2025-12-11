<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/product')]
final class ProductController extends AbstractController
{
    public function __construct(
        private ActivityLogService $activityLogService
    ) {
    }
    #[Route(name: 'app_product_index', methods: ['GET'])]
    public function index(Request $request, ProductRepository $productRepository, CategoryRepository $categoryRepository): Response
    {
        $search = trim((string) $request->query->get('search', ''));
        $categoryId = $request->query->getInt('category');
        $sort = (string) $request->query->get('sort', 'name');
        $dir = strtolower((string) $request->query->get('dir', 'asc')) === 'desc' ? 'DESC' : 'ASC';

        $qb = $productRepository->createQueryBuilder('p')
            ->leftJoin('p.Category', 'c')->addSelect('c')
            ->leftJoin('p.createdBy', 'u')->addSelect('u');

        if ($search !== '') {
            $qb->andWhere('LOWER(p.Name) LIKE :search OR LOWER(p.Description) LIKE :search')
               ->setParameter('search', '%' . strtolower($search) . '%');
        }

        if ($categoryId > 0) {
            $qb->andWhere('p.Category = :categoryId')
               ->setParameter('categoryId', $categoryId);
        }

        switch ($sort) {
            case 'price':
                $qb->orderBy('p.Price', $dir);
                break;
            case 'date':
                $qb->orderBy('p.Datetime', $dir);
                break;
            case 'name':
            default:
                $qb->orderBy('p.Name', $dir);
                break;
        }

        $products = $qb->getQuery()->getResult();
        $categories = $categoryRepository->findAll();

        return $this->render('product/index.html.twig', [
            'products' => $products,
            'categories' => $categories,
            'search' => $search,
            'categoryId' => $categoryId,
            'sort' => $sort,
            'dir' => strtolower($dir),
        ]);
    }

   #[Route('/new', name: 'app_product_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                $imageFile->move($this->getParameter('images_directory'), $newFilename);
                $product->setImage($newFilename);
            }


            $product->setDatetime(new \DateTimeImmutable());
            $user = $this->getUser();
            if ($user instanceof \App\Entity\User) {
                $product->setCreatedBy($user);
            }

            $entityManager->persist($product);
            $entityManager->flush();

            if ($user instanceof \App\Entity\User) {
                $this->activityLogService->logCreate(
                    $user,
                    'Product',
                    $product->getId(),
                    ['name' => $product->getName()],
                    sprintf('Created product: %s', $product->getName())
                );
            }
            
            $this->addFlash('success', 'Product created successfully.');

            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('product/new.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }


    #[Route('/{id}', name: 'app_product_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

   #[Route('/{id}/edit', name: 'app_product_edit', methods: ['GET', 'POST'])]
public function edit(Request $request, Product $product, EntityManagerInterface $entityManager): Response
{
    $this->denyUnlessOwnerOrAdmin($product);

    $form = $this->createForm(ProductType::class, $product);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $entityManager->flush();

        $user = $this->getUser();
        if ($user instanceof \App\Entity\User) {
            $this->activityLogService->logUpdate(
                $user,
                'Product',
                $product->getId(),
                ['name' => $product->getName()],
                sprintf('Updated product: %s', $product->getName())
            );
        }
        
        $this->addFlash('success', 'Product updated successfully.');

        // ✅ Redirect back to index after saving
        return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
    }

    return $this->render('product/edit.html.twig', [
        'product' => $product,
        'form' => $form,
    ]);
}


    #[Route('/{id}', name: 'app_product_delete', methods: ['POST'])]
    public function delete(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->request->getString('_token'))) {
            $this->denyUnlessOwnerOrAdmin($product);

            $productName = $product->getName();
            $productId = $product->getId();
            
            $entityManager->remove($product);
            $entityManager->flush();

            $user = $this->getUser();
            if ($user instanceof \App\Entity\User) {
                $this->activityLogService->logDelete(
                    $user,
                    'Product',
                    $productId,
                    ['name' => $productName],
                    sprintf('Deleted product: %s', $productName)
                );
            }
            
            $this->addFlash('success', 'Product deleted successfully.');
        }

        return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
    }

    private function denyUnlessOwnerOrAdmin(Product $product): void
    {
        $user = $this->getUser();
        if ($user instanceof \App\Entity\User && in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return;
        }

        if ($user instanceof \App\Entity\User && $product->getCreatedBy()?->getId() === $user->getId()) {
            return;
        }

        throw new AccessDeniedException('You cannot modify this record.');
    }
}
