<?php

/**
 * @file
 * Contains \Drupal\tmgmt\Entity\Controller\TranslatorList.
 */

namespace Drupal\tmgmt\Entity\Controller;

use Drupal\Core\Config\Entity\DraggableList;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of translators.
 */
class TranslatorList extends DraggableList {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'tmgmt_translator_overview';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = t('Translator name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $this->getLabel($entity);
    return $row + parent::buildRow($entity);
  }

}
