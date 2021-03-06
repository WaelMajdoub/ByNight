<?php

namespace TBN\MajDataBundle\Utils;

use TBN\AgendaBundle\Entity\Agenda;
use TBN\AgendaBundle\Entity\Place;

/**
 * Description of Merger.
 *
 * @author Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 */
class Merger
{
    const MERGE_LEFT                    = 'do_merge_left';
    const MERGE_RIGHT                   = 'do_merge_right';
    const MERGE_MAX                     = 'do_merge_max';
    const MERGE_RIGHT_IF_DIFFERENT      = 'do_merge_right_if_different';
    const MERGE_RIGHT_IF_DATE_DIFFERENT = 'do_merge_right_if_date_different';
    const FORCE_MERGE_LEFT              = 'do_force_merge_left';
    const FORCE_MERGE_RIGHT             = 'do_force_merge_right';
    const DEFAULT_MERGE                 = self::MERGE_RIGHT;

    /**
     * @var Comparator
     */
    private $comparator;

    public function __construct(Comparator $comparator)
    {
        $this->comparator = $comparator;
    }

    public function mergeEvent(Agenda $a = null, Agenda $b = null)
    {
        return $this->merge($a, $b, [
            'id' => self::FORCE_MERGE_LEFT,
//            'slug' => self::FORCE_MERGE_LEFT,
            'nom',
            'date_debut' => self::MERGE_RIGHT_IF_DATE_DIFFERENT,
            'date_fin'   => self::MERGE_RIGHT_IF_DATE_DIFFERENT,
            'descriptif',
            'horaires',
            'modification_derniere_minute',
            'type_manifestation',
            'categorie_manifestation',
            'theme_manifestation',
            'station_metro_tram',
            'reservation_telephone',
            'reservation_email',
            'reservation_internet',
            'tarif',
            'url',
            'facebook_event_id',
            'facebook_owner_id',
            'fb_participations' => self::MERGE_MAX,
            'fb_interets'       => self::MERGE_MAX,
            'fb_post_id',
            'fb_post_system_id',
            'tweet_post_id',
            'tweet_post_system_id',
            'google_post_id',
            'google_system_post_id',
            'source',
            'fb_date_modification' => self::MERGE_RIGHT_IF_DIFFERENT,
            'place',
            'user' => self::FORCE_MERGE_LEFT,
            'path',
            'system_path',
            'file' => self::MERGE_LEFT,
            'from_data',
            'reject',
        ]);
    }

    public function mergePlace(Place $a = null, Place $b = null)
    {
        return $this->merge($a, $b, [
            'id' => self::FORCE_MERGE_LEFT,
//            'slug' => self::FORCE_MERGE_LEFT,
            'nom',
            'latitude'  => self::MERGE_LEFT,
            'longitude' => self::MERGE_LEFT,
            'rue'       => self::MERGE_LEFT,
            'url',
            'ville'       => self::MERGE_LEFT,
            'codePostal'  => self::MERGE_LEFT,
            'facebook_id' => self::MERGE_LEFT,
            'reject',
        ]);
    }

    /**
     * Merge les champs de b dans a s'ils sont jugés plus pertinents.
     *
     * @param \stdClass $a
     * @param \stdClass $b
     * @param array     $fields
     *
     * @return \stdClass
     */
    private function merge($a, $b, array $fields)
    {
        //Un ou les deux est nul, pas la peine de merger
        if (null === $a || null === $b) {
            return $a ?: $b; //Retourne l'objet non nul s'il existe
        }

        if ($a === $b) {
            return $a;
        }

        foreach ($fields as $type => $field) {
            if (is_numeric($type)) {
                $type = self::DEFAULT_MERGE;
            } else {
                $oldField = $field;
                $field    = $type;
                $type     = $oldField;
            }

            $getter = 'get'.$this->skakeToCamel($field);
            $setter = 'set'.$this->skakeToCamel($field);

            $valueA = $a->$getter();
            $valueB = $b->$getter();
            $value  = $this->getBestContent($valueA, $valueB, $type);

            $a->$setter($value);
        }

        return $a;
    }

    protected function getBestContent($valueA, $valueB, $mergeType)
    {
        if (is_callable($mergeType)) {
            return call_user_func($mergeType, $valueA, $valueB);
        }

        switch ($mergeType) {
            case self::MERGE_RIGHT:
                return $valueB ?: $valueA;
            case self::MERGE_LEFT:
                return $valueA ?: $valueB;
            case self::FORCE_MERGE_LEFT:
                return $valueA;
            case self::FORCE_MERGE_RIGHT:
                return $valueB;
            case self::MERGE_RIGHT_IF_DIFFERENT:
                return $valueA != $valueB ? $valueB : $valueA;
            case self::MERGE_RIGHT_IF_DATE_DIFFERENT:
                if ($valueA && $valueB) {
                    return $valueA->format('Y-m-d') != $valueB->format('Y-m-d') ? $valueB : $valueA;
                }

                return $this->getBestContent($valueA, $valueB, self::MERGE_RIGHT_IF_DIFFERENT);
            case self::MERGE_MAX:
                return max($valueA, $valueB);
        }

        if (is_bool($valueA)) {
            return $valueA;
        }

        if (is_object($valueA) || is_object($valueB)) {
            return $valueA ?: $valueB;
        }

        $compareA = $this->comparator->sanitize($valueA);

        return isset($compareA[0]) ? ($valueA ?: null) : ($valueB ?: null);
    }

    private function skakeToCamel($str)
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $str)));
    }
}
