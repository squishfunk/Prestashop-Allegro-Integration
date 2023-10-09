<?php

namespace Allegro\Singleton;

use Allegro\Oauth\DbTokenRepository;
use Imper86\PhpAllegroApi\AllegroApi;
use Imper86\PhpAllegroApi\Model\Credentials;

use Configuration;
use Imper86\PhpAllegroApi\Plugin\AuthenticationPlugin;

class AllegroSingleton
{
    private static AllegroApi $instance;

    protected function __construct(){}
    protected function __clone() {}
    function __wakeup(){}

    public static function getInstance(): AllegroApi
    {
        if (!isset(self::$instance)) {
            $credentials = new Credentials(
                Configuration::get('ALLEGRO_CLIENT_ID'),
                Configuration::get('ALLEGRO_CLIENT_SECRET'),
                'http://localhost/presta/admin-dev/modules/allegro_accounts/store_allegro_token', /* TODO jakoś to zmień po prostu */
                true
            );

            $tokenRepository = new DbTokenRepository();

            self::$instance = new AllegroApi($credentials);
            self::$instance->addPlugin(new AuthenticationPlugin($tokenRepository, self::$instance->oauth()));
        }

        return self::$instance;
    }

    public static function authorize(string $return_code){
        if (self::$instance !== null) {
            $token = self::$instance->oauth()->fetchTokenWithCode($return_code);

            $tokenRepository = new DbTokenRepository();
            $tokenRepository->save($token);

            self::$instance->addPlugin(new AuthenticationPlugin($tokenRepository, self::$instance->oauth()));
        }
    }


}