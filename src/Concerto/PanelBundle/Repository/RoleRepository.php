<?php

namespace Concerto\PanelBundle\Repository;

/**
 * RoleRepository
 */
class RoleRepository extends AEntityRepository {

    public function findOneByRole($role) {
        return $this->getEntityManager()->getRepository(\Concerto\PanelBundle\Entity\Role::class)->findOneBy(array("role" => $role));
    }

}
