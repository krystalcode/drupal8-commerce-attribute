<?php

namespace Drupal\commerce_attribute;

use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\commerce_product\ProductAttributeFieldManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\commerce_attribute\Entity\ProductAttributeValue;
use Drupal\commerce_attribute\Entity\ProductAttribute;
use Drupal\Core\Entity\Query\QueryFactory;

/**
 * Handles the Variation price calculations.
 */
class VariationPriceHandler {

  /**
   * Logger Service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $Logger;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The attribute field manager.
   *
   * @var \Drupal\commerce_product\ProductAttributeFieldManagerInterface
   */
  protected $attributeFieldManager;

  /**
   * Drupal\Core\Entity\Query\QueryFactory definition.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $variationStorage;

  /**
   * Constructor.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, EntityManagerInterface $entity_manager, EntityTypeManagerInterface $entity_type_manager, ProductAttributeFieldManagerInterface $attribute_field_manager, QueryFactory $entity_query) {
    $this->logger = $logger_factory->get('system');
    $this->entityTypeManager = $entity_type_manager;
    $this->entityManager = $entity_manager;
    $this->attributeFieldManager = $attribute_field_manager;
    $this->variationStorage = $entity_query;
  }

  /**
   * Retrieves the product variations.
   *
   * @param array $types
   *   The product variation types.
   * @param string $attribute
   *   The attribute machine name.
   * @param int $attribute_value
   *   The Attribue value id.
   *
   * @return array
   *   Array of variations id.
   */
  public function getProductVariations(array $types, $attribute, $attribute_value) {
    // The attribute field name of variation type will always be in the
    // format of `attrubute_attribute_machine_name`.
    $attribute_field_name = 'attribute_' . $attribute;

    // Get all variations associated with an particular attribute value.
    return $this->variationStorage->get('commerce_product_variation')
      ->condition('type', $types, 'IN')
      ->condition($attribute_field_name, $attribute_value)->execute();
  }

  /**
   * Recalculates the product variation price based on the attribute price.
   *
   * @param Drupal\commerce_attribute\Entity\ProductAttributeValue $attribute_value
   *   The ProductAttributeValue entity.
   */
  public function updateProductVariationsPrice(ProductAttributeValue $attribute_value) {
    $attribute = $attribute_value->getAttribute();

    // Get all variation type associated with the attribute
    // which is being updated.
    $variation_types = $this->getAllVariationTypes($attribute);
    if (empty($variation_types)) {
      return FALSE;
    }

    // Get all product variations associated with the attribute value which is
    // being updated.
    $variations = $this->getProductVariations($variation_types, $attribute->id(), $attribute_value->id());
    foreach ($variations as $variation_id) {
      $product_variation = $this->entityManager->getStorage('commerce_product_variation')->load($variation_id);
      $product = $product_variation->get('product_id')->entity;
      $base_price = $product->get('price')->first()->toPrice();

      // Get the total price of all attributes associated with the product
      // variation type.
      $currency = $base_price->getCurrencyCode();
      $attribute_rice = $this->getAttributePriceSum($product_variation->bundle(), $product_variation, $currency);
      $price = $base_price->add($attribute_rice);
      $product_variation->setPrice($price);
      $product_variation->save();
    }
    return TRUE;
  }

  /**
   * Retrieve all product variation types associated with the attribute.
   *
   * @param Drupal\commerce_attribute\Entity\ProductAttribute $product_attribute
   *   The ProductAttribute Entity, which is being updated.
   *
   * @return array
   *   Array of all product variations types associated with the attribute.
   */
  public function getAllVariationTypes(ProductAttribute $product_attribute) {
    $types = [];
    $used_attributes = $this->getAllUsedAttributes();
    foreach ($used_attributes as $variation_type_id => $attributes) {
      if (in_array($product_attribute->id(), $attributes)) {
        $types[] = $variation_type_id;
      }
    }
    return $types;
  }

  /**
   * Retrieves all attributes used by product variation types.
   *
   * @return array
   *   Associative array which has product variation type as key and
   *   the attributes used as value.
   */
  public function getAllUsedAttributes() {
    $used_attributes = [];
    $types = [];
    $variation_type_storage = $this->entityTypeManager->getStorage('commerce_product_variation_type');
    $variation_types = $variation_type_storage->loadMultiple();
    $attribute_field_map = $this->attributeFieldManager->getFieldMap();
    foreach ($variation_types as $variation_type_id => $variation_type) {
      if ($variation_type->hasTrait('commerce_attribute_variation_price')) {
        $used_attributes[$variation_type_id] = array_column($attribute_field_map[$variation_type_id], 'attribute_id');
      }
    }
    return $used_attributes;
  }

  /**
   * Provides the total price of attribues attached to a variation.
   *
   * @param string $variation_type_id
   *   The variation type machine name.
   * @param Drupal\commerce_product\Entity\ProductVariation $variation
   *   The commerce product variation.
   * @param string $currency
   *   The currency string.
   *
   * @return int
   *   Sum of all attribute values associated with a variation.
   */
  public function getAttributePriceSum($variation_type_id, ProductVariation $variation, $currency) {
    $attribute_total_price = new Price((string) 0, $currency);
    $used_attributes = $this->getAllUsedAttributes();
    if (empty($used_attributes)) {
      return $attribute_total_price;
    }
    $variation_type_attributes = $used_attributes[$variation_type_id];
    foreach ($variation_type_attributes as $attribute) {
      // The attribute field name of variation type will always be in the
      // format of `attrubute_attribute_machine_name`.
      $attribute_field_name = 'attribute_' . $attribute;
      $attribute_value = $variation->get($attribute_field_name)->entity;
      if (!empty($attribute_value) && $attribute_value->hasField('price')) {
        $attribute_price = $attribute_value->get('price')->first()->toPrice();
        $attribute_total_price = $attribute_total_price->add($attribute_price);
      }
    }
    return $attribute_total_price;
  }

  /**
   * Updates the product variation price.
   *
   * @param Drupal\commerce_product\Entity\ProductVariation $variation
   *   The commerce product variation.
   *
   * @return Drupal\commerce_price\Price
   *   The price object.
   */
  public function getProductVariationPrice(ProductVariation $variation) {
    $product = $variation->get('product_id')->entity;
    if (empty($product->get('price')->first())) {
      return NULL;
    }

    $base_price = $product->get('price')->first()->toPrice();
    // Get the total price of all attributes associated with the product
    // variation type.
    $currency = $base_price->getCurrencyCode();
    $attribute_price = $this->getAttributePriceSum($variation->bundle(), $variation, $currency);
    $new_price = $base_price->add($attribute_price);
    return $new_price;
  }

  /**
   * Retrieves Product Type associated with a variation type.
   *
   * @return object
   *   The product type object.
   */
  public function getProductTypes($variation_type_id) {
    $product_type_storage = $this->entityTypeManager->getStorage('commerce_product_type');
    $product_types = $product_type_storage->loadMultiple();
    foreach ($product_types as $product_type_id => $product_type) {
      if ($variation_type_id == $product_type->getVariationTypeId()) {
        return $product_type;
      }
    }
    return NULL;
  }

}
