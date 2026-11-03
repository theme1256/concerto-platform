<?php

namespace Concerto\APIBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use League\Bundle\OAuth2ServerBundle\Model\AbstractClient;

/**
 * OAuth2 API client (league/oauth2-server-bundle).
 *
 * The identifier is longer than the bundle's default (32) so that clients migrated from
 * FOSOAuthServerBundle keep their "<id>_<randomId>" public id. See OAuthClientMigrator.
 *
 * @ORM\Table(name="oauth2_client")
 * @ORM\Entity(repositoryClass="Concerto\APIBundle\Repository\ClientRepository")
 */
class Client extends AbstractClient implements \JsonSerializable
{
    /**
     * @var string
     *
     * @ORM\Id
     * @ORM\Column(type="string", length=128)
     */
    protected $identifier;

    public function getId()
    {
        return $this->getIdentifier();
    }

    /**
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array(
            "class_name" => "Client",
            "id" => $this->getIdentifier(),
            "fullId" => $this->getIdentifier(),
            "secret" => $this->getSecret()
        );
    }
}
