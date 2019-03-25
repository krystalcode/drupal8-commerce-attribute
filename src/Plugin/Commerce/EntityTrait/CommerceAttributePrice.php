<?php

namespace Drupal\commerce_attribute\Plugin\Commerce\EntityTrait;

use Drupal\commerce\Plugin\Commerce\EntityTrait\EntityTraitBase;
use Drupal\entity\BundleFieldDefinition;

/**
 * Provides the price entity trait.
 *
 * @CommerceEntityTrait(
 *   id = "commerce_attribute_price",
 *   label = @Translation("Adds price to the product's base price"),
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
      ->setDisplayOptions('form',
        [
          'settings' => [
            'display_label' => TRUE,
          ],
        ])
      ->setDisplayConfigurable('form', TRUE);
    return $fields;
  }

}
