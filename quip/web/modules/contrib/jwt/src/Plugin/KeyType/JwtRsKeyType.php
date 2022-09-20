<?php

namespace Drupal\jwt\Plugin\KeyType;

use Drupal\Core\Form\FormStateInterface;
use Drupal\key\Plugin\KeyTypeBase;
use Drupal\key\Plugin\KeyPluginFormInterface;

/**
 * Defines a key type for JWT RSA Signatures.
 *
 * @KeyType(
 *   id = "jwt_rs",
 *   label = @Translation("JWT RSA Key"),
 *   description = @Translation("A key type used for JWT RSA signature algorithms."),
 *   group = "privatekey",
 *   key_value = {
 *     "plugin" = "textarea_field"
 *   }
 * )
 */
class JwtRsKeyType extends KeyTypeBase implements KeyPluginFormInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'algorithm' => 'RS256',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $algorithm_options = [
      'RS256' => $this->t('RSASSA-PKCS1-v1_5 using SHA-256 (RS256)'),
    ];

    $algorithm = $this->getConfiguration()['algorithm'];

    $form['algorithm'] = [
      '#type' => 'select',
      '#title' => $this->t('JWT Algorithm'),
      '#description' => $this->t('The JWT Algorithm to use with this key.'),
      '#options' => $algorithm_options,
      '#default_value' => $algorithm,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->setConfiguration($form_state->getValues());
  }

  /**
   * {@inheritdoc}
   */
  public static function generateKeyValue(array $configuration) {
    $algorithm_keysize = self::getAlgorithmKeysize();
    $algorithm = $configuration['algorithm'];

    if (empty($algorithm) || !isset($algorithm_keysize[$algorithm])) {
      $algorithm = 'RS256';
    }

    $key_resource = openssl_pkey_new([
      'private_key_bits' => $algorithm_keysize[$algorithm],
      'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    $key_string = '';

    openssl_pkey_export($key_resource, $key_string);
    openssl_pkey_free($key_resource);

    return $key_string;
  }

  /**
   * {@inheritdoc}
   */
  public function validateKeyValue(array $form, FormStateInterface $form_state, $key_value) {
    if (!$form_state->getValue('algorithm')) {
      return;
    }

    // Validate the key.
    $algorithm = $form_state->getValue('algorithm');

    if (strpos($key_value, '-----BEGIN PUBLIC KEY-----') !== FALSE) {
      $key_resource = openssl_pkey_get_public($key_value);
    }
    else {
      $key_resource = openssl_pkey_get_private($key_value);
    }

    if ($key_resource === FALSE) {
      $form_state->setErrorByName('key_type', $this->t('Invalid Key.'));
      return;
    }

    $key_details = openssl_pkey_get_details($key_resource);
    if ($key_details === FALSE) {
      $form_state->setErrorByName('key_type', $this->t('Unable to get key details.'));
      return;
    }

    $required_bits = self::getAlgorithmKeysize()[$algorithm];
    if ($key_details['bits'] < $required_bits) {
      $args = ['%size' => $key_details['bits'], '%required' => $required_bits];
      $form_state->setErrorByName('key_type', $this->t('Key size (%size bits) is too small for algorithm chosen. Algorithm requires a minimum of %required bits.', $args));
    }

    if ($key_details['type'] != OPENSSL_KEYTYPE_RSA) {
      $form_state->setErrorByName('key_type', $this->t('Key must be RSA.'));
    }

    openssl_pkey_free($key_resource);
  }

  /**
   * Get keysizes for the various algorithms.
   *
   * @return array
   *   An array key keysizes.
   */
  protected static function getAlgorithmKeysize() {
    return [
      'RS256' => 2048,
    ];
  }

}
