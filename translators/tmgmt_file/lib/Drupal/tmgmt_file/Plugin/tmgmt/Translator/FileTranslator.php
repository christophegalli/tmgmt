<?php

/**
 * @file
 * Contains \Drupal\tmgmt_file\Plugin\tmgmt\Translator\FileTranslator.
 */

namespace Drupal\tmgmt_file\Plugin\tmgmt\Translator;

use Drupal\tmgmt\TranslatorPluginBase;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\Entity\Translator;
use Drupal\tmgmt\Annotation\TranslatorPlugin;
use Drupal\Core\Annotation\Translation;

/**
 * File translator.
 *
 * @TranslatorPlugin(
 *   id = "file",
 *   label = @Translation("File translator"),
 *   description = @Translation("File translator that exports and imports files."),
 *   ui = "Drupal\tmgmt_file\FileTranslatorUi"
 * )
 */
class FileTranslator extends TranslatorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function canTranslate(Translator $translator, Job $job) {
    // Anything can be exported.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function requestTranslation(Job $job) {
    $name = "JobID" . $job->tjid . '_' . $job->source_language . '_' . $job->target_language;

    $export = tmgmt_file_format_controller($job->getSetting('export_format'));

    $path = $job->getSetting('scheme') . '://tmgmt_file/' . $name . '.' .  $job->getSetting('export_format');
    $dirname = dirname($path);
    debug($path);
    if (file_prepare_directory($dirname, FILE_CREATE_DIRECTORY)) {
      $file = file_save_data($export->export($job), $path);
      file_usage()->add($file, 'tmgmt_file', 'tmgmt_job', $job->tjid);
      $job->submitted('Exported file can be downloaded <a href="!link">here</a>.', array('!link' => file_create_url($path)));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hasCheckoutSettings(Job $job) {
    return $job->getTranslator()->getSetting('allow_override');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSettings() {
    return array(
      'export_format' => 'xlf',
      'allow_override' => TRUE,
      'scheme' => 'public',
    );
  }

}
