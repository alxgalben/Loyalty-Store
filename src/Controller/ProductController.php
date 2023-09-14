<?php

namespace App\Controller;

use App\Entity\CartItem;
use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Entity\Product;
use App\Entity\User;
use App\Form\ProductType;
use App\Repository\CartItemRepository;
use App\Repository\ClientRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ProductController extends AbstractController
{
    #[Route('dashboard/product/add', name: 'product-add')]
    public function index(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();

            if ($file) {
                $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($filename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
                $product->setFile($newFilename);
                $file->move($this->getParameter('files_directory'), $newFilename);
            }

            $em->persist($product);
            $em->flush();

            return $this->redirectToRoute('list-of-products');
        }
        return $this->render('product/index.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('dashboard/product/{id}/edit', name: 'product-edit')]
    public function editProduct(Request $request, EntityManagerInterface $em, SluggerInterface $slugger, int $id): Response
    {
        $product = $em->getRepository(Product::class)->find($id);

        if ($product === null) {
            return $this->redirectToRoute('list-of-products');
        }
        $oldFile = $product->getFile();

        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();

            if ($file) {
                $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($filename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
                $product->setFile($newFilename);
                $file->move($this->getParameter('files_directory'), $newFilename);
            } else {
                $product->setFile($oldFile);
            }

            $em->persist($product);
            $em->flush();
            return $this->redirectToRoute('list-of-products');

        }

        return $this->render('product/edit.html.twig', [
            'form' => $form->createView()
        ]);

    }

    #[Route('/cart-items', name: 'cart-items')]
    public function cartItems(Request $request, ProductRepository $productRepository): Response
    {
        $session = $request->getSession();
        $cart = $session->get('cart', []);

        $cartItems = [];

        foreach ($cart as $item) {
            $product = $productRepository->find($item['id']);
            if ($product) {
                $cartItems[] = $product;
            }
        }

        return $this->render('product/items.html.twig', [
            'items' => $cartItems
        ]);
    }


    #[Route('/dashboard/products', name: 'list-of-products')]
    public function productListing(ProductRepository $productRepository): Response
    {
        $productData = $productRepository->findAll();

        return $this->render('product/list.html.twig', [
            'products' => $productData
        ]);
    }

    #[Route('/list', name: 'list-of-main-products')]
    public function mainProductListing(Request $request, ProductRepository $productRepository, UserRepository $userRepository): Response
    {
        $userId = $this->getUser()->getId();
        $account = $userRepository->findAccount($userId);
        $session = $request->getSession();
        $productData = $productRepository->findAll();
        //$session->remove('cart');
        $cart = $session->get('cart', []);
        $user = $this->getUser();

        //dd($user->getClients());


        return $this->render('product/main-list.html.twig', [
            'products' => $productData,
            'totalCarItems' => isset($cart) ? count($cart) : 0
        ]);
    }

    // mai bine fac un serviciu si pun $cart = $session->get('cart'); + count()

    /**
     * @Route("/dashboard/products/{id}/delete", name="delete-product")
     */
    public function deleteProduct(Request $request, EntityManagerInterface $em, int $id): Response
    {
        $product = $em->getRepository(Product::class)->find($id);

        if ($product === null) {
            return new Response(1);
        }

        $em->remove($product);
        $em->flush();

        $this->addFlash('success', 'Client deleted successfully.');

        return $this->redirectToRoute('list-of-products');
    }

    /**
     * @Route("/add-to-cart", name="add-to-cart")
     */
    public function addToCart(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $productId = $request->get('productId');
        $productPrice = $request->get('productPrice');

        $user = $this->getUser();
        $product = $entityManager->getRepository(Product::class)->find($productId);
        $session = $request->getSession();

        if (!isset($product)) {
            return $this->json(['success' => false, 'message' => 'Product not found.']);
        }

        $cart = $session->get('cart', []);

        $productIds = array_column($cart, 'id');

        if (in_array($productId, $productIds)) {
            return $this->json(['success' => false, 'message' => 'Product already in cart.']);
        }

        $totalPrice = 0;
        foreach ($cart as $item) {
            $totalPrice += $item['price'];
        }

        $remainingCredit = $user->getCredit() - $productPrice;

        if ($totalPrice > $remainingCredit) {
            return $this->json(['success' => false, 'message' => 'Insufficient credits.']);
        }

        $user->setCredit($remainingCredit);
        $entityManager->persist($user);
        $entityManager->flush();

        $userCredits = $user->getCredit();
        if ($totalPrice > $userCredits) {
            return $this->json(['success' => false, 'message' => 'Insufficient credits.']);
        }

        $cart[] = [
            'id' => $productId,
            'price' => $productPrice
        ];

        $session->set('cart', $cart);

        return $this->json(['success' => true, 'cartItemCount' => count($cart)]);
    }

    /**
     * @Route("/order-confirmation", name="order-confirmation")
     */
    public function orderConfirmation(): Response
    {
        return $this->render('product/order-confirmation.html.twig');
    }

    /**
     * @Route("/approve-order/{id}", name="approve_order")
     */
    public function approveOrder(int $id, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        $order = $entityManager->getRepository(Order::class)->find($id);
        $user = $this->getUser();

        if (!$order) {
            throw $this->createNotFoundException('Order not found.');
        }

        $order->setApproved(true);

        $entityManager->flush();

        $clientEmail = $user->getEmail();

        $email = (new TemplatedEmail())
            ->from('tst@example.ro')
            ->to($clientEmail)
            ->subject('Done!')
            ->htmlTemplate('product/final-email.html.twig');

        $mailer->send($email);

        return $this->redirectToRoute('product/approve-button.html.twig');
    }

    #[Route('/send-order-email', name: 'send_order_email')]
    public function sendOrderEmail(Request $request, EntityManagerInterface $entityManager, MailerInterface $mailer, UrlGeneratorInterface $urlGenerator, UserRepository $userRepository): Response
    {
        $user = $this->getUser();

        $session = $request->getSession();
        $cartItems = $session->get('cart', []);

        $totalPrice = 0;
        foreach ($cartItems as $item) {
            $totalPrice += $item['price'];
        }

        if ($totalPrice > $user->getCredit()) {
            $this->addFlash('error', 'Insufficient credits.');
            return $this->redirectToRoute('cart-items');
        }

        $newOrder = new Order();
        $newOrder->setUser($user);
        $newOrder->setApproved(false);
        $newOrder->setTotalPrice($totalPrice);
        $newOrder->setCreatedAt(new \DateTime());

        foreach ($cartItems as $cartItem) {
            $product = $entityManager->getRepository(Product::class)->find($cartItem['id']);
            if ($product) {
                $orderProduct = new OrderProduct();
                $orderProduct->setOrders($newOrder);
                $orderProduct->setProducts($product);
                $entityManager->persist($orderProduct);
            }
        }

        $entityManager->persist($newOrder);
        $entityManager->flush();

        $userId = $this->getUser()->getId();
        $account = $userRepository->findAccount($userId);

        $editClientUrl = $urlGenerator->generate('approve_order', [
            'id' => $newOrder->getId()
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from('tst@example.ro')
            ->to($account[0]['email'])
            ->subject('New order request')
            ->htmlTemplate('product/order_confirmation_email.html.twig')
            ->context([
                'editClientUrl' => $editClientUrl,
                'order' => $newOrder,
            ]);

        $mailer->send($email);

        return $this->render('product/items.html.twig',[
            'items' => $cartItems
        ]);
    }
}






















