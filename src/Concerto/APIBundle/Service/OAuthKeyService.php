<?php

namespace Concerto\APIBundle\Service;

/**
 * Provides the RSA key pair league/oauth2-server uses to sign and verify access tokens.
 * Keys are generated once (by concerto:setup) into a persistent directory.
 */
class OAuthKeyService
{
    const PRIVATE_KEY = 'private.key';
    const PUBLIC_KEY = 'public.key';
    const KEY_BITS = 2048;

    private $keyDir;

    public function __construct($keyDir)
    {
        $this->keyDir = rtrim($keyDir, '/');
    }

    public function getPrivateKeyPath()
    {
        return $this->keyDir . '/' . self::PRIVATE_KEY;
    }

    public function getPublicKeyPath()
    {
        return $this->keyDir . '/' . self::PUBLIC_KEY;
    }

    /**
     * @return bool true when a new key pair was generated
     */
    public function ensureKeys()
    {
        if (is_file($this->getPrivateKeyPath()) && is_file($this->getPublicKeyPath())) {
            return false;
        }

        if (!is_dir($this->keyDir) && !mkdir($this->keyDir, 0700, true) && !is_dir($this->keyDir)) {
            throw new \RuntimeException("Unable to create OAuth key directory {$this->keyDir}");
        }

        $key = openssl_pkey_new(array(
            'private_key_bits' => self::KEY_BITS,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ));
        if ($key === false || !openssl_pkey_export($key, $privatePem)) {
            throw new \RuntimeException('Unable to generate OAuth private key: ' . openssl_error_string());
        }
        $publicPem = openssl_pkey_get_details($key)['key'];

        $this->writeFile($this->getPrivateKeyPath(), $privatePem, 0600);
        $this->writeFile($this->getPublicKeyPath(), $publicPem, 0644);

        return true;
    }

    private function writeFile($path, $content, $mode)
    {
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $previousUmask = umask(0077);
        try {
            if (file_put_contents($tmp, $content) === false) {
                throw new \RuntimeException("Unable to write $path");
            }
        } finally {
            umask($previousUmask);
        }
        chmod($tmp, $mode);
        rename($tmp, $path);
    }
}
