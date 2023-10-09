<?php
namespace Allegro\Service;

use Allegro\Singleton\AllegroSingleton;
use Doctrine\ORM\EntityManagerInterface;

class ImportAllegroCategories implements ImportServiceInterface
{

    private EntityManagerInterface $entityManager;


    /**
     * ImportAllegroCategories constructor.
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    function run(): void
    {
        $api = AllegroSingleton::getInstance();
        $categories = json_decode($api->sale()->categories()->get()->getBody()->getContents(), true);
        if(!isset($categories['categories'])){
            return;
        }

        foreach($categories['categories'] as $category){
            $subcategories = json_decode($api->sale()->categories()->get(null, ['parent.id' => $category['id']])->getBody()->getContents(), true);
            if(empty($subcategories['categories'])){
                continue;
            }

            $this->entityManager->getRepository();
            $allegroAccountForUpdate = $em->getRepository(AllegroAccount::class)->find($id);

        }

    }

    function getCategories(){

    }

}