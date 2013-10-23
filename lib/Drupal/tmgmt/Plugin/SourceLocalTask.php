<?php

/**
 * @file
 * Contains \Drupal\tmgmt\Plugin\SourceLocalTask.
 */

namespace Drupal\tmgmt\Plugin;

use Drupal\Core\Menu\LocalTaskDefault;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides route parameters needed to link to a sources overview page.
 */
class SourceLocalTask extends LocalTaskDefault {

  /**
   * {@inheritdoc}
   */
  public function getRouteParameters(Request $request) {
    dpm($this->pluginDefinition);
    list($base, $plugin, $item_type) = explode(':', $this->pluginId);
    return array('plugin' => $plugin, 'item_type' => $item_type);
  }

}
