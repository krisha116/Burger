<?php

namespace App\Controller;

use App\Entity\Order;
use App\Form\OrderType;
use App\Repository\OrderRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;


#[Route('/order')]
final class OrderController extends AbstractController
{
    public function __construct(
        private ActivityLogService $activityLogService
    ) {
    }

    #[Route(name: 'app_order_index', methods: ['GET'])]
    public function index(Request $request, OrderRepository $orderRepository): Response
    {
        $search = trim((string) $request->query->get('search', ''));
        $statusFilter = $request->query->get('status');
        $sort = (string) $request->query->get('sort', 'date');
        $dir = strtolower((string) $request->query->get('dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $qb = $orderRepository->createQueryBuilder('o')
            ->leftJoin('o.Customer', 'c')->addSelect('c')
            ->leftJoin('o.createdBy', 'u')->addSelect('u');

        if ($search !== '') {
            $qb->andWhere('LOWER(o.Name) LIKE :search OR LOWER(c.Name) LIKE :search')
               ->setParameter('search', '%' . strtolower($search) . '%');
        }

        if ($statusFilter && $statusFilter !== '') {
            $qb->andWhere('o.Status = :status')
               ->setParameter('status', $statusFilter);
        }

        switch ($sort) {
            case 'name':
                $qb->orderBy('o.Name', $dir);
                break;
            case 'total':
                $qb->orderBy('o.Total', $dir);
                break;
            case 'status':
                $qb->orderBy('o.Status', $dir);
                break;
            case 'date':
            default:
                $qb->orderBy('o.createAt', $dir);
                break;
        }

        $orders = $qb->getQuery()->getResult();

        return $this->render('order/index.html.twig', [
            'orders' => $orders,
            'search' => $search,
            'statusFilter' => $statusFilter,
            'sort' => $sort,
            'dir' => strtolower($dir),
        ]);
    }

    #[Route('/new', name: 'app_order_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $order = new Order();
        $form = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $order->setCreateAt(new \DateTimeImmutable());
            $user = $this->getUser();
            if ($user instanceof \App\Entity\User) {
                $order->setCreatedBy($user);
            }

            $entityManager->persist($order);
            $entityManager->flush();

            if ($user instanceof \App\Entity\User) {
                $this->activityLogService->logCreate(
                    $user,
                    'Order',
                    $order->getId(),
                    ['name' => $order->getName(), 'total' => $order->getTotal()],
                    sprintf('Created order: %s', $order->getName())
                );
            }
            
            $this->addFlash('success', 'Order created successfully.');

            return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('order/new.html.twig', [
            'order' => $order,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_order_show', methods: ['GET'])]
    public function show(Order $order): Response
    {
        return $this->render('order/show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_order_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Order $order, EntityManagerInterface $entityManager): Response
    {
        $this->denyUnlessOwnerOrAdmin($order);

        $form = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $user = $this->getUser();
            if ($user instanceof \App\Entity\User) {
                $this->activityLogService->logUpdate(
                    $user,
                    'Order',
                    $order->getId(),
                    ['name' => $order->getName(), 'total' => $order->getTotal()],
                    sprintf('Updated order: %s', $order->getName())
                );
            }
            
            $this->addFlash('success', 'Order updated successfully.');

            return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('order/edit.html.twig', [
            'order' => $order,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_order_delete', methods: ['POST'])]
    public function delete(Request $request, Order $order, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$order->getId(), $request->request->getString('_token'))) {
            $this->denyUnlessOwnerOrAdmin($order);

            $orderName = $order->getName();
            $orderTotal = $order->getTotal();
            $orderId = $order->getId();
            
            $entityManager->remove($order);
            $entityManager->flush();

            $user = $this->getUser();
            if ($user instanceof \App\Entity\User) {
                $this->activityLogService->logDelete(
                    $user,
                    'Order',
                    $orderId,
                    ['name' => $orderName, 'total' => $orderTotal],
                    sprintf('Deleted order: %s', $orderName)
                );
            }
            
            $this->addFlash('success', 'Order deleted successfully.');
        }

        return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
    }

    private function denyUnlessOwnerOrAdmin(Order $order): void
    {
        $user = $this->getUser();
        if ($user instanceof \App\Entity\User && in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return;
        }

        if ($user instanceof \App\Entity\User && $order->getCreatedBy()?->getId() === $user->getId()) {
            return;
        }

        throw new AccessDeniedException('You cannot modify this record.');
    }
}
