<?php

namespace Drupal\br_domain\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;

/**
 * Configure the Domain aware URL language negotiation method.
 */
class NegotiationUrlDomainForm extends ConfigFormBase {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new LanguageDeleteForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LanguageManagerInterface $language_manager) {
    parent::__construct($config_factory);
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'br_domain_language_negotiation_configure_url_domain_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['br_domain.language.negotiation_domain'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    global $base_url;
    $config = $this->config('br_domain.language.negotiation_domain');

    $form['prefix'] = array(
      '#type' => 'details',
      '#tree' => TRUE,
      '#title' => $this->t('Path prefix configuration'),
      '#open' => TRUE,
      '#description' => $this->t('Language codes or other custom text to use as a path prefix for URL language detection. For the selected fallback language, this value may be left blank. <strong>Modifying this value may break existing URLs. Use with caution in a production environment.</strong> Example: Specifying "deutsch" as the path prefix code for German results in URLs like "example.com/deutsch/contact".'),
    );

    $languages = $this->languageManager->getLanguages();
    $prefixes = $config->get('prefixes');
    foreach ($languages as $langcode => $language) {
      $t_args = array('%language' => $language->getName(), '%langcode' => $language->getId());
      $form['prefix'][$langcode] = array(
        '#type' => 'textfield',
        '#title' => $language->isDefault() ? $this->t('%language (%langcode) path prefix (Default language)', $t_args) : $this->t('%language (%langcode) path prefix', $t_args),
        '#maxlength' => 64,
        '#default_value' => isset($prefixes[$langcode]) ? $prefixes[$langcode] : '',
        '#field_prefix' => $base_url . '/',
      );
    }

    $form_state->setRedirect('language.negotiation');

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $languages = $this->languageManager->getLanguages();

    // Count repeated values for uniqueness check.
    $count = array_count_values($form_state->getValue('prefix'));
    $default_langcode = $this->languageManager->getDefaultLanguage()->getId();
    foreach ($languages as $langcode => $language) {
      $value = $form_state->getValue(array('prefix', $langcode));
      if ($value === '') {
        if (!($default_langcode == $langcode) && $form_state->getValue('language_negotiation_url_part') == LanguageNegotiationUrl::CONFIG_PATH_PREFIX) {
          // Throw a form error if the prefix is blank for a non-default language,
          // although it is required for selected negotiation type.
          $form_state->setErrorByName("prefix][$langcode", $this->t('The prefix may only be left blank for the <a href=":url">selected detection fallback language.</a>', [
            ':url' => $this->getUrlGenerator()->generate('br_domain.language.negotiation_selected'),
          ]));
        }
      }
      elseif (strpos($value, '/') !== FALSE) {
        // Throw a form error if the string contains a slash,
        // which would not work.
        $form_state->setErrorByName("prefix][$langcode", $this->t('The prefix may not contain a slash.'));
      }
      elseif (isset($count[$value]) && $count[$value] > 1) {
        // Throw a form error if there are two languages with the same
        // domain/prefix.
        $form_state->setErrorByName("prefix][$langcode", $this->t('The prefix for %language, %value, is not unique.', array('%language' => $language->getName(), '%value' => $value)));
      }
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save selected format (prefix or domain).
    $this->config('br_domain.language.negotiation_domain')
      // Save new domain and prefix values.
      ->set('prefixes', $form_state->getValue('prefix'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
