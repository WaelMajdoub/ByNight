<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 03/05/2017
 * Time: 19:13
 */

namespace AppBundle\Request\ParamConverter;


use Doctrine\Common\Persistence\AbstractManagerRegistry;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Entity\Site;

class CityConverter implements ParamConverterInterface
{
    private $registry;

    public function __construct(AbstractManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function apply(Request $request, ParamConverter $configuration)
    {
        $city = $request->attributes->get('city');

        if (null === $city && ! $configuration->isOptional()) {
            throw new \InvalidArgumentException('Route attribute is missing');
        }elseif(null === $city) {
            return;
        }

        $city = $this
            ->registry
            ->getManager()
            ->getRepository("AppBundle:Site")
            ->findOneBy(['subdomain' => $city]);

        $request->attributes->set($configuration->getName(), $city);
    }

    public function supports(ParamConverter $configuration)
    {
        return $configuration->getClass() === Site::class;
    }
}