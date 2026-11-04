<?php

namespace Tests\Concerto\PanelBundle\Controller\FunctionalTests;

use Tests\Concerto\PanelBundle\AFunctionalTest;

/**
 * Contract for Google Authenticator two-factor login on the admin panel.
 */
class TwoFactorLoginTest extends AFunctionalTest
{
    // Base32 for "12345678901234567890", the RFC 6238 test key.
    const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    protected function setUp(): void
    {
        parent::setUp();
        // User::getGoogleAuthenticatorUsername() reads this global, which PHP-FPM always sets.
        $_SERVER['HTTP_HOST'] = 'localhost';
        $this->setTwoFactor(true, self::SECRET);
    }

    protected function tearDown(): void
    {
        $this->setTwoFactor(false, null);
        unset($_SERVER['HTTP_HOST']);
        parent::tearDown();
    }

    private function setTwoFactor($enabled, $secret)
    {
        self::$entityManager->createQuery(
            'UPDATE Concerto\PanelBundle\Entity\User u SET u.googleAuthenticatorEnabled = :enabled, u.googleAuthenticatorSecret = :secret, u.lockedUntil = NULL, u.failedAuthenticationStreak = 0'
        )->execute(array('enabled' => $enabled, 'secret' => $secret));
        self::$entityManager->clear();
    }

    private static function totp($timestamp)
    {
        $key = '';
        $bits = '';
        foreach (str_split(self::SECRET) as $char) {
            $bits .= str_pad(decbin(strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', $char)), 5, '0', STR_PAD_LEFT);
        }
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $key .= chr(bindec($byte));
            }
        }
        $hash = hash_hmac('sha1', pack('J', intdiv($timestamp, 30)), $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7fffffff;
        return str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private static function path($response)
    {
        return parse_url($response->headers->get('Location'), PHP_URL_PATH);
    }

    /**
     * Logs in with the password and returns the client, now waiting for the second factor.
     */
    private function passwordStep()
    {
        $client = static::createClient();
        $client->request('POST', '/admin/login_check', array('_username' => 'admin', '_password' => 'admin'));
        $this->assertContains(self::path($client->getResponse()), array('/admin', '/admin/2fa'), 'password must be accepted');

        $client->request('GET', '/admin');
        $this->assertSame('/admin/2fa', self::path($client->getResponse()), 'panel must stay closed until the code is entered');

        // As the browser does: follow the redirect and display the code form.
        $client->request('GET', '/admin/2fa');
        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('name="_auth_code"', $client->getResponse()->getContent());

        return $client;
    }

    public function testCorrectCodeCompletesLogin()
    {
        $client = $this->passwordStep();
        $client->request('POST', '/admin/2fa_check', array('_auth_code' => self::totp(time())));
        $this->assertSame('/admin', self::path($client->getResponse()));

        $client->request('GET', '/admin');
        $this->assertSame(200, $client->getResponse()->getStatusCode());
    }

    public function testWrongCodeIsRejected()
    {
        $client = $this->passwordStep();
        $wrong = str_pad((string)((self::totp(time()) + 1) % 1000000), 6, '0', STR_PAD_LEFT);
        $client->request('POST', '/admin/2fa_check', array('_auth_code' => $wrong));
        $this->assertSame('/admin/2fa', self::path($client->getResponse()));

        $client->request('GET', '/admin');
        $this->assertSame('/admin/2fa', self::path($client->getResponse()));
    }

    public function testExpiredCodeIsRejected()
    {
        $client = $this->passwordStep();
        $client->request('POST', '/admin/2fa_check', array('_auth_code' => self::totp(time() - 300)));
        $this->assertSame('/admin/2fa', self::path($client->getResponse()));
    }
}
