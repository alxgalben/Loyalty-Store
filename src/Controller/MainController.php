<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\EditClientFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Form\UserProfileEditFormType;
use App\Form\EmailType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use JetBrains\PhpStorm\NoReturn;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Email;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use App\Form\ForgotPasswordType;


class MainController extends AbstractController
{

    #[Route('/dashboard/accounts', name: 'dashboard')]
    public function index(UserRepository $userRepository): Response
    {
        $this->isGranted('ROLE_ADMIN');
        $role = 'ROLE_ACCOUNT';
        $accountsData = $userRepository->findByRole($role);

        return $this->render('main/index.html.twig', [
            'accounts' => $accountsData
        ]);
    }

    #[Route('dashboard/clients', name: 'dashboard-clients')]
    public function manageClientsFromDashboard(UserRepository $userRepository): Response
    {
        $user = $this->getUser();
        /*$role = 'ROLE_CLIENT';
        $clients = $userRepository->findByRole($role);*/
        //$clientsData = $userRepository->findAll();

        $role = 'ROLE_CLIENT';
        $clientsData = $userRepository->findByRole($role);

        $managedClients = $user->getManagedClient();

        return $this->render('main/manage_clients.html.twig', [
            'managedClients' => $managedClients,
            'clients' => $clientsData,
        ]);
    }

    /**
     * @Route("dashboard/{user}/{id}/{action}", name="set-user-actions", requirements={"user"="clients|accounts", "action"="activate|deactivate|delete|edit"})
     */
    public function markUserAction(Request $request, EntityManagerInterface $em, $user, $id, $action): Response
    {
        $userEntity = $em->getRepository(User::class)->find($id);

        if (!$userEntity) {
            throw $this->createNotFoundException('User not found');
        }

        if ($action === 'activate') {
            $userEntity->setActive(true);
        } elseif ($action === 'deactivate') {
            $userEntity->setActive(false);
        } elseif ($action === 'delete') {
            $em->remove($userEntity);
            $em->flush();

            if ($user === 'clients') {
                return $this->redirectToRoute('dashboard-clients');
            } elseif ($user === 'accounts') {
                return $this->redirectToRoute('dashboard');
            }
        } elseif ($action === 'edit') {
            $form = $this->createForm(EditClientFormType::class, $userEntity);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $em->persist($userEntity);
                $em->flush();

                if ($user === 'clients') {
                    return $this->redirectToRoute('dashboard-clients');
                } elseif ($user === 'accounts') {
                    return $this->redirectToRoute('dashboard');
                }
            }

            return $this->render('main/edit_clients.html.twig', [
                'editForm' => $form->createView(),
                'client' => $userEntity
            ]);
        } else {
            throw $this->createNotFoundException('Invalid action');
        }

        $em->flush();

        if ($user === 'clients') {
            return $this->redirectToRoute('dashboard-clients');
        }

        return $this->redirectToRoute('dashboard');
    }

    /**
     * @Route("/logout", name="logout")
     */

    public function logout(AuthenticationUtils $authenticationUtils)
    {

    }

    /**
     * @Route("/dashboard/logout", name="dashboard_logout")
     */

    public function dashboardLogout(AuthenticationUtils $authenticationUtils)
    {

    }

    /**
     * @Route("/forgot-password", name="forgot_password")
     * @throws TransportExceptionInterface
     */
    public function forgotPassword(Request $request, UserRepository $userRepository, MailerInterface $mailer, TokenGeneratorInterface $tokenGenerator, EntityManagerInterface $em)
    {
        $form = $this->createForm(ForgotPasswordType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();


            $user = $userRepository->findOneBy(['email' => $data['email']]);
            if (!$user) {
                $this->addFlash('danger', 'Invalid address');
                return $this->redirectToRoute("forgot_password");
            }
            $token = $tokenGenerator->generateToken();

            try {
                $user->setToken($token);
                //$em->getManager();
                $em->persist($user);
                $em->flush();
            } catch (Exception $exception) {
                $this->addFlash('warning', 'Error :' . $exception->getMessage());
                return $this->redirectToRoute("app_login");
            }

            $url = $this->generateUrl('app_reset_password', array('token' => $token), UrlGeneratorInterface::ABSOLUTE_URL);

            $email = (new Email())
                ->from('testsender@gmail.com')
                ->to($user->getEmail())
                ->subject('Did u forget the password?')
                ->html("<p>Forgot password?</p> no worries, click here :<br><a href='$url'>$url</a>");

            $mailer->send($email);
        }

        return $this->render("security/forgotPassword.html.twig", ['form' => $form->createView()]);
    }

    #[Route('/profile', name: 'user_profile')]
    #[IsGranted("ROLE_USER")]
    public function indexProfile(): Response
    {
        return $this->render('profile/index.html.twig');
    }

    #[Route('/profile/edit', name: 'user_profile_edit')]
    #[IsGranted("ROLE_USER")]
    public function edit(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(UserProfileEditFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            //$user->setCredit($form->get('credit')->getData());

            $entityManager->persist($user);
            $entityManager->flush();
            return $this->redirectToRoute('user_profile');
        }

        return $this->render('profile/profile-edit.html.twig', [
            'editForm' => $form->createView()
        ]);
    }

    #[Route('/manage-clients', name: 'manage_clients')]
    public function manageClients(UserRepository $userRepository): Response
    {
        $user = $this->getUser();
        /*$role = 'ROLE_CLIENT';
        $clients = $userRepository->findByRole($role);*/
        //$clientsData = $userRepository->findAll();

        if (in_array('ROLE_ACCOUNT', $user->getRoles())) {
            $role = 'ROLE_CLIENT';
            $clientsData = $userRepository->findByRole($role);

            $managedClients = $user->getManagedClient();

            return $this->render('client_admin/manage_clients.html.twig', [
                'managedClients' => $managedClients,
                'clients' => $clientsData,
            ]);
        } else {
            throw $this->createAccessDeniedException('You do not have permission to manage clients.');
        }
    }

    /**
     * @Route("/reset-password/{token}", name="app_reset_password")
     */
    public function resetPassword(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordEncoder, string $token): Response
    {
        //TODO
        // verifici in db daca exista tokenul trimis pe request si identifici userul

        $user = $em->getRepository(User::class)->findOneBy(['token' => $token]);
        if (!$user) {
            // daca nu exista faci redicrect catre forgot-password + flash
            $this->addFlash('danger', 'Password reset link invalid or expired.');
            return $this->redirectToRoute('forgot_password');
        }

        // initializam formularul de reset-password (2 campuri) -- link repeatedtype field

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // parola o hashuim si salvam in db
            $encodedPassword = $passwordEncoder->hashPassword($user, $form->get('plainPassword')->getData());

            $user->setPassword($encodedPassword);
            // setez reset token-ul de pe user pe null
            $user->setToken(null);

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Your password has been successfully reset.');

            // redirect catre login
            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/reset.html.twig', [
            'resetForm' => $form->createView()
        ]);
    }
}