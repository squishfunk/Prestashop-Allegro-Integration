<?php

namespace Allegro\Oauth;

use Imper86\PhpAllegroApi\Model\Token;
use Imper86\PhpAllegroApi\Model\TokenInterface;
use Configuration;

class DbTokenRepository implements \Imper86\PhpAllegroApi\Oauth\TokenRepositoryInterface
{
    public function load(): ?TokenInterface
    {
        $json = Configuration::get('ALLEGRO_OAUTH_TOKEN_SERIALIZED') ? json_decode(Configuration::get('ALLEGRO_OAUTH_TOKEN_SERIALIZED'), true) : null;

        if (!$json) {
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException(json_last_error_msg(), json_last_error());
            }

            throw new \RuntimeException('Couldn\'t fetch token from database');
        }

        return new Token($json);
    }

    public function save(TokenInterface $token): void
    {
        Configuration::updateValue('ALLEGRO_OAUTH_TOKEN_SERIALIZED', json_encode($token->serialize()));
    }
}