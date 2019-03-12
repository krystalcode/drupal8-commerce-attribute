<?php

namespace Drupal\commerce_attributes\Plugin\Commerce\EntityTrait;

use Drupal\entity\BundleFieldDefinition;
use Drupal\commerce\Plugin\Commerce\EntityTrait\EntityTraitBase;

/**
 * Provides the price entity trait.
 *
 * @CommerceEntityTrait(
 *   id = "commerce_attribute_price",
 *   label = @Translation("Has Price"),
 *   entity_types = {"commerce_product_attribute_value"}
 * )
 */
class CommerceAttributePrice extends EntityTraitBase {

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = [];
    $fields['price'] = BundleFieldDefinition::create('commerce_price')
      ->setLabel(t('Price'))
      ->setRequired(TRUE);
    return $fields;
  }

}
