<?php

namespace Concerto\PanelBundle\EventSubscriber;

use Concerto\PanelBundle\Entity\AEntity;
use Concerto\PanelBundle\Entity\ATopEntity;
use Concerto\PanelBundle\Entity\TestNode;
use Concerto\PanelBundle\Entity\TestNodeConnection;
use Concerto\PanelBundle\Entity\TestNodePort;
use Concerto\PanelBundle\Entity\TestVariable;
use Concerto\PanelBundle\Entity\TestWizardParam;
use Concerto\PanelBundle\Entity\TestWizardStep;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Doctrine listener (see doctrine.event_listener tags in services.yml): keeps the owning
 * top entity's update metadata current when its child entities change.
 */
class EntitySubscriber
{
    private $securityTokenStorage;
    private $topEntitiesToUpdate;

    public function __construct(TokenStorageInterface $securityTokenStorage)
    {
        $this->securityTokenStorage = $securityTokenStorage;
        $this->topEntitiesToUpdate = [];
    }

    public function preUpdate(PreUpdateEventArgs $args)
    {
        $this->addTopEntityForUpdate($args->getObject());
    }

    public function prePersist(PrePersistEventArgs $args)
    {
        $this->addTopEntityForUpdate($args->getObject());
    }

    public function preRemove(PreRemoveEventArgs $args)
    {
        $this->addTopEntityForUpdate($args->getObject());
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        if (!empty($this->topEntitiesToUpdate)) {
            $entityManager = $args->getObjectManager();
            $user = null;
            $token = $this->securityTokenStorage->getToken();
            if ($token !== null) $user = $token->getUser();
            $entitiesToFlush = [];
            foreach ($this->topEntitiesToUpdate as $topEntity) {
                if ($entityManager->contains($topEntity)) {
                    $topEntity->updateTopEntity($user);
                    array_push($entitiesToFlush, $topEntity);
                }
            }
            $this->topEntitiesToUpdate = [];
            if (!empty($entitiesToFlush)) {
                $entityManager->flush();
            }
        }
    }

    private function addTopEntityForUpdate($entity)
    {
        $childEntity = $entity instanceof TestNode ||
            $entity instanceof TestNodeConnection ||
            $entity instanceof TestNodePort ||
            $entity instanceof TestVariable ||
            $entity instanceof TestWizardParam ||
            $entity instanceof TestWizardStep;

        if ($childEntity && !in_array($entity->getTopEntity(), $this->topEntitiesToUpdate)) {
            array_push($this->topEntitiesToUpdate, $entity->getTopEntity());
        }
    }
}