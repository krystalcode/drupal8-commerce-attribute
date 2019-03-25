<?php

namespace Drupal\commerce_attribute\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;

/**
 * Defines the add/edit/duplicate form for product attribute value.
 */
class ProductAttributeValueForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRouteMatch(RouteMatchInterface $route_match, $entity_type_id) {
    if ($route_match->getRawParameter('commerce_product_attribute_value') !== NULL) {
      $entity = $route_match->getParameter('commerce_product_attribute_value');
    }
    else {
      /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
      $attribute = $route_match->getParameter('commerce_product_attribute');
      $values = [
        'attribute' => $attribute,
      ];
      $entity = $this->entityTypeManager->getStorage('commerce_product_attribute_value')->create($values);
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEntity() {
    parent::prepareEntity();

    if ($this->operation == 'duplicate') {
      $this->entity = $this->entity->createDuplicate();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $commerce_product_attribute = \Drupal::routeMatch()->getRawParameter('commerce_product_attribute');
    $this->entity->save();
    $this->messenger()->addMessage($this->t('Saved the %label attribute.', ['%label' => $this->entity->label()]));
    $url = Url::fromRoute('view.attribute_values.page_1', ['commerce_product_attribute' => $commerce_product_attribute]);
    $form_state->setRedirectUrl($url);
  }

}
