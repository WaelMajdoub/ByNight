<?php

namespace TBN\MajDataBundle\Utils;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\Cache;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\AgendaBundle\Entity\Place;

/**
 * @author guillaume
 */
class Comparator
{
    /**
     * @var ArrayCache
     */
    private $cache;

    /**
     * @var Util
     */
    private $util;

    public function __construct(Util $util, Cache $cache)
    {
        $this->util  = $util;
        $this->cache = $cache;
    }

    public function deleteCache()
    {
        $this->cache->deleteAll();
    }

    public function getMatchingScoreVille(Place $a = null, Place $b = null)
    {
        $pourcentage = 0;
        if (null !== $a && null !== $b) {
            $isMatchingCP = ($a->getCodePostal() && $b->getCodePostal()) ? $this->isSameText($a->getCodePostal(), $b->getCodePostal()) : false;
            //Même CP -> fortement identiques
            if ($isMatchingCP) {
                return 100;
            }

            $matchingNom = ($a->getVille() && $b->getVille()) ? $this->getMatchingScoreText($a->getVille(), $b->getVille()) : 0;

            //Même Nom et pas de CP sur l'une des villes -> fortement identiques
            if ($matchingNom >= 80 && (!$a->getCodePostal() || !$b->getCodePostal())) {
                return 80;
            } elseif ($matchingNom >= 80) {
                return 75;
            }
        }

        return $pourcentage;
    }

    public function getBestPlace(array $places, Place $testedPlace = null)
    {
        return $this->getBest($places, [$this, 'getMatchingScorePlace'], $testedPlace, 80);
    }

    public function getMatchingScorePlace(Place $a = null, Place $b = null)
    {
        if (null !== $a && null !== $b) {
            if ($this->getStrictMatchingPlace($a, $b)) {
                return 100;
            }

            if ($this->getMatchingScoreVille($a, $b) >= 75) {
                //~ Même ville et même rue
                if ($this->getMatchingScoreRue($a->getRue(), $b->getRue()) >= 100) {
                    if ($this->getMatchingScoreText($a->getNom(), $b->getNom()) >= 80) {
                        return 100;
                    }
                }

                if ($this->getMatchingScoreText($a->getNom(), $b->getNom()) >= 80) {
                    return 90;
                }

                if ($this->isSubInSub($a->getNom(), $b->getNom())) {
                    return 85;
                }
            }
        }

        return 0;
    }

    protected function getStrictMatchingEvent(Agenda $a, Agenda $b)
    {
        return ($a->getFacebookEventId() && $a->getFacebookEventId() == $b->getFacebookEventId()) ||
        ($a->getId() && $a->getId() == $b->getId());
    }

    protected function getStrictMatchingPlace(Place $a, Place $b)
    {
        return ($a->getFacebookId() && $a->getFacebookId() == $b->getFacebookId()) ||
        ($a->getId() && $a->getId() == $b->getId());
    }

    public function getBestEvent(array $events, Agenda $testedEvent)
    {
        return $this->getBest($events, [$this, 'getMatchingScoreEvent'], $testedEvent);
    }

    protected function getMatchingScoreEvent(Agenda $a, Agenda $b)
    {
        //Fort taux de ressemblance du nom ou descriptif ou égalité de l'id FB
        if ($this->getStrictMatchingEvent($a, $b)) {
            return 100;
        }

        $placeA = $a->getPlace();
        $placeB = $b->getPlace();
        if ($this->getMatchingScorePlace($placeA, $placeB) >= 80) {
            if ($this->getMatchingScoreText($a->getNom(), $b->getNom()) >= 75 ||
                $this->getMatchingScoreHTML($a->getDescriptif(), $b->getDescriptif()) >= 75) {
                return 90;
            }

            if ($this->isSubInSub($a->getNom(), $b->getNom())) {
                return 85;
            }
        }

        return 0;
    }

    private function getBest(array $items, callable $machingFunction, $testedItem = null, $minScore = 75)
    {
        if (null === $testedItem) {
            return;
        }

        $bestScore = 0;
        $bestItem  = null;

        foreach ($items as $item) {
            $score = call_user_func($machingFunction, $item, $testedItem);

            if ($score >= 100) {
                return $item;
            } elseif ($score >= $minScore && $score > $bestScore) {
                $bestItem  = $item;
                $bestScore = $score;
            }
        }

        return $bestItem;
    }

    protected function isSubInSub($str1, $str2)
    {
        $sanitized1 = $this->sanitize($str1);
        $sanitized2 = $this->sanitize($str2);

        return $this->isSubstrInStr($str1, $str2) || $this->isSubstrInStr($str2, $str1) || $this->isSubstrInStr($sanitized1, $sanitized2) || $this->isSubstrInStr($sanitized2, $sanitized1);
    }

    protected function isSubstrInStr($needle, $haystack)
    {
        return false !== strpos($haystack, $needle);
    }

    protected function getMatchingScore($a, $b)
    {
        $pourcentage = 0;
        // = strlen > 0
        if (isset($a[0]) && isset($b[0]) > 0) {
            if ($a === $b) {
                return 100;
            }

            if (isset($a[250]) || isset($b[250])) {
                similar_text($a, $b, $pourcentage);
            } else {
                try {
                    $pourcentage = $this->getDiffPourcentage($a, $b);
                } catch (\Exception $ex) {
                }
            }
        }

        return $pourcentage;
    }

    private function getDiffPourcentage($a, $b)
    {
        $hashA = md5($a);
        $hashB = md5($b);
        $keys  = ['getDiffPourcentage.'.$hashA.'.'.$hashB, 'getDiffPourcentage.'.$hashB.'.'.$hashA];

        foreach ($keys as $key) {
            if ($this->cache->contains($key)) {
                return $this->cache->fetch($key);
            }
        }

        $score = (1 - levenshtein($a, $b) / max(strlen($a), strlen($b))) * 100;

        $this->cache->save($keys[0], $score);

        return $score;
    }

    protected function getMatchingScoreRue($a, $b)
    {
        $trimedA = $this->sanitizeRue($a);
        $trimedB = $this->sanitizeRue($b);

        return $this->getMatchingScore($trimedA, $trimedB);
    }

    protected function getMatchingScoreHTML($a, $b)
    {
        $trimedA = $this->sanitizeHTML($a);
        $trimedB = $this->sanitizeHTML($b);

        return $this->getMatchingScore($trimedA, $trimedB);
    }

    protected function getMatchingScoreText($a, $b)
    {
        $trimedA = $this->sanitize($a);
        $trimedB = $this->sanitize($b);

        return $this->getMatchingScore($trimedA, $trimedB);
    }

    public function sanitizeNumber($string)
    {
        return preg_replace('/\D/', '', $string);
    }

    public function isSameMoment(Agenda $a, Agenda $b)
    {
        $dateDebutA = $a->getDateDebut();
        $dateDebutB = $b->getDateDebut();

        $dateFinA = $a->getDateFin();
        $dateFinB = $b->getDateFin();

        if (false === $dateDebutA || false === $dateDebutB) {
            return false;
        }

        return $this->isSameDate($dateDebutA, $dateDebutB) && $this->isSameDate($dateFinA, $dateFinB);
    }

    public function sanitizeRue($string)
    {
        $step1 = $this->util->utf8LowerCase($string);
        $step2 = $this->util->replaceAccents($step1);
        $step3 = $this->util->deleteMultipleSpaces($step2);

        return trim($step3);
    }

    public function sanitizeHTML($string)
    {
        return $this->sanitize(strip_tags($string));
    }

    public function sanitizeVille($string)
    {
        $string = preg_replace("#-(\s*)st(\s*)-#i", 'saint', $string);

        return $this->sanitize($string);
    }

    public function sanitize($string)
    {
        return Monitor::bench('Sanitize', function () use ($string) {
            $key = 'sanitize'.$string;
            if (!$this->cache->contains($key)) {
                $step1 = $this->util->utf8LowerCase($string);
                $step2 = $this->util->replaceAccents($step1);
                $step3 = $this->util->replaceNonAlphanumericChars($step2);
                $step4 = $this->util->deleteStopWords($step3);
                $step5 = $this->util->deleteMultipleSpaces($step4);
                $step6 = trim($step5);
                $this->cache->save($key, $step6);
            }

            return $this->cache->fetch($key);
        });
    }

    private function isSameDate(\DateTime $a, \DateTime $b)
    {
        return $a->format('Y-m-d') === $b->format('Y-m-d');
    }

    private function isSameText($a, $b, $minPourcentage = 100, $nullAreSame = false)
    {
        $trimedA = $this->sanitize($a);
        $trimedB = $this->sanitize($b);

        // = strlen > 0
        if (!isset($trimedA[0]) || !isset($trimedB[0])) {
            return $nullAreSame;
        } elseif ($trimedA === $trimedB) {
            return true;
        } elseif ($minPourcentage < 100) {
            $pourcentage = 0;
            similar_text($a, $b, $pourcentage);

            return $pourcentage >= $minPourcentage;
        }

        return false;
    }
}
