<?php

namespace Allegro\Controller;

use Allegro\Service\ImportAllegroCategories;
use Allegro\Service\ImportAllegroParameters;
use Allegro\Service\ImportProduct;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;

class AllegroCronController extends FrameworkBundleAdminController
{

    function importProductsFromAllegro(){
        $importer = new ImportProduct();
        $importer->run();
        echo 'done';
        exit;
    }



    function test(){
        (new ImportAllegroCategories($this->getDoctrine()->getManager()))->run();
        exit;
    }
}
