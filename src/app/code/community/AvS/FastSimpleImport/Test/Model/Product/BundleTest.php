<?php

class AvS_FastSimpleImport_Test_Model_Product_BundleTest extends EcomDev_PHPUnit_Test_Case
{
    protected function setUp()
    {
        Mage::getSingleton('core/resource')->getConnection('core_write')
            ->query('delete from catalog_product_entity');

        parent::setUp();
    }
    /**
     * @test
     * @loadExpectation
     * @dataProvider dataProvider
     */
    public function createProduct($values)
    {
        $productBaseModel = Mage::getModel('catalog/product');

        $this->assertCount(0, $productBaseModel->getCollection()->getAllIds());
        Mage::getModel('fastsimpleimport/import')->setUseNestedArrays(true)->processProductImport($values);

        $this->assertCount(3, $productBaseModel->getCollection()->getAllIds());

        $bundleProducts = $productBaseModel->getCollection()->addFieldToFilter('type_id', array('eq' => 'bundle'))->load();
        $this->assertCount(1, $bundleProducts->getAllIds());

        $sku = $bundleProducts->getFirstItem()->getSku();
        $productId = Mage::getModel('catalog/product')->getIdBySku($sku);
        
        /** @var Mage_Catalog_Model_Product $productModel */
        $productModel = Mage::getModel('catalog/product')->load($productId);

        /** @var Mage_Bundle_Model_Product_Type $bundleTypeInstance */
        $bundleTypeInstance = $productModel->getTypeInstance(true);

        $childrenSkus = $this->expected('%s-%s', $sku, 'children')->getSkus();
        $usedProducts = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToFilter('entity_id', array('in' => $bundleTypeInstance->getChildrenIds($productModel->getId())));
        foreach ($usedProducts as $product) {
            $this->assertTrue(in_array($product->getSku(), $childrenSkus));
        }
        
        $options = $bundleTypeInstance->getOptions($productModel);
        $this->assertCount(1, $options);

        $expectedOptions = $this->expected('%s-%s', $sku, 'options');
        $i = 0;
        foreach($options as $option) {

            $expectedOption = $expectedOptions->getData($i++);
            $this->assertEquals($expectedOption['type'], $option->getType());
            $this->assertEquals($expectedOption['title'], $option->getDefaultTitle());
        }
    }
}