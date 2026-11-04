<?php

namespace Concerto\PanelBundle\Repository;

/**
 * TestNodeConnectionRepository
 */
class TestNodeConnectionRepository extends AEntityRepository
{

    public function findByNodes($sourceNode, $destinationNode)
    {
        return $this->getEntityManager()->getRepository(\Concerto\PanelBundle\Entity\TestNodeConnection::class)->findBy(array("sourceNode" => $sourceNode, "destinationNode" => $destinationNode));
    }
}
