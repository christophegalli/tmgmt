<?php

/**
 * @file
 * Provides Microsoft Translator plugin controller.
 *
 * Check @link http://msdn.microsoft.com/en-us/library/dd576287.aspx Microsoft
 * Translator @endlink. Note that we are using HTTP API.
 */

class TMGMTMicrosoftTranslatorController extends TMGMTDefaultTranslatorPluginController {

  /**
   * Translation service URL.
   *
   * @var string
   */
  protected $translatorURL = 'http://api.microsofttranslator.com/v2/Http.svc';

  /**
   * Implements TMGMTTranslatorPluginControllerInterface::isAvailable().
   */
  public function isAvailable() {
    if ($this->getOption('bing_api_id')) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Implements TMGMTTranslatorPluginControllerInterface::canTranslation().
   *
   * We will check are source and target language supported.
   */
  public function canTranslate(TMGMTJob $job) {
    $languages = $this->getSupportedLanguages();
    if ($this->isAvailable() && isset($languages[$job->source_language]) && isset($languages[$job->target_language])) {
      // The job is only translatable if the translator is available too.
      return TRUE;
    }
    // What to return if we can not query service and can not get supported
    // languages, for what ever reason? For now it is safer to return FALSE.
    return FALSE;
  }

  /**
   * Implements TMGMTTranslatorPluginControllerInterface::requestTranslation().
   *
   * Here we will acutally query source and get translations.
   */
  public function requestTranslation(TMGMTJob $job) {
    // @todo - who is handling cron / batch here, we or some other controler?
    $translate_url = $this->translatorURL . '/Translate';

    // Pull the source data array from through the job and flatten it..
    $data = tmgmt_flatten_data($job->getSourceData());
    foreach ($data as $key => $value) {
      if (isset($value['#translate']) && $value['#translate'] === FALSE) {
        continue;
      }

      $query_data = drupal_http_build_query(array(
        'appId'       => $this->getOption('bing_api_id'),
        'from'        => $job->source_language,
        'to'          => $job->target_language,
        'contentType' => 'text/plain',
        // @todo - if length is more then 10000 chars we need to split request.
        'text'        => $value['#text'],
      ));

      $options = array(
        // @todo - for some reason post is returning 202 status and service
        // is not translating things, maybe check this later.
        //'method' => 'POST',
        //'data' => $query_data,
        'headers' => array('Content-Type' => 'text/plain')
      );
      $result = drupal_http_request($translate_url. '?' . $query_data, $options);
      switch ($result->code) {
        case 200:
          // Lets leave DOMDocument for now because this service enable us
          // to send array of translation sources, and we will probably use
          // this soon.
          $dom = new DOMDocument;
          $dom->loadXML($result->data);
          $string_items = $dom->getElementsByTagName('string');
          $data[$key]['#text'] = $string_items->item(0)->nodeValue;
          break;
        case 400:
          $job->rejected($result->data);
          return;
          break;
        default:
          // @todo - we need to think here more what to do if service is not
          // responding for some items and is responding for some others.
          // Ideally we should save sources that we manage to translate and
          // not just return.
          $job->addMessage('Error from server: @error', array('@error' => $result->data));
          return;
          break;
      }
    }
    // Save the translated data through the job.
    $job->addTranslatedData(tmgmt_unflatten_data($data));
    // Mark this string as translatable.
    $job->needsReview('Microsoft Translator translation completed.');
  }

  /**
   * Implements TMGMTTranslatorPluginControllerInterface::getSupportedTargetLanguages().
   */
  public function getSupportedTargetLanguages($source_language) {
    $languages = $this->getSupportedLanguages();
    unset($languages[$source_language]);
    return $languages;
  }

  /**
   * Returns array of service supported languages.
   *
   * @return array
   */
  public function getSupportedLanguages() {
    $languages = &drupal_static(__FUNCTION__);
    if (!isset($languages)) {
      $languages = array();
      $res = drupal_http_request($this->translatorURL . '/GetLanguagesForTranslate' . '?appId=' . $this->getOption('bing_api_id'));
      if ($res->code == 200) {
        $dom = new DOMDocument;
        $dom->loadXML($res->data);
        $string_items = $dom->getElementsByTagName('string');
        foreach($string_items as $item) {
          $languages[$item->nodeValue] = $item->nodeValue;
        }
      }
    }
    return $languages;
  }
  
  /**
   * Defines plugin settings form. 
   * 
   * @param $form array
   * @param $form_state array
   */
  public function settingsForm($form, &$form_state) {
    $form['bing_api_id'] = array(
      '#type' => 'textfield',
      '#title' => t('Bing API ID'),
      // @todo - remove default test bing id before production release.
      '#default_value' => $this->getOption('bing_api_id', 'DA903AE0DF7668FD33F53192B5382E4D1D2CF369'),
      '#description' => t('Please enter yout Bing API ID, you can generate one on the !link.', array('!link' => l('http://www.bing.com/developers/appids.aspx', 'http://www.bing.com/developers/appids.aspx.'))),
    );
    return $form;
  }

}