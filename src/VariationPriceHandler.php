<?php

namespace Drupal\commerce_attributes;

use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\commerce_product\ProductAttributeFieldManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\commerce_attributes\Entity\ProductAttributeValue;
use Drupal\commerce_attributes\Entity\ProductAttribute;

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
   * Constructor.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, EntityManagerInterface $entity_manager, EntityTypeManagerInterface $entity_type_manager, ProductAttributeFieldManagerInterface $attribute_field_manager) {
    $this->logger = $logger_factory->get('system');
    $this->entityTypeManager = $entity_type_manager;
    $this->entityManager = $entity_manager;
    $this->attributeFieldManager = $attribute_field_manager;
  }

  /**
   * Retrieves the product variations.
   *
   * @param array $types
   *   The product variation types.
   * @param string $attribute
   *   The attribute machine name.
   * @param int $attributeValue
   *   The Attribue value id.
   *
   * @return array
   *   Array of variations id.
   */
  public function getProductVariation(array $types, $attribute, $attributeValue) {
    // The attribute field name of variation type will always be in the
    // format of `attrubute_attribute_machine_name`.
    $attributefieldName = 'attribute_' . $attribute;

    // Get all variations associated with an particular attribute value.
    $query = \Drupal::entityQuery('commerce_product_variation')
      ->condition('type', $types, 'IN')
      ->condition($attributefieldName, $attributeValue);
    $variations = $query->execute();
    return $variations;
  }

  /**
   * Recalculates the product variation price based on the attribute price.
   *
   * @param Drupal\commerce_attributes\Entity\ProductAttributeValue $attributeValue
   *   The ProductAttributeValue entity.
   */
  public function updateProductVariationsPrice(ProductAttributeValue $attributeValue) {
    $attribute = $attributeValue->getAttribute();

    // Get all variation type associated with the attribute
    // which is being updated.
    $variationTypes = $this->getAllVariationTypes($attribute);
    if (empty($variationTypes)) {
      return FALSE;
    }

    // Get all product variations associated with the attribute value which is
    // being updated.
    $variations = $this->getProductVariation($variationTypes, $attribute->id(), $attributeValue->id());
    foreach ($variations as $variation_id) {
      $product_variation = $this->entityManager->getStorage('commerce_product_variation')->load($variation_id);
      $product_id = $product_variation->get('product_id')->getValue()['0']['target_id'];
      $product = $this->entityManager->getStorage('commerce_product')->load($product_id);
      $productBaseCost = $product->get('field_price')->getValue()['0']['number'];

      // Get the total price of all attributes associated with the product
      // variation type.
      $attributePrice = $this->getAttributePriceSum($product_variation->bundle(), $product_variation);
      $newPrice = $productBaseCost + $attributePrice;
      $currency = $product->get('field_price')->getValue()['0']['currency_code'];
      $newPrice = new Price((string) $newPrice, $currency);
      $product_variation->setPrice($newPrice);
      $product_variation->save();
    }
    return TRUE;
  }

  /**
   * Retrieve all product variation types associated with the attribute.
   *
   * @param Drupal\commerce_attributes\Entity\ProductAttribute $productAttribute
   *   The ProductAttribute Entity, which is being updated.
   *
   * @return array
   *   Array of all product variations types associated with the attribute.
   */
  public function getAllVariationTypes(ProductAttribute $productAttribute) {
    $types = [];
    $usedAttributes = $this->getAllUsedAttributes();
    foreach ($usedAttributes as $variation_type_id => $attributes) {
      if (in_array($productAttribute->id(), $attributes)) {
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
    $usedAttributes = [];
    $types = [];
    $variation_type_storage = $this->entityTypeManager->getStorage('commerce_product_variation_type');
    $variation_types = $variation_type_storage->loadMultiple();
    $attribute_field_map = $this->attributeFieldManager->getFieldMap();
    foreach ($variation_types as $variation_type_id => $variation_type) {
      $usedAttributes[$variation_type_id] = array_column($attribute_field_map[$variation_type_id], 'attribute_id');
    }
    return $usedAttributes;
  }

  /**
   * Provides the total price of attribues attached to a variation.
   *
   * @param string $variation_type_id
   *   The variation type machine name.
   * @param Drupal\commerce_product\Entity\ProductVariation $variation
   *   The commerce product variation.
   *
   * @return int
   *   Sum of all attribute values associated with a variation.
   */
  public function getAttributePriceSum($variation_type_id, ProductVariation $variation) {
    $attributeTotalPrice = 0;
    $usedAttributes = $this->getAllUsedAttributes();
    $variationTypeAttributes = $usedAttributes[$variation_type_id];
    foreach ($variationTypeAttributes as $attribute) {
      // The attribute field name of variation type will always be in the
      // format of `attrubute_attribute_machine_name`.
      $attributefieldName = 'attribute_' . $attribute;
      $attributeValueID = $variation->get($attributefieldName)->getValue()['0']['target_id'];
      $productAttributeValue = $this->entityManager->getStorage('commerce_product_attribute_value')->load($attributeValueID);
      if (!empty($productAttributeValue->get('price')->getValue()['0']['number'])) {
        $attributePrice = $productAttributeValue->get('price')->getValue()['0']['number'];
        $attributeTotalPrice += $attributePrice;
      }
    }
    return $attributeTotalPrice;
  }

  /**
   * Updates the product variation price
   *
   * @param Drupal\commerce_product\Entity\ProductVariation $variation
   *   The commerce product variation.
   *
   * @return Drupal\commerce_price\Price
   *   The price object.
   */
  public function getProductVariationPrice(ProductVariation $variation) {
    $product_id = $variation->get('product_id')->getValue()['0']['target_id'];
    $product = $this->entityManager->getStorage('commerce_product')->load($product_id);
    $productBaseCost = $product->get('field_price')->getValue()['0']['number'];

    // Get the total price of all attributes associated with the product
    // variation type.
    $attributePrice = $this->getAttributePriceSum($variation->bundle(), $variation);
    $newPrice = $productBaseCost + $attributePrice;
    $currency = $product->get('field_price')->getValue()['0']['currency_code'];
    $newPrice = new Price((string) $newPrice, $currency);
    return $newPrice;
  }

}
