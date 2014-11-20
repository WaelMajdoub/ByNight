<?php

namespace TBN\UserBundle\Controller;

use TBN\MainBundle\Controller\TBNController as Controller;
use TBN\UserBundle\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller {

    public function searchAction($term)
    {
        $users = [];

        if($term !== null and $term !== "")
        {
            $siteManager = $this->get("site_manager");
            $site = $siteManager->getCurrentSite();

            $em = $this->getDoctrine()->getManager();
            $repo = $em->getRepository("TBNUserBundle:User");

            $users = $repo->search($site, $term);
        }

        return $this->render("TBNUserBundle:Membres:recherche.html.twig", [
            "users" => $users,
            "term" => $term
        ]);
    }

    public function searchRedirectAction(Request $request)
    {
        $term = $request->request->get('term', null);
        return new RedirectResponse($this->get("router")->generate("tbn_user_search", ["term" => $term]));
    }

    public function detailsAction(User $user) {
        $em         = $this->getDoctrine()->getManager();
        $repo       = $em->getRepository("TBNAgendaBundle:Agenda");

        $response   = new Response;


        return $response->setContent($this->renderView('TBNUserBundle:Membres:details.html.twig', [
                    "user" => $user,
                    "next_events" => $repo->findAllNextEvents($user),
                    "previous_events" => $repo->findAllNextEvents($user, false),
                    "etablissements" => $repo->findAllEtablissements($user),
                    "count_participations" => $repo->getCountParticipations($user),
                    "count_interets" => $repo->getCountInterets($user),
        ]));
    }

    public function listAction() {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository("TBNUserBundle:User");

        $siteManager = $this->get("site_manager");
        $site = $siteManager->getCurrentSite();

        $new_membres = $repo->findBy([
            "site" => $site
        ], [
            "date_creation" => "DESC"
        ]);

        $last_actifs = $repo->findBy([
            "site" => $site
        ], [
            "lastLogin" => "DESC"
        ]);

        return $this->render('TBNUserBundle:Membres:list.html.twig', [
            "new_membres" => $new_membres,
            "last_actifs" => $last_actifs
        ]);
    }

    public function statsAction(Request $request, User $user, $type) {

        $em         = $this->getDoctrine()->getManager();
        $repo       = $em->getRepository("TBNAgendaBundle:Agenda");
        $str_date   = $repo->getLastDateStatsUser($user);

        $response   = $this->cacheVerif($str_date);
        if($response !== null)
        {
            // Vérifie que l'objet Response n'est pas modifié
            // pour un objet Request donné
            if ($response->isNotModified($request)) {
                // Retourne immédiatement un objet 304 Response
                return $response;
            }
        }

        $today = new \DateTime();
        switch ($type) {
            case "semaine":
                $datas = $this->getDataOfWeek($repo, $user, $today);
                break;
            case "mois":
                $datas = $this->getDataOfMonth($repo, $user, $today);
                break;
            case "annee":
                $datas = $this->getDataOfYear($repo, $user, $today);
                break;
        }

        return $response->setData($datas);
    }

    protected function cacheVerif($str_date)
    {
        $response   = new JsonResponse();

        if($str_date !== null)
	{
            //2014-05-08 11:49:21
            if(($date = \DateTime::createFromFormat("Y-m-d H:i:s", $str_date)))
            {
                $response->setPublic(); //Afin d'être partagée avec tout le monde
                $response->setLastModified($date);

            }
        }

        return $response;
    }

    protected function getDataOfWeek($repo, User $user) {

        $now = new \DateTime;
        $date = $this->calculDate('P1W');
        $datas = $repo->getStatsUser($user, $date);

        $final_datas = [
            "categories" => [],
            "data" => [],
            "full_categories"
        ];

        for ($i = 1; $date <= $now; $i++) {
            $nb_events = 0;
            foreach ($datas as $data) {
                if ($data["date_event"] == $date->format("m-d")) {
                    $nb_events = $data["nbEvents"];
                }
            }

            $cle = ucfirst($this->getDayName($date->format("N")));
            $final_datas["full_categories"][] = $cle." ".$date->format("d")." ".$this->getMonthName($date->format("m")) . " " . $date->format("Y");
            $final_datas["categories"][] = $cle;
            $final_datas["data"][] = intval($nb_events);

            $date->add(new \DateInterval('P1D'));
        }

        return $final_datas;
    }

    protected function getDataOfMonth($repo, User $user) {

        $now = new \DateTime;
        $date = $this->calculDate('P1M');
        $datas = $repo->getStatsUser($user, $date);

        $final_datas = [
            "categories" => [],
            "data" => [],
            "full_categories"
        ];

        for ($i = 1; $date <= $now; $i++) {
            $nb_events = 0;
            foreach ($datas as $data) {
                if ($data["date_event"] == $date->format("m-d")) {
                    $nb_events = $data["nbEvents"];
                }
            }

            $cle = "";
            if($i % 8 === 2)
            {
                $cle = ucfirst($this->getDayName($date->format("N")))." ".$date->format("d");
            }

            $final_datas["full_categories"][] = ucfirst($this->getDayName($date->format("N")))." ".$date->format("d")." ".$this->getMonthName($date->format("m")) . " " . $date->format("Y");
            $final_datas["categories"][] = $cle;
            $final_datas["data"][] = intval($nb_events);

            $date->add(new \DateInterval('P1D'));
        }

        return $final_datas;
    }

    protected function getDataOfYear($repo, User $user) {
        $now = new \DateTime;
        $date = $this->calculDate('P1Y');
        $datas = $repo->getStatsUser($user, $date, false);

        $final_datas = [
            "categories" => [],
            "data" => [],
            "full_categories"
        ];

        for ($i = 1; $date <= $now; $i++) {
            $nb_events = 0;
            foreach ($datas as $data) {
                if ($data["date_event"] == $date->format("Y-m")) {
                    $nb_events = $data["nbEvents"];
                }
            }

            $cle = ucfirst(utf8_encode(substr(utf8_decode($this->getMonthName($date->format("m"))),0, 3)));
            $final_datas["full_categories"][] = ucfirst($this->getMonthName($date->format("m"))) . " " . $date->format("Y");
            $final_datas["categories"][] = $cle;
            $final_datas["data"][] = intval($nb_events);

            $date->add(new \DateInterval('P1M'));
        }

        return $final_datas;
    }

    protected function getMonthName($number) {
        $months = ["janvier", "février", "mars", "avril", "mai", "juin", "juillet", "août", "septembre", "octobre", "novembre", "décembre"];
        return $months[(intval($number) - 1)];
    }

    protected function getDayName($number) {
        $days = ["lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi", "dimanche"];
        return $days[(intval($number) - 1)];
    }

    protected function calculDate($format) {
        $debut = new \DateTime();
        $debut->sub(new \DateInterval($format));

        return $debut;
    }

}
