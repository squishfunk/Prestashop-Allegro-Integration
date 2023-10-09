<?php
namespace Allegro\Service;

use Allegro\Singleton\AllegroSingleton;

class ImportAllegroParameters implements ImportServiceInterface
{

    function run(): void
    {
        $api = AllegroSingleton::getInstance();
        $essa = json_decode($api->sale()->offers()->get()->getBody()->getContents(), true);

        dd($essa);
        // TODO: Implement run() method.
    }


}