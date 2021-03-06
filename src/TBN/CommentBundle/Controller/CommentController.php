<?php

namespace TBN\CommentBundle\Controller;

use FOS\HttpCacheBundle\Configuration\InvalidateRoute;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\CommentBundle\Entity\Comment;
use TBN\CommentBundle\Form\Type\CommentType;
use TBN\CommentBundle\Repository\CommentRepository;
use TBN\MainBundle\Controller\TBNController as Controller;

class CommentController extends Controller
{
    /**
     * @return CommentRepository
     */
    protected function getCommentRepo()
    {
        $repo = $this->getDoctrine()->getRepository('TBNCommentBundle:Comment');

        return $repo;
    }

    protected function getCommentaires(Agenda $soiree, $page = 1, $limit = 10)
    {
        return $this->getCommentRepo()->findAllByAgenda($soiree, $page, $limit);
    }

    protected function getReponses(Comment $comment, $page = 1, $limit = 10)
    {
        return $this->getCommentRepo()->findAllReponses($comment, $page, $limit);
    }

    protected function getNbComments(Agenda $soiree)
    {
        return $this->getCommentRepo()->findNBCommentaires($soiree);
    }

    protected function getNbReponses(Comment $comment)
    {
        return $this->getCommentRepo()->findNBReponses($comment);
    }

    public function detailsAction(Comment $comment)
    {
        return $this->render('TBNCommentBundle:Comment:details.html.twig', [
            'comment'     => $comment,
            'nb_reponses' => $this->getNbReponses($comment),
        ]);
    }

    /**
     * @InvalidateRoute("tbn_comment_list", params={"id" = {"expression"="soiree.getId()"}})
     * @InvalidateRoute("tbn_agenda_details", params={"slug" = {"expression"="soiree.getSlug()"}, "id" = {"expression"="soiree.getId()"}})
     *
     * @param Request $request
     * @param Agenda  $soiree
     *
     * @return Response
     */
    public function newAction(Request $request, Agenda $soiree)
    {
        $comment = new Comment();
        $form    = $this->getCreateForm($comment, $soiree);

        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'post'    => $this->container->get('templating')->render('TBNCommentBundle:Comment:error.html.twig'),
            ]);
        }

        $comment->setUser($user);
        $comment->setAgenda($soiree);
        $form->handleRequest($request);
        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $this->get('tbn.event_http:invalidator')->addEvent($soiree);
            $em->persist($comment);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'comment' => $this->container->get('templating')->render('TBNCommentBundle:Comment:details.html.twig', [
                    'comment'              => $comment,
                    'success_confirmation' => true,
                    'nb_reponses'          => 0,
                ]),
                'header' => $this->container->get('templating')->render('TBNCommentBundle:Comment:header.html.twig', [
                    'nb_comments' => $this->getNbComments($soiree),
                ]),
            ]);
        }

        return new JsonResponse([
            'success' => false,
            'post'    => $this->container->get('templating')->render('TBNCommentBundle:Comment:post.html.twig', [
                'form' => $form->createView(),
            ]),
        ]);
    }

    protected function getCreateForm(Comment $comment, Agenda $soiree)
    {
        return $this->createForm(CommentType::class, $comment, [
            'action' => $this->generateUrl('tbn_comment_new', ['id' => $soiree->getId()]),
            'method' => 'POST',
        ])
            ->add('poster', SubmitType::class, [
                'label' => 'Poster',
                'attr'  => [
                    'class'             => 'btn btn-primary btn-submit btn-raised',
                    'data-loading-text' => 'En cours...',
                ],
            ]);
    }

    /**
     * @param Agenda $soiree
     * @param $page
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @Cache(expires="tomorrow")
     */
    public function listAction(Agenda $soiree, $page)
    {
        $offset  = 10;
        $comment = new Comment();
        $form    = $this->getCreateForm($comment, $soiree);

        $response = $this->render('TBNCommentBundle:Comment:list.html.twig', [
            'nb_comments' => $this->getNbComments($soiree),
            'comments'    => $this->getCommentaires($soiree, $page, $offset),
            'soiree'      => $soiree,
            'page'        => $page,
            'offset'      => $offset,
            'form'        => $form->createView(),
        ]);

        $response->headers->add([
            'X-No-Browser-Cache' => '1',
        ]);

        $tomorrow = $this->getSecondsUntilTomorrow();
        $response->setSharedMaxAge($tomorrow);

        return $response;
    }
}
