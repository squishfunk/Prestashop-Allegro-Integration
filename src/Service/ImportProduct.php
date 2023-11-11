<?php
namespace Allegro\Service;

use Allegro\Entity\AllegroExternal;
use Allegro\Singleton\AllegroSingleton;
use Category;
use Configuration;
use Context;
use Db;
use Doctrine\Persistence\ObjectManager;
use Hook;
use Image;
use ImageManager;
use ImageType;
use Imper86\PhpAllegroApi\AllegroApi;
use Imper86\PhpAllegroApi\Enum\ContentType;
use Language;
use Shop;
use StockAvailable;
use Product;
use Tools;

class ImportProduct implements ImportServiceInterface
{

    /**
     * @var AllegroApi
     */
    private AllegroApi $api;

    /**
     * @var ObjectManager
     */
    private ObjectManager $entityManager;

    /**
     * @var bool
     */
    private bool $isTest;

    /**
     * ImportProduct constructor.
     */
    public function __construct(ObjectManager $entityManager, bool $isTest)
    {
        $this->api = AllegroSingleton::getInstance();
        $this->entityManager = $entityManager;
        $this->isTest = $isTest;
    }

    public function run(): void{
        /* 24h */
        set_time_limit(1 * 60 * 60 * 24);

        (new ClearAllegroExternals($this->entityManager))->run();

        $actualOffset = 0;
        $pageLimit = 20;
        /* Pobranie ofert */
        do{
            $offers = json_decode($this->api->sale()->offers()->get(null, ['offset' => $actualOffset, 'limit' => $pageLimit])->getBody()->getContents(), true);
            $this->processAllegroOffers($offers['offers']);
            $actualOffset++;
            if($this->isTest){
                break;
            }
        }while(count($offers['offers']) == $pageLimit);
    }

    /**
     * @param string $search
     * @return false|string|null
     */
    private function getProductIdByProductReference(string $search)
    {
        return Db::getInstance()->getValue('
        SELECT id_product
        FROM '._DB_PREFIX_.'product
        WHERE `reference` = "'.pSQL($search).'"
        ');
    }

    /**
     * @param $ean13    - Product EAN13
     * @param $ref      - Product reference
     * @param $name     - Product name
     * @param $qty      - Product quantity
     * @param $text     - Product description
     * @param $features - Product features (array)
     * @param $price    - Product price
     * @param $imgUrls  - Product images
     * @param $catDef   - Product default category
     * @param $catAll   - All categories for product (array)
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    function addOrUpdateProduct($ean13, $ref, $name, $qty, $text, $features, $price, $imgUrls, $catDef, $catAll) {
        $new = true;
        if($productId = $this->getProductIdByProductReference($ref)){
            $product = new Product($productId);
        }else{
            $new = false;
            $product = new Product();
        }

        $product->ean13 = $ean13;
        $product->reference = $ref;
        $product->name = $name;
        $product->description = htmlspecialchars($text);
        $product->id_category_default = $catDef;
        $product->redirect_type = '301';
        $product->price = number_format($price, 6, '.', '');
        $product->minimal_quantity = 1;
        $product->show_price = 1;
        $product->on_sale = 0;
        $product->online_only = 0;
        $product->meta_description = '';
        $product->link_rewrite = Tools::str2url($name);
        $product->save();

        if($this->isTest){
            dump($product);
        }

        StockAvailable::setQuantity($product->id, null, $qty);
        if(!empty($catAll)){
            $product->addToCategories($catAll);
        }

        if($new){
            $this->createOrUpdateProductFeatures($product->id, $features);
            $this->createOrUpdateProductPhotos($product->id, $imgUrls);
        }

        if($new){
            echo 'Product added successfully (ID: ' . $product->id . ') <br>';
        }else{
            echo 'Product updated successfully (ID: ' . $product->id . ') <br>';
        }
    }

    private function createMultiLangField($field) {
        $res = array();
        foreach (Language::getIDs() as $id_lang) {
            $res[$id_lang] = $field;
        }
        return $res;
    }

    private function uploadImage($id_entity, $id_image = null, string $imgUrl) {
        $tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'ps_import');
        $watermark_types = explode(',', Configuration::get('WATERMARK_TYPES'));
        $image_obj = new Image((int)$id_image);
        $path = $image_obj->getPathForCreation();

        // Evaluate the memory required to resize the image: if it's too big we can't resize it.
        if (!ImageManager::checkImageMemoryLimit($imgUrl)) {
            return false;
        }
        if (@copy($imgUrl, $tmpfile)) {
            ImageManager::resize($tmpfile, $path . '.jpg');
            $images_types = ImageType::getImagesTypes('products');
            foreach ($images_types as $image_type) {
                ImageManager::resize($tmpfile, $path . '-' . stripslashes($image_type['name']) . '.jpg', $image_type['width'], $image_type['height']);
                if (in_array($image_type['id_image_type'], $watermark_types)) {
                    Hook::exec('actionWatermark', array('id_image' => $id_image, 'id_product' => $id_entity));
                }
            }
        } else {
            unlink($tmpfile);
            return false;
        }
        unlink($tmpfile);

        return true;
    }

    private function getDataFromAllegroProductParameters(array $offer): array
    {
        $ean = '';
        $features = [];

        foreach($offer['productSet'][0]['product']['parameters'] as $parameter){
            if(empty($parameter['values'])){
                continue;
            }

            /* ean */
            if(str_contains($parameter['name'], 'EAN')){
                $ean = implode('', $parameter['values']);
                continue;
            }

            $features[] = [
                'name' => $parameter['name'],
                'value' => implode(', ', $parameter['values']),
            ];
        }

        return [$ean, $features];
    }

    /**
     * @param string $productId
     * @param array $featuresToSave
     */
    private function createOrUpdateProductFeatures(string $productId, array $featuresToSave): void
    {
        foreach ($featuresToSave as $feature) {
            $attributeName = $feature['name'];
            $attributeValue = $feature['value'];


            // 1. Check if 'feature name' exist already in database
            $FeatureNameId = Db::getInstance()->getValue('SELECT id_feature FROM ' . _DB_PREFIX_ . 'feature_lang WHERE name = "' . pSQL($attributeName) . '"');
            // If 'feature name' does not exist, insert new.
            if (empty($FeatureNameId)) {
                Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . 'feature` (`id_feature`,`position`) VALUES (0, 0)');
                $FeatureNameId = Db::getInstance()->Insert_ID(); // Get id of "feature name" for insert in product
                Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . 'feature_shop` (`id_feature`,`id_shop`) VALUES (' . $FeatureNameId . ', 1)');
                Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . 'feature_lang` (`id_feature`,`id_lang`, `name`) VALUES (' . $FeatureNameId . ', ' . Context::getContext()->language->id . ', "' . pSQL($attributeName) . '")');
            }

            // 1. Check if 'feature value name' exist already in database
            $FeatureValueId = Db::getInstance()->getValue('SELECT id_feature_value FROM ' . _DB_PREFIX_ . 'feature_value WHERE id_feature_value IN (SELECT id_feature_value FROM `' . _DB_PREFIX_ . 'feature_value_lang` WHERE value = "' . pSQL($attributeValue) . '") AND id_feature = ' . $FeatureNameId);
            // If 'feature value name' does not exist, insert new.
            if (empty($FeatureValueId)) {
                Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . 'feature_value` (`id_feature_value`,`id_feature`,`custom`) VALUES (0, ' . $FeatureNameId . ', 0)');
                $FeatureValueId = Db::getInstance()->Insert_ID();
                Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . 'feature_value_lang` (`id_feature_value`,`id_lang`,`value`) VALUES (' . $FeatureValueId . ', ' . Context::getContext()->language->id . ', "' . pSQL($attributeValue) . '")');
            }

            /* Jeśli już przypisany to pomijam */
            $exist = Db::getInstance()->getValue('SELECT id_feature_value FROM ' . _DB_PREFIX_ . 'feature_product WHERE `id_feature` = ' . $FeatureNameId . ' AND `id_product` = ' . $productId . ' AND `id_feature_value` = ' . $FeatureValueId);
            if (empty($exist)) {
                Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . 'feature_product` (`id_feature`, `id_product`, `id_feature_value`) VALUES (' . $FeatureNameId . ', ' . $productId . ', ' . $FeatureValueId . ')');
            }
        }
    }

    /**
     * @param string $productId
     * @param array $imgUrls
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function createOrUpdateProductPhotos(string $productId, array $imgUrls): void
    {
        $product = new Product($productId);
        $productImages = $product->getImages(1);
        $productExistingImages = [];

        $coverImageId = 0;
        foreach($productImages as $productImage){
            if($productImage['cover']){
                $coverImageId = $productImage['id_image'];
            }
            $productExistingImages[$productImage['id_image']] = $productImage['id_image'];
        }

        $shops = Shop::getShops(true, null, true);

        foreach(array_values($imgUrls) as $index => $imgUrl){
            $photo_md5 = md5(file_get_contents($imgUrl));
            $existingImg = $this->entityManager->getRepository(AllegroExternal::class)->findOneBy(['externalId' => $photo_md5, 'modelName' => 'Image']);
            if($existingImg){
                if(isset($productExistingImages[$existingImg->getInternalId()])){
                    unset($productExistingImages[$existingImg->getInternalId()]);
                }
                continue;
            }else{
                $image = new Image();
            }
            $image->id_product = $productId;
            $image->position = Image::getHighestPosition($productId) + 1;
            if($index == 0 && !$coverImageId){
                $image->cover = true;
            }else{
                $image->cover = false;
            }
            if (($image->validateFields(false, true)) === true && ($image->validateFieldsLang(false, true)) === true && $image->add()) {
                $image->associateTo($shops);
                if ($this->uploadImage($productId, $image->id, $imgUrl)) {

                    $allegroExternal = new AllegroExternal();
                    $allegroExternal->setModelName("Image");
                    $allegroExternal->setExternalId($photo_md5);
                    $allegroExternal->setExternalName($imgUrl);
                    $allegroExternal->setInternalId($image->id);

                    $this->entityManager->persist($allegroExternal);
                    $this->entityManager->flush();
                }else{
                    $image->delete();
                }
            }
        }

        /* Delete old of images */
        if(!empty($productExistingImages)){
            foreach($productExistingImages as $productExistingImageId){
                if($coverImageId == $productExistingImageId){
                    $productImages = $product->getImages(1);
                    if($productImages){
                        $imgId = array_shift($productImages)['id_image'];
                        $img = new Image($imgId);
                        $img->cover = true;
                        $img->update();
                    }
                }
                $imgToDelete = new Image($productExistingImageId);
                $imgToDelete->delete();
            }
        }
    }


    /**
     * Funkcja generuje opis produktu ze struktury opisu allegro
     * @param $allegroDescription
     * @return string
     */
    private function getDescriptionFromAllegroProduct($allegroDescription): string
    {
        $description = '';
        if(isset($allegroDescription['sections'])){
            foreach($allegroDescription['sections'] as $descriptionSection){
                foreach($descriptionSection['items'] as $item){
                    if($item['type'] == 'TEXT'){
                        $description .= $item['content'];
                    }elseif($item['type'] == 'IMAGE'){
                        /* TODO */
                        /* $x .= $item['url']; */
                        continue;
                    }
                }
            }
        }
        return $description;
    }

    /**
     * Funkcja zwraca najwyższy poziom kategorii
     * @param $categoryId
     * @param bool $import
     */
    private function getMainAllegroCategoryIdAndImport($categoryId, $import = false)
    {
        $returnCategoryId = 0;
        $categoryAllegro = null;
        while(true){
            $categoryAllegro = json_decode($this->api->sale()->categories()->get($categoryId)->getBody()->getContents(), true);
            if(!isset($categoryAllegro['parent']['id'])){
                $returnCategoryId = $categoryId;
                break;
            }else{
                $categoryId = $categoryAllegro['parent']['id'];
            }
        }

        /* w tym momencie $returnCategoryId ma najwyższą kategorię */
        if($import && $categoryAllegro){
            if($psCategoryId = $this->getCategoryIdByCategoryName($categoryAllegro['name'])){
                $category = new Category($psCategoryId);
            }else{
                $category = new Category();
            }

            $category->active       = 1;
            $category->id_parent    = 0;
            $category->name         = $this->createMultiLangField($categoryAllegro['name']);
            $category->link_rewrite = $this->createMultiLangField(Tools::str2url($categoryAllegro['name']));

            try {
                $category->save();
            } catch (\PrestaShopDatabaseException | \PrestaShopException $e) {
                dd($e);
            }
            return $category->id;
        }
        return $returnCategoryId;
    }

    /**
     * @param string $search
     * @return false|string|null
     */
    private function getCategoryIdByCategoryName(string $search,  $id_lang = 1, $id_shop = 1)
    {
        $return = Db::getInstance()->getValue('
        SELECT id_category
        FROM '._DB_PREFIX_.'category_lang
        WHERE `name` LIKE "%'.pSQL($search).'%"
        ');
        return $return;
    }

    /**
     * @param array $offers
     */
    public function processAllegroOffers($offers): void
    {
        foreach ($offers as $offer_data) {
            /* Pobranie szczegółów oferty */
            $offer = json_decode($this->api->sale()->productOffers()->get($offer_data['id'], ContentType::VND_PUBLIC_V1)->getBody()->getContents(), true);

            /* Jeśli oferta bez produktyzacji skip */
            if (!isset($offer['productSet'][0]['product']) || !isset($offer['external']['id']) || $offer['publication']['status'] == 'INACTIVE') {
                continue;
            }

            list($ean,
                $productRef,
                $productName,
                $productQuantity,
                $productDescription,
                $features,
                $productPrice,
                $urlList,
                $mainCategoryId
                ) = $this->getDataFromOfferToBuildProduct($offer);

            try {
                $this->addOrUpdateProduct(
                    $ean,
                    $productRef,
                    $productName,
                    $productQuantity,
                    $productDescription,
                    $features,
                    $productPrice,
                    $urlList,
                    $mainCategoryId,
                    array()
                );
            } catch (\PrestaShopDatabaseException | \PrestaShopException $e) {
                dump($e);
            }
        }
    }

    /**
     * @param mixed $offer
     * @return array
     */
    public function getDataFromOfferToBuildProduct(array $offer): array
    {
        [$ean, $features] = $this->getDataFromAllegroProductParameters($offer);

        if (/* TODO Ustawienie czy opis pobierać z katalogu allegro czy oferty */ true) {
            $productName = $offer['name'];
            $productDescription = $this->getDescriptionFromAllegroProduct($offer['description']);
            $urlList = $offer['images'];
        }
        if ((empty($productDescription) || empty($urlList)) && isset($offer['productSet'][0]['product']['id'])) {
            $product = json_decode($this->api->sale()->products()->get($offer['productSet'][0]['product']['id'])->getBody()->getContents(), true);
//                    $productName = $product['name'];
            if (empty($productDescription)) {
                $productDescription = $this->getDescriptionFromAllegroProduct($product['description']);
            }
            if (empty($urlList)) {
                $urlList = array_map(fn($item) => $item['url'], $product['images']);
            }
        }

        if(!isset($offer['sellingMode']['price']['amount'])){
            dump($offer);
            exit;
        }

        $productQuantity = $offer['stock']['available'];
        $productPrice = $offer['sellingMode']['price']['amount'];
        $productRef = $offer['external']['id'];

        $mainCategoryId = $this->getMainAllegroCategoryIdAndImport($offer['category']['id'], true);

        return array(
            $ean,
            $productRef,
            $productName,
            $productQuantity,
            $productDescription,
            $features,
            $productPrice,
            $urlList,
            $mainCategoryId);
    }
}