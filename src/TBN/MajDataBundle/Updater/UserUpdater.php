<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 17/12/2016
 * Time: 14:28.
 */

namespace TBN\MajDataBundle\Updater;

use Doctrine\ORM\EntityManager;
use TBN\MajDataBundle\Handler\UserHandler;
use TBN\MajDataBundle\Utils\Monitor;
use TBN\SocialBundle\Social\FacebookAdmin;
use TBN\UserBundle\Entity\User;

class UserUpdater extends Updater
{
    /**
     * @var \TBN\MajDataBundle\Handler\UserHandler
     */
    protected $userHandler;

    public function __construct(EntityManager $entityManager, FacebookAdmin $facebookAdmin, UserHandler $userHandler)
    {
        parent::__construct($entityManager, $facebookAdmin);
        $this->userHandler = $userHandler;
    }

    public function update()
    {
        $repo  = $this->entityManager->getRepository('TBNUserBundle:User');
        $fbIds = $repo->getUserFbIds();
        $count = count($fbIds);

        $fbStats = $this->facebookAdmin->getUserStatsFromIds($fbIds);
        unset($fbIds);

        $nbBatchs = ceil($count / self::PAGINATION_SIZE);
        Monitor::createProgressBar($nbBatchs);

        for ($i = 0; $i < $nbBatchs; ++$i) {
            $users = $repo->getUsersWithInfo($i, self::PAGINATION_SIZE);
            $this->doUpdate($users, $fbStats);
            $this->doFlush();
            Monitor::advanceProgressBar();
        }
    }

    protected function doUpdate(array $users, array $fbStats)
    {
        $downloadUrls = [];
        foreach ($users as $user) {
            /**
             * @var User
             */
            $userInfo = $user->getInfo();
            $imageURL = null;
            if ($userInfo && $userInfo->getFacebookId() && isset($fbStats[$userInfo->getFacebookId()])) {
                $imageURL = $fbStats[$userInfo->getFacebookId()]['url'];
            }

            if ($this->userHandler->hasToDownloadImage($imageURL, $user)) {
                $userInfo->setFacebookProfilePicture($imageURL);
                $downloadUrls[$user->getId()] = $imageURL;
            }
        }

        $responses = $this->downloadUrls($downloadUrls);
        foreach ($users as $user) {
            if (isset($responses[$user->getId()])) {
                $this->userHandler->uploadFile($user, $responses[$user->getId()]);
            }
        }
    }

    protected function doFlush()
    {
        $this->entityManager->flush();
        $this->entityManager->clear(User::class);
    }
}
