<?php

namespace Drupal\commerce_attribute\Plugin\Commerce\EntityTrait;

use Drupal\commerce\Plugin\Commerce\EntityTrait\EntityTraitBase;
use Drupal\entity\BundleFieldDefinition;

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
