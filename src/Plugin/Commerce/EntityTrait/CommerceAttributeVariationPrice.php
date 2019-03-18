<?php

namespace Drupal\commerce_attribute\Plugin\Commerce\EntityTrait;

use Drupal\commerce\Plugin\Commerce\EntityTrait\EntityTraitBase;

/**
 * Provides the price entity trait.
 *
 * @CommerceEntityTrait(
 *   id = "commerce_attribute_variation_price",
 *   label = @Translation("Attribute-based Pricing"),
 *   entity_types = {"commerce_product_variation"}
 * )
 */
class CommerceAttributeVariationPrice extends EntityTraitBase {

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = [];
    return $fields;
  }

}
