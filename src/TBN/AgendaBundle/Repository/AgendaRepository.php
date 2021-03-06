<?php

namespace TBN\AgendaBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Security\Core\User\UserInterface;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\AgendaBundle\Search\SearchAgenda;
use TBN\MainBundle\Entity\Site;
use TBN\UserBundle\Entity\User;

class AgendaRepository extends EntityRepository
{
    public function updateOlds(\DateTime $since)
    {
        return $this->_em
            ->createQuery('UPDATE TBNAgendaBundle:Agenda a
            SET a.isArchive = TRUE
            WHERE a.dateFin <= :date_fin')
            ->setParameters(['date_fin' => $since->format('Y-m-d')])
            ->execute();
    }

    public function findOlds(\DateTime $since)
    {
        return $this
            ->createQueryBuilder('a')
            ->where('a.isArchive IS NULL OR a.isArchive = :archive')
            ->andWhere('a.dateFin <= :date_fin')
            ->setParameters(['archive' => false, ':date_fin' => $since->format('Y-m-d')])
            ->getQuery()
            ->getResult();
    }

    public function findByInterval(\DateTime $from, \DateTime $to)
    {
        $sites  = $this->_em->getRepository('TBNMainBundle:Site')->findBy([], ['nom' => 'ASC']);
        $events = [];
        foreach ($sites as $site) {
            $events[$site->getNom()] = $this
                ->createQueryBuilder('a')
                ->where('a.dateFin BETWEEN :debut AND :fin')
                ->andWhere('a.site = :site')
                ->orderBy('a.fbParticipations', 'DESC')
                ->setMaxResults(3)
                ->setParameters([
                    ':debut' => $from->format('Y-m-d'),
                    ':fin'   => $to->format('Y-m-d'),
                    ':site'  => $site->getId(),
                ])
                ->getQuery()
                ->getResult();
        }

        return $events;
        /*
         * SELECT a.* FROM event AS a
        LEFT JOIN event AS a2
        ON a.site_id = a2.site_id AND a.participations <= a2.participations
        GROUP BY a.id
        HAVING COUNT(*) <= 2
        ORDER BY a.site_id, a.participations DESC;
         */
//        return $this
//            ->createQueryBuilder('a')
//            ->leftJoin('TBNAgendaBundle:Agenda', 'a2', "WITH", "a.site = a2.site AND a.fbParticipations <= a2.fbParticipations")
//            ->where('a.dateFin BETWEEN :debut AND :fin')
//            ->groupBy("a.id")
//            ->having("COUNT(a) <= 2")
//            ->orderBy("a.site")
//            ->addOrderBy("a.fbParticipations", "DESC")
//            ->setParameters([":debut" => $from->format('Y-m-d'), ":fin" => $from->format('Y-m-d')])
//            ->getQuery()
//            ->getResult();
    }

    public function createQueryBuilder($alias, $indexBy = null)
    {
        $qb = parent::createQueryBuilder($alias, $indexBy);

        $qb->select($alias, 'p')
            ->leftJoin($alias.'.place', 'p');

        return $qb;
    }

    public function findAllByUser(UserInterface $user)
    {
        return $this
            ->createQueryBuilder('a')
            ->where('a.user = :user')
            ->setParameters(['user' => $user])
            ->orderBy('a.dateModification', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAllFBOwnerIds()
    {
        $places = $this->_em
            ->createQueryBuilder()
            ->select('a.facebookOwnerId')
            ->from('TBNAgendaBundle:Agenda', 'a')
            ->where('a.facebookOwnerId IS NOT NULL')
            ->getQuery()
            ->getScalarResult();

        return array_unique(array_filter(array_column($places, 'facebookOwnerId')));
    }

    public function findAllOfWeek()
    {
        $now = new \DateTime();

        return $this->_em
            ->createQueryBuilder()
            ->select('a')
            ->from('TBNAgendaBundle:Agenda', 'a')
            ->where('a.dateFin BETWEEN :date_debut AND :date_fin')
            ->andWhere('a.facebookEventId IS NOT NULL')
            ->setParameters([':date_debut' => $now->format('Y-m-d'), ':date_fin' => $now->add(new \DateInterval('P7D'))->format('Y-m-d')])
            ->getQuery()
            ->getResult();
    }

    public function getLastDateStatsUser(User $user)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('MAX(c.lastDate) as last_date')
            ->from('TBNAgendaBundle:Calendrier', 'c')
            ->leftJoin('TBNUserBundle:User', 'u', 'WITH', 'u = c.user')
            ->where('c.user = :user')
            ->setParameters([':user' => $user->getId()])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getNextEvents(\DateTime $since, $page, $offset)
    {
        return $this
            ->createQueryBuilder('a')
            ->where('a.dateFin >= :since')
            ->setParameters([':since' => $since->format('Y-m-d')])
            ->setFirstResult($page * $offset)
            ->setMaxResults($offset)
            ->getQuery()
            ->getResult();
    }

    public function getNextEventsFbIds(\DateTime $since)
    {
        $datas = $this->_em
            ->createQueryBuilder()
            ->select('DISTINCT(a.facebookEventId)')
            ->from('TBNAgendaBundle:Agenda', 'a')
            ->where('a.dateFin >= :since')
            ->setParameters([':since' => $since->format('Y-m-d')])
            ->getQuery()
            ->getScalarResult();

        return array_filter(array_map('current', $datas));
    }

    public function getNextEventsCount(\DateTime $since)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('count(a.id)')
            ->from('TBNAgendaBundle:Agenda', 'a')
            ->where('a.dateFin >= :since')
            ->setParameters([':since' => $since->format('Y-m-d')])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getStatsUser(User $user, \DateTime $dateFin, $groupByDay = true)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('COUNT(u) as nbEvents')
            ->addSelect('a.dateDebut')
            ->addSelect(($groupByDay ? 'SUBSTRING(a.dateDebut, 6, 5)' : 'SUBSTRING(a.dateDebut, 1, 7)').' as date_event')
            ->from('TBNAgendaBundle:Calendrier', 'c')
            ->leftJoin('TBNUserBundle:User', 'u', 'WITH', 'u = c.user')
            ->leftJoin('TBNAgendaBundle:Agenda', 'a', 'WITH', 'a = c.agenda')
            ->where('c.user = :user')
            ->andWhere('a.dateDebut >= :date_fin')
            ->groupBy('date_event')
            ->setParameters([':user' => $user->getId(), 'date_fin' => $dateFin])
            ->getQuery()
            ->getScalarResult();
    }

    public function findAllPlaces(User $user, $limit = 5)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('COUNT(u) as nbEtablissements, p.nom')
            ->from('TBNAgendaBundle:Calendrier', 'c')
            ->leftJoin('TBNUserBundle:User', 'u', 'WITH', 'u = c.user')
            ->leftJoin('TBNAgendaBundle:Agenda', 'a', 'WITH', 'a = c.agenda')
            ->leftJoin('TBNAgendaBundle:Place', 'p', 'WITH', 'p = a.place')
            ->where('c.user = :user')
            ->groupBy('p.nom')
            ->orderBy('nbEtablissements', 'DESC')
            ->setParameters([':user' => $user->getId()])
            ->setFirstResult(0)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findAllNextEvents(User $user, $isNext = true, $page = 1, $limit = 3)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('a')
            ->from('TBNAgendaBundle:Calendrier', 'c')
            ->leftJoin('TBNAgendaBundle:Agenda', 'a', 'WITH', 'c.agenda = a')
            ->where('c.user = :user')
            ->andWhere('a.dateFin '.($isNext ? '>=' : '<').' :date_debut')
            ->orderBy('a.dateFin', $isNext ? 'ASC' : 'DESC')
            ->setParameters([':user' => $user->getId(), 'date_debut' => date('Y-m-d')])
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();
    }

    protected function getCountAllParticipations(User $user, $isParticipation = true)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('COUNT(u)')
            ->from('TBNAgendaBundle:Calendrier', 'c')
            ->leftJoin('TBNUserBundle:User', 'u', 'WITH', 'u = c.user')
            ->where('c.user = :user')
            ->andWhere(($isParticipation ? 'c.participe' : 'c.interet').' = :vrai')
            ->setParameters([':user' => $user->getId(), 'vrai' => true])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getLastDateTopSoiree(Site $site)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('MAX(c.lastDate) as last_date')
            ->from('TBNAgendaBundle:Calendrier', 'c')
            ->leftJoin('TBNAgendaBundle:Agenda', 'a', 'WITH', 'a = c.agenda')
            ->where('a.site = :site')
            ->setParameters([':site' => $site->getId()])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getCountParticipations(User $user)
    {
        return $this->getCountAllParticipations($user);
    }

    public function getCountInterets(User $user)
    {
        return $this->getCountAllParticipations($user, false);
    }

    protected function getCountTendances(Agenda $soiree, $isParticipation = true)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('COUNT(u)')
            ->from('TBNAgendaBundle:Calendrier', 'c')
            ->leftJoin('TBNUserBundle:User', 'u', 'WITH', 'u = c.user')
            ->where('c.agenda = :agenda')
            ->andWhere(($isParticipation ? 'c.participe' : 'c.interet').' = :vrai')
            ->setParameters([':agenda' => $soiree->getId(), 'vrai' => true])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getCountTendancesParticipation(Agenda $soiree)
    {
        return $this->getCountTendances($soiree);
    }

    public function getCountTendancesInterets(Agenda $soiree)
    {
        return $this->getCountTendances($soiree, false);
    }

    protected function findAllTendances(Agenda $soiree, $page, $limit, $isParticipation = true)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('u')
            ->from('TBNAgendaBundle:Calendrier', 'c')
            ->leftJoin('TBNUserBundle:User', 'u', 'WITH', 'u = c.user')
            ->where('c.agenda = :agenda')
            ->andWhere(($isParticipation ? 'c.participe' : 'c.interet').' = :vrai')
            ->setParameters([':agenda' => $soiree, 'vrai' => true])
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();
    }

    public function findAllTendancesParticipations(Agenda $soiree, $page = 1, $limit = 7)
    {
        return $this->findAllTendances($soiree, $page, $limit);
    }

    public function findAllTendancesInterets(Agenda $soiree, $page = 1, $limit = 7)
    {
        return $this->findAllTendances($soiree, $page, $limit, false);
    }

    public function getLastDateAutreSoirees(Agenda $soiree)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('MAX(a.dateModification)')
            ->from('TBNAgendaBundle:Agenda', 'a')
            ->where('a.dateDebut = :date_debut AND a.id != :id AND a.site = :site')
            ->setParameters([':date_debut' => $soiree->getDateDebut(), ':id' => $soiree->getId(), ':site' => $soiree->getSite()->getId()])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findAllSimilaires(Agenda $soiree, $page = 1, $limit = 7)
    {
        return $this
            ->createQueryBuilder('a')
            ->where('a.dateDebut = :date_debut AND a.id != :id AND a.site = :site')
            ->orderBy('a.nom', 'ASC')
            ->setParameters([':date_debut' => $soiree->getDateDebut()->format('Y-m-d'), ':id' => $soiree->getId(), ':site' => $soiree->getSite()->getId()])
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();
    }

    public function findAllNext(Agenda $soiree, $page = 1, $limit = 7)
    {
        return $this
            ->createQueryBuilder('a')
            ->where('a.dateFin >= :date_fin AND a.id != :id AND a.place = :place')
            ->orderBy('a.dateFin', 'ASC')
            ->addOrderBy('a.fbParticipations', 'DESC')
            ->setParameters([':date_fin' => $soiree->getDateFin()->format('Y-m-d'), ':id' => $soiree->getId(), ':place' => $soiree->getPlace()->getId()])
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();
    }

    public function findAllSimilairesCount(Agenda $soiree)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('count(a.id)')
            ->from('TBNAgendaBundle:Agenda', 'a')
            ->where('a.dateDebut = :date_debut AND a.id != :id AND a.site = :site')
            ->setParameters([':date_debut' => $soiree->getDateDebut()->format('Y-m-d'), ':id' => $soiree->getId(), ':site' => $soiree->getSite()->getId()])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findAllNextCount(Agenda $soiree)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('count(a.id)')
            ->from('TBNAgendaBundle:Agenda', 'a')
            ->where('a.dateFin >= :date_fin AND a.id != :id AND a.place = :place')
            ->setParameters([':date_fin' => $soiree->getDateFin()->format('Y-m-d'), ':id' => $soiree->getId(), ':place' => $soiree->getPlace()->getId()])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findTopSoireeCount(Site $site)
    {
        $du = new \DateTime();
        $au = new \DateTime('sunday this week');

        return $this->_em
            ->createQueryBuilder()
            ->select('count(a.id)')
            ->from('TBNAgendaBundle:Agenda', 'a')
            ->where('a.site = :site')
            ->andWhere('a.dateFin BETWEEN :du AND :au')
            ->setParameters([':site' => $site->getId(), 'du' => $du->format('Y-m-d'), 'au' => $au->format('Y-m-d')])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findTopSoiree(Site $site, $page = 1, $limit = 7)
    {
        $du = new \DateTime();
        $au = new \DateTime('sunday this week');

        $soirees = $this
            ->createQueryBuilder('a')
            ->where('a.site = :site')
            ->andWhere('a.dateFin BETWEEN :du AND :au')
            ->orderBy('a.fbParticipations', 'DESC')
            ->addOrderBy('a.fbInterets', 'DESC')
            ->setParameters([':site' => $site->getId(), 'du' => $du->format('Y-m-d'), 'au' => $au->format('Y-m-d')])
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();

        usort($soirees, function (Agenda $a, Agenda $b) {
            if ($a->getDateFin() === $b->getDateFin()) {
                return 0;
            }

            return $a->getDateFin() > $b->getDateFin() ? -1 : 1;
        });

        return $soirees;
    }

    protected function makeQuery(QueryBuilder $qb, Site $site, SearchAgenda $search)
    {
        $params = [':site' => $site->getId()];
        $qb->where('a.site = :site');

        if (null !== $search->getDu()) {
            $params[':du'] = $search->getDu()->format('Y-m-d');
            if (null === $search->getAu()) {
                $qb->andWhere(':du BETWEEN a.dateDebut AND a.dateFin');
            } else {
                $qb->andWhere('((a.dateDebut BETWEEN :du AND :au) '
                    .'OR (a.dateFin BETWEEN :du AND :au) '
                    .'OR (:du BETWEEN a.dateDebut AND a.dateFin)'
                    .'OR (:au BETWEEN a.dateDebut AND a.dateFin))');

                $params[':au'] = $search->getAu()->format('Y-m-d');
            }
        } else {
            $qb->andWhere('a.dateDebut >= :now');
            $params[':now'] = (new \DateTime())->format('Y-m-d');
        }

        if (count($search->getTerms()) > 0) {
            $qb->andWhere('(a.nom LIKE :mot_clefs OR a.descriptif LIKE :mot_clefs OR a.lieuNom LIKE :mot_clefs)');
            $params[':mot_clefs'] = '%'.$search->getTerm().'%';
        }

        if (null !== $search->getTypeManifestation() && count($search->getTypeManifestation()) > 0) {
            $qb->andWhere('a.typeManifestation IN(:type_manifesation)');
            $params[':type_manifesation'] = $search->getTypeManifestation();
        }
        if (null !== $search->getCommune() && count($search->getCommune()) > 0) {
            $qb->andWhere('a.commune IN(:commune)');
            $params[':commune'] = $search->getCommune();
        }

        if (null !== $search->getLieux() && count($search->getLieux()) > 0) {
            $qb->andWhere('a.lieuNom IN(:lieux)');
            $params[':lieux'] = $search->getLieux();
        }

        return $qb
            ->setParameters($params);
    }

    public function findCountWithSearch(Site $site, SearchAgenda $search)
    {
        $qb = $this->_em
            ->createQueryBuilder()
            ->select('COUNT(a) as nombre')
            ->from('TBNAgendaBundle:Agenda', 'a');

        return
            $this->makeQuery($qb, $site, $search)
                ->getQuery()
                ->getSingleScalarResult();
    }

    public function findWithSearch(Site $site, SearchAgenda $search, $page = 1, $limit = 15, $orderDesc = true)
    {
        $qb = $this->_em
            ->createQueryBuilder()
            ->select('a')
            ->from('TBNAgendaBundle:Agenda', 'a')
            ->orderBy('a.dateDebut', $orderDesc ? 'DESC' : 'ASC');

        $soirees = $this->makeQuery($qb, $site, $search);

        if (false !== $page && false !== $limit) {
            $soirees = $soirees
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit);
        }

        return $soirees
            ->getQuery()
            ->execute();
    }

    //Appelé par DoctrineEventParser
    public function findAllByDates(array $events)
    {
        if (!count($events)) {
            return [];
        }

        $params = [];
        $query  = $this->createQueryBuilder('a');

        $i = 0;
        foreach ($events as $event) {
            ++$i;
            /*
             * @var Agenda $event
             */
            $query->orWhere("a.dateDebut = :date_debut_$i AND a.dateFin = :date_fin_$i AND a.site = :site_$i");
            $params["date_debut_$i"] = $event->getDateDebut()->format('Y-m-d');
            $params["date_fin_$i"]   = $event->getDateFin()->format('Y-m-d');
            $params["site_$i"]       = $event->getSite()->getId();
        }

        return $query
            ->setParameters($params)
            ->getQuery()
            ->getResult();
    }

    //Appelé par AgendaParser
    public function findOneByPlace($lieuNom, \DateTime $dateDebut, \DateTime $dateFin)
    {
        $query = $this
            ->createQueryBuilder('a')
            ->where('p.nom = :nom')
            ->andWhere('a.dateDebut = :date_debut')
            ->andWhere('a.dateFin = :date_fin')
            ->setParameters([':nom' => $lieuNom, 'date_debut' => $dateDebut->format('Y-m-d'), 'date_fin' => $dateFin->format('Y-m-d')])
            ->getQuery()
            ->setMaxResults(1);

        try {
            $agenda = $query->getSingleResult();
        } catch (NoResultException $e) {
            $agenda = null;
        }

        return $agenda;
    }

    public function getEventsWithFBOwner(Site $site)
    {
        return $this
            ->createQueryBuilder('a')
            ->where('a.site = :site')
            ->andWhere("a.facebookOwnerId != ''")
            ->groupBy('a.facebookOwnerId')
            ->setParameters([':site' => $site->getId()])
            ->getQuery()
            ->execute();
    }

    public function getAgendaPlaces(Site $site)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('p')
            ->from('TBNAgendaBundle:Agenda', 'a')
            ->leftJoin('TBNAgendaBundle:Place', 'p', 'WITH', 'p = a.place')
            ->where('a.site = :site')
            ->andWhere("p.nom != ''")
            ->andWhere('a.dateDebut >= :today')
            ->groupBy('p.nom')
            ->orderBy('p.nom')
            ->setParameters([':site' => $site->getId(), 'today' => (new \DateTime())->format('Y-m-d')])
            ->getQuery()
            ->execute();
    }

    public function getAgendaVilles(Site $site)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('p')
            ->from('TBNAgendaBundle:Agenda', 'a')
            ->leftJoin('TBNAgendaBundle:Place', 'p', 'WITH', 'p = a.place')
            ->where('a.site = :site')
            ->andWhere("p.ville != ''")
            ->andWhere('a.dateDebut >= :today')
            ->groupBy('p.ville')
            ->orderBy('p.ville')
            ->setParameters([':site' => $site->getId(), 'today' => (new \DateTime())->format('Y-m-d')])
            ->getQuery()
            ->execute();
    }

    public function getTypesEvenements(Site $site)
    {
        return $this
            ->createQueryBuilder('a')
            ->where('a.site = :site')
            ->andWhere("a.categorieManifestation != ''")
            //->andWhere("a.dateDebut >= :today")
            ->groupBy('a.categorieManifestation')
            ->orderBy('a.categorieManifestation', 'DESC')
            ->setParameters([':site' => $site->getId()])
            ->getQuery()
            ->execute();
    }
}
