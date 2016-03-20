<?php

namespace TBN\MajDataBundle\Parser;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use \DateTime;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\MainBundle\Entity\Site;
use TBN\UserBundle\Entity\SiteInfo;
use Symfony\Component\Filesystem\Filesystem;

/*
 * Classe abstraite représentant le parse des données d'un site Internet
 * Plusieurs moyens sont disponibles: Récupérer directement les données suivant
 * une URL donnée, ou bien retourner un tableau d'URLS à partir d'un flux RSS
 *
 * @author Guillaume SAINTHILLIER
 */

abstract class AgendaParser implements ParserInterface
{

    /**
     * Url du site à parser
     */
    protected $url;

    /**
     *
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var Site
     */
    protected $site;

    /**
     *
     * @var SiteInfo
     */
    protected $siteInfo;

    /**
     *
     * @var PropertyAccessorInterface
     */
    protected $propertyAccessor;

    public function __construct()
    {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $this->output = null;
        $this->site = null;
        $this->siteInfo = null;

        return $this;
    }

    public abstract function getRawAgendas();

    public function isTrustedLocation()
    {
        return true;
    }

    public function setURL($url)
    {
        $this->url = $url;

        return $this;
    }

    public function getURL()
    {
        return $this->url;
    }

    public function parse()
    {
        //Tableau des informations récoltées
        $raw = $this->getRawAgendas();

        return array_map([$this, 'arrayToAgenda'], $raw);
    }

    protected function arrayToAgenda($infos)
    {
        $agenda = new Agenda;

        foreach ($infos as $field => $value) {
            $this->propertyAccessor->setValue($agenda, $field, $value);
        }

        return $agenda;
    }


    protected function parseDate($date)
    {
        $tabMois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        return preg_replace_callback("/(.+)(\d{2}) (" . implode("|", $tabMois) . ") (\d{4})(.*)/iu",
            function ($items) use ($tabMois) {
                return $items[4] . "-" . (array_search($items[3], $tabMois) + 1) . "-" . $items[2];
            }, $date);
    }

    public function write($text)
    {
        if ($this->output !== null) {
            $this->output->write($text);
        } else {
            echo $text;
        }
    }

    public function writeln($text)
    {
        $this->write($text . "\n");
    }

    public function getOutput()
    {
        return $this->output;
    }

    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
        return $this;
    }

    public function getSite()
    {
        return $this->site;
    }

    public function getSiteInfo()
    {
        return $this->siteInfo;
    }

    public function setSite(Site $site)
    {
        $this->site = $site;
        return $this;
    }

    public function setSiteInfo(SiteInfo $siteInfo)
    {
        $this->siteInfo = $siteInfo;
        return $this;
    }
}
