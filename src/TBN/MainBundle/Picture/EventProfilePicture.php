<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 11/10/2016
 * Time: 18:48
 */

namespace TBN\MainBundle\Picture;


use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Bundle\FrameworkBundle\Templating\Helper\AssetsHelper;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RouterInterface;
use TBN\AgendaBundle\Entity\Agenda;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class EventProfilePicture
{
    /**
     * @var CacheManager $cacheManager
     */
    private $cacheManager;

    /** @var UploaderHelper $cacheManager
     */
    private $helper;

    /** @var Router $router
     */
    private $router;

    /** @var Packages $packages
     */
    private $packages;

    public function __construct(CacheManager $cacheManager, UploaderHelper $helper, Packages $packages, RouterInterface $router)
    {
        $this->cacheManager = $cacheManager;
        $this->helper = $helper;
        $this->router = $router;
        $this->packages = $packages;
    }

    public function getOriginalPicture(Agenda $agenda) {
        if($agenda->getPath()) {
            return $this->router->generate(
                $this->helper->asset($agenda, 'file'),
                UrlGenerator::ABSOLUTE_URL
            );
        }

        if($agenda->getUrl()) {
            return $agenda->getUrl();
        }

        return $this->router->generate(
            $this->packages->getUrl('/img/empty_event.png'),
            UrlGenerator::ABSOLUTE_URL
        );
    }

    public function getPicture(Agenda $agenda, $thumb = 'thumbs_evenement') {
        if($agenda->getPath()) {
            return $this->cacheManager->getBrowserPath($this->helper->asset($agenda, 'file'), $thumb);
        }

        if($agenda->getUrl()) {
            return $agenda->getUrl();
        }

        return $this->cacheManager->getBrowserPath('img/empty_event.png', $thumb);
    }
}