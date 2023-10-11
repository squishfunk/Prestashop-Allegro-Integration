<?php
namespace Allegro\Service;

use Allegro\Entity\AllegroCategory;
use Allegro\Singleton\AllegroSingleton;
use Doctrine\Persistence\ObjectManager;
use Imper86\PhpAllegroApi\AllegroApi;

class ImportAllegroCategories implements ImportServiceInterface
{

    /**
     * @var ObjectManager
     */
    private ObjectManager $entityManager;

    /**
     * @var AllegroApi
     */
    private AllegroApi $api;

    /**
     * @var array
     */
    private $processIds;


    /**
     * ImportAllegroCategories constructor.
     */
    public function __construct(ObjectManager $entityManager)
    {
        $this->api = AllegroSingleton::getInstance();
        $this->entityManager = $entityManager;
    }

    function run(): void
    {
        set_time_limit(1 * 60 * 60 * 60);

        $categories = json_decode($this->api->sale()->categories()->get()->getBody()->getContents(), true);
        if(!isset($categories['categories'])){
            return;
        }

        $this->processCategories($categories);
        if(!empty($this->processIds)){
            $this->deleteRemovedCategories();
        }
    }

    /**
     * Główna funkcja przetwarzająca kategorie
     * @param $categories
     */
    private function processCategories($categories){
        foreach ($categories['categories'] as $categoryData) {
            $categoryId = (int) $categoryData['id'];
            $this->processIds[] = $categoryId;

            $category = $this->entityManager->getRepository(AllegroCategory::class)->findOneBy([
                'allegroId' => $categoryId,
            ]);

            if (!$category) {
                $this->createAllegroCategory($categoryData);
            }

            $subcategoriesData = json_decode($this->api->sale()->categories()->get(null, ['parent.id' => $categoryId])->getBody()->getContents(), true);
            if (!empty($subcategoriesData['categories'])) {
                $this->processCategories($subcategoriesData);
            }
        }
    }

    /**
     *  Funkcja usuwa wszystkie pozostałe kategorie
     */
    private function deleteRemovedCategories(){
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder
            ->select('AllegroCategory')
            ->from(AllegroCategory::class, 'AllegroCategory')
            ->where($queryBuilder->expr()->notIn('AllegroCategory.id', $this->processIds));
        $allegroCategoriesToDelete = $queryBuilder->getQuery()->getResult();

        foreach($allegroCategoriesToDelete as $allegroCategoryToDelete){
            $this->entityManager->remove($allegroCategoryToDelete);
        }
        $this->entityManager->flush();
    }


    /**
     * Funkcja tworzy AllegroCategory z danych z API
     * @param $categoryData
     */
    private function createAllegroCategory($categoryData){
        $category = new AllegroCategory();
        $category->setAllegroId((int)$categoryData['id']);
        if(isset($categoryData['parent']['id'])){
            $category->setParentId((int)$categoryData['parent']['id']);
        }
        $category->setName($categoryData['name']);
        $category->setLeaf($categoryData['name']);
        $category->setOptions(json_encode($categoryData['options']));

        $this->entityManager->persist($category);
        $this->entityManager->flush();
    }


}