<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 31/05/2016
 * Time: 19:26
 */

namespace UserBundle\Controller;

use AppBundle\Entity\Agenda;
use AppBundle\Entity\Calendrier;
use FOS\UserBundle\Event\FilterUserResponseEvent;
use FOS\UserBundle\Event\FormEvent;
use FOS\UserBundle\Event\GetResponseUserEvent;
use FOS\UserBundle\FOSUserEvents;
use FOS\UserBundle\Model\UserInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use \FOS\UserBundle\Controller\ProfileController as BaseController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use FOS\UserBundle\Form\Factory\FactoryInterface;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends BaseController
{
    /**
     * @inheritdoc
     */
    public function showAction() {
        return $this->redirectToRoute("fos_user_profile_edit");
    }

    /**
     * @Route("/delete", name="tbn_user_delete")
     */
    public function deleteAction(Request $request)
    {
        $user = $this->getUser();
        if (!is_object($user) || !$user instanceof UserInterface) {
            throw new AccessDeniedException('This user does not have access to this section.');
        }

        $form = $this->createDeleteForm();
        $form->handleRequest($request);

        if ($form->isValid()) {
            /** @var $userManager \FOS\UserBundle\Model\UserManagerInterface */
            $userManager = $this->get('fos_user.user_manager');

            $em = $this->get('doctrine.orm.entity_manager');

            $deleteEvents = $form->get('delete_events')->getData();
            $events = $this->getDoctrine()->getRepository('AppBundle:Agenda')->findBy([
                'user' => $user,
            ]);

            foreach ($events as $event) {
                if (!$deleteEvents) {
                    $event->setUser(null);
                } else {
                    $em->remove($event);
                }
            }

            $calendriers = $user->getCalendriers();
            foreach ($calendriers as $calendrier) {
                /** @var Calendrier $calendrier */
                $agenda = $calendrier->getAgenda();
                if ($calendrier->getParticipe()) {
                    $agenda->setParticipations($agenda->getParticipations() - 1);
                } else {
                    $agenda->setInterets($agenda->getInterets() - 1);
                }
                $em->remove($calendrier);
            }

            $comments = $this->getDoctrine()->getRepository('AppBundle:Comment')->findAllByUser($user);
            foreach ($comments as $comment) {
                $em->remove($comment);
            }
            $em->flush();

            $userManager->deleteUser($user);

            $this->addFlash('info', 'Votre compte a bien été supprimé. A bientôt sur By Night !');
            return $this->redirectToRoute('tbn_main_index');
        } else {
            $errors = $form->getErrors(true);
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->redirectToRoute('fos_user_profile_edit');
    }

    /**
     * @inheritdoc
     */
    public function editAction(Request $request)
    {
        $user = $this->getUser();
        if (!is_object($user) || !$user instanceof UserInterface) {
            throw new AccessDeniedException('This user does not have access to this section.');
        }

        /** @var $dispatcher EventDispatcherInterface */
        $dispatcher = $this->get('event_dispatcher');

        $event = new GetResponseUserEvent($user, $request);
        $dispatcher->dispatch(FOSUserEvents::PROFILE_EDIT_INITIALIZE, $event);

        if (null !== $event->getResponse()) {
            return $event->getResponse();
        }

        /** @var $formFactory FactoryInterface */
        $formChangePasswordFactory = $this->get('fos_user.change_password.form.factory');

        $formChangePassword = $formChangePasswordFactory->createForm();
        $formChangePassword->setData($user);

        $formDelete = $this->createDeleteForm();

        /** @var $formFactory FactoryInterface */
        $formFactory = $this->get('fos_user.profile.form.factory');
        $form = $formFactory->createForm();
        $form->setData($user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var $userManager \FOS\UserBundle\Model\UserManagerInterface */
            $userManager = $this->get('fos_user.user_manager');

            $event = new FormEvent($form, $request);
            $dispatcher->dispatch(FOSUserEvents::PROFILE_EDIT_SUCCESS, $event);

            $userManager->updateUser($user);

            if (null === $response = $event->getResponse()) {
                $url = $this->generateUrl('fos_user_profile_show');
                $response = new RedirectResponse($url);
            }

            $dispatcher->dispatch(FOSUserEvents::PROFILE_EDIT_COMPLETED, new FilterUserResponseEvent($user, $request, $response));

            return $response;
        }

        return $this->render('FOSUserBundle:Profile:edit.html.twig', array(
            'form' => $form->createView(),
            'formChangePassword' => $formChangePassword->createView(),
            'formDelete' => $formDelete->createView(),
        ));
    }

    private function createDeleteForm()
    {
        return $this->createFormBuilder()
            ->add('delete_events', CheckboxType::class, [
                'required' => false,
            ])
            ->getForm();
    }
}