<?php

namespace Drupal\br_domain\Plugin\LanguageNegotiation;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\language\LanguageNegotiationMethodBase;
use Drupal\language\LanguageSwitcherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class for identifying language via URL prefix with domains.
 *
 * @LanguageNegotiation(
 *   id = \Drupal\br_domain\Plugin\LanguageNegotiation\LanguageNegotiationUrlDomain::METHOD_ID,
 *   types = {\Drupal\Core\Language\LanguageInterface::TYPE_INTERFACE,
 *   \Drupal\Core\Language\LanguageInterface::TYPE_CONTENT,
 *   \Drupal\Core\Language\LanguageInterface::TYPE_URL},
 *   weight = -8,
 *   name = @Translation("Domain aware URL"),
 *   description = @Translation("Language from the URL; Path format /[domain]/[language]"),
 *   config_route_name = "br_domain.negotiation_url_domain"
 * )
 */
class LanguageNegotiationUrlDomain extends LanguageNegotiationMethodBase implements InboundPathProcessorInterface, OutboundPathProcessorInterface, LanguageSwitcherInterface {

  /**
   * The language negotiation method id.
   */
  const METHOD_ID = 'language-url-domain';

  /**
   * The position of the language code in the URL.
   *
   *   URL: /[domain]/[language] = position 1
   */
  const LANGUAGE_PART_POSITION = 1;

  /**
   * {@inheritdoc}
   */
  public function getLangcode(Request $request = NULL) {
    $langcode = NULL;

    if ($request && $this->languageManager) {
      $languages = $this->languageManager->getLanguages();
      $prefixes = $this->config->get('br_domain.language.negotiation_domain')->get('prefixes');

      $request_path = urldecode(trim($request->getPathInfo(), '/'));
      $path_args = explode('/', $request_path);
      // At this point the path has not yet been processed by the inbound domain
      // path processor. Therefore the language code is not the first path argument.
      $prefix = isset($path_args[$this::LANGUAGE_PART_POSITION]) ? $path_args[$this::LANGUAGE_PART_POSITION] : '';

      // Search prefix within added languages.
      $negotiated_language = FALSE;
      if ($prefix) {
        foreach ($languages as $language) {
          if (isset($prefixes[$language->getId()]) && $prefixes[$language->getId()] == $prefix) {
            $negotiated_language = $language;
            break;
          }
        }
      }

      if ($negotiated_language) {
        $langcode = $negotiated_language->getId();
      }

    }

    return $langcode;
  }

  /**
   * {@inheritDoc}
   */
  public function processInbound($path, Request $request) {

    $prefixes = $this->config->get('br_domain.language.negotiation_domain')->get('prefixes');
    $parts = explode('/', trim($path, '/'));
    // At this point the path has been processed by the inbound domain path
    // processor. Therefore the language code is now the first path argument.
    $prefix = array_shift($parts);

    // Search prefix within added languages.
    foreach ($this->languageManager->getLanguages() as $language) {
      if (isset($prefixes[$language->getId()]) && $prefixes[$language->getId()] == $prefix) {
        // Rebuild $path with the language removed.
        $path = '/' . implode('/', $parts);
        break;
      }
    }

    return $path;
  }

  public function processOutbound($path, &$options = array(), Request $request = NULL, BubbleableMetadata $bubbleable_metadata = NULL) {

    $languages = array_flip(array_keys($this->languageManager->getLanguages()));
    // Language can be passed as an option, or we go for current URL language.
    if (!isset($options['language'])) {
      $language_url = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_URL);
      $options['language'] = $language_url;
    }
    // We allow only added languages here.
    elseif (!is_object($options['language']) || !isset($languages[$options['language']->getId()])) {
      return $path;
    }
    $prefixes = $this->config->get('br_domain.language.negotiation_domain')->get('prefixes');

    if (is_object($options['language']) && !empty($prefixes[$options['language']->getId()])) {
      $options['prefix'] = $prefixes[$options['language']->getId()] . '/';
      if ($bubbleable_metadata) {
        $bubbleable_metadata->addCacheContexts(['languages:' . LanguageInterface::TYPE_URL]);
      }
    }

    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguageSwitchLinks(Request $request, $type, Url $url) {
    $links = array();

    // @todo Copied from LanguageNegotiationUrl. Does this work?
    foreach ($this->languageManager->getNativeLanguages() as $language) {
      $links[$language->getId()] = array(
        // We need to clone the $url object to avoid using the same one for all
        // links. When the links are rendered, options are set on the $url
        // object, so if we use the same one, they would be set for all links.
        'url' => clone $url,
        'title' => $language->getName(),
        'language' => $language,
        'attributes' => array('class' => array('language-link')),
      );
    }

    return $links;
  }

}
