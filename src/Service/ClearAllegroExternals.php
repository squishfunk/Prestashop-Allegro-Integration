<?php
namespace Allegro\Service;

use Allegro\Entity\AllegroExternal;
use Doctrine\Persistence\ObjectManager;
use Image;
use Validate;

class ClearAllegroExternals implements ImportServiceInterface
{

    /**
     * @var ObjectManager
     */
    private ObjectManager $entityManager;


    /**
     * ImportAllegroCategories constructor.
     */
    public function __construct(ObjectManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    function run(): void
    {
        set_time_limit(1 * 60 * 60 * 60);
        $imgExternals = $this->entityManager->getRepository(AllegroExternal::class)->findAll();

        if($imgExternals){
            foreach($imgExternals as $imgExternal){
                if($imgExternal->getModelName() == 'Image'){
                    $imgId = $imgExternal->getInternalId();
                    $image = new Image($imgId);
                    if (!Validate::isLoadedObject($image)) {
                        $this->entityManager->remove($imgExternal);
                    }
                }
            }
            $this->entityManager->flush();
        }
    }
}