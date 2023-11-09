<?php

namespace Allegro\Controller;

use Allegro\Service\ImportAllegroCategories;
use Allegro\Service\ImportAllegroParameters;
use Allegro\Service\ImportProduct;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;

class AllegroCronController extends FrameworkBundleAdminController
{

    function importProductsFromAllegro(Request $request){
        $is_test = (bool)$request->query->get('test');
        $importer = new ImportProduct($this->getDoctrine()->getManager(), $is_test);
        $importer->run();
        echo 'done';
        exit;
    }



    function test(){
        (new ImportAllegroCategories($this->getDoctrine()->getManager()))->run();
        exit;
    }
}
