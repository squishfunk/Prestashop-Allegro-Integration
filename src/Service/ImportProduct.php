<?php
namespace Allegro\Service;

use Allegro\Singleton\AllegroSingleton;
use Configuration;
use Context;
use Db;
use Hook;
use Image;
use ImageManager;
use ImageType;
use Imper86\PhpAllegroApi\Enum\ContentType;
use Language;
use Shop;
use StockAvailable;
use Product;
use Tools;

class ImportProduct implements ImportServiceInterface
{

    private mixed $offer;

    public function run(): void{
        $api = AllegroSingleton::getInstance();

        /* Pobranie ofert */
        $offers = json_decode($api->sale()->offers()->get()->getBody()->getContents(), true);

        foreach($offers['offers'] as $offer_data){
            /* Pobranie szczegółowe oferty */
            $offer = json_decode($api->sale()->productOffers()->get($offer_data['id'], ContentType::VND_PUBLIC_V1)->getBody()->getContents(), true);

            [$ean, $features] = $this->getDataFromAllegroProductParameters($offer);

            $product = json_decode($api->sale()->products()->get($offer['productSet'][0]['product']['id'])->getBody()->getContents(), true);

            $productName = $product['name'];
            $productQuantity = 1; /* TODO znaleźć gdzie jest info o ilości sztuk */
            $productDescription = $product['description']['sections'][0]['items'][1]['content']; /* TODO */
            $productPrice = $offer['sellingMode']['price']['amount'];
            $urlList = array_map(fn($item) => $item['url'], $product['images']);

            try {
                $this->addOrUpdateProduct(
                    $ean,                // Product EAN13
                    $offer['id'],        // Product reference
                    $productName,        // Product name
                    $productQuantity,    // Product quantity
                    $productDescription, // Product description
                    $features,           // Product features (array)
                    $productPrice,       // Product price
                    $urlList,         // Product images
                    1,             // Product default category
                    array(1)          // All categories for product (array)
                );
            } catch (\PrestaShopDatabaseException $e) {
            } catch (\PrestaShopException $e) {
            }
        }


    }

    /**
     * @param string $search
     * @param int $id_shop
     * @param int $id_lang
     * @return false|string|null
     */
    private function getProductIdByProductName(string $search, int $id_shop = 1, int $id_lang = 1)
    {
        return Db::getInstance()->getValue('
        SELECT id_product
        FROM '._DB_PREFIX_.'product_lang
        WHERE name LIKE "%'.(string)$search.'%"
        AND id_shop = '.$id_shop.' AND id_lang = '.$id_lang.'
        ');
    }


    /**
     * @throws \PrestaShopException
     * @throws \PrestaShopDatabaseException
     */
    function addOrUpdateProduct($ean13, $ref, $name, $qty, $text, $features, $price, $imgUrls, $catDef, $catAll) {
        if($productId = $this->getProductIdByProductName($name)){
            $product = new Product($productId);
        }else{
            $product = new Product();
        }
        $product->ean13 = $ean13;
        $product->reference = $ref;
        $product->name = $this->createMultiLangField(utf8_encode($name));
        $product->description = htmlspecialchars($text);
        $product->id_category_default = $catDef;
        $product->redirect_type = '301';
        $product->price = number_format($price, 6, '.', '');
        $product->minimal_quantity = 1;
        $product->show_price = 1;
        $product->on_sale = 0;
        $product->online_only = 0;
        $product->meta_description = '';
        $product->link_rewrite = $this->createMultiLangField(Tools::str2url($name));
        if(!$product->id){
            $product->add();
        }
        StockAvailable::setQuantity($product->id, null, $qty); // id_product, id_product_attribute, quantity
        $product->addToCategories($catAll);     // After product is submitted insert all categories


        $this->createOrUpdateProductFeatures($product->id, $features);
        $this->createOrUpdateProductPhotos($product->id, $imgUrls);

        echo 'Product added successfully (ID: ' . $product->id . ')';
    }

    private function createMultiLangField($field) {
        $res = array();
        foreach (Language::getIDs(false) as $id_lang) {
            $res[$id_lang] = $field;
        }
        return $res;
    }

    private function uploadImage($id_entity, $id_image = null, array $imgUrls) {
        $tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'ps_import');
        $watermark_types = explode(',', Configuration::get('WATERMARK_TYPES'));
        $image_obj = new Image((int)$id_image);
        $path = $image_obj->getPathForCreation();

        // Evaluate the memory required to resize the image: if it's too big we can't resize it.
        foreach($imgUrls as $imgUrl){
            if (!ImageManager::checkImageMemoryLimit($imgUrl)) {
                continue;
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
                continue;
            }
            unlink($tmpfile);
        }

        return true;
    }

    private function getDataFromAllegroProductParameters(array $offer): array
    {
        $ean = '';
        $features = [];

        foreach($offer['productSet'][0]['product']['parameters'] as $parameter){
            if(empty($parameter['values'])){
                /* TODO obsłużyć wartości valuesIds */
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
        $shops = Shop::getShops(true, null, true);
        $image = new Image();
        $image->id_product = $productId;
        $image->position = Image::getHighestPosition($productId) + 1;
        $image->cover = true;
        if (($image->validateFields(false, true)) === true && ($image->validateFieldsLang(false, true)) === true && $image->add()) {
            $image->associateTo($shops);
            if (!$this->uploadImage($productId, $image->id, $imgUrls)) {
                $image->delete();
            }
        }
    }
}