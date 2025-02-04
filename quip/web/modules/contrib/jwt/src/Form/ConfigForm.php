<?php

namespace Drupal\jwt\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\jwt\Transcoder\JwtTranscoder;
use Drupal\key\KeyRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * JWT module config form.
 */
class ConfigForm extends ConfigFormBase {

  /**
   * The JWT transcoder.
   *
   * @var \Drupal\jwt\Transcoder\JwtTranscoder
   */
  protected $transcoder;

  /**
   * The key repository.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepo;

  /**
   * ConfigForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory for parent.
   * @param \Drupal\key\KeyRepositoryInterface $key_repo
   *   Key repo to validate keys.
   * @param \Drupal\jwt\Transcoder\JwtTranscoder $transcoder
   *   JWT Transcoder.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    KeyRepositoryInterface $key_repo,
    JwtTranscoder $transcoder
  ) {
    $this->keyRepo = $key_repo;
    $this->transcoder = $transcoder;
    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('key.repository'),
      $container->get('jwt.transcoder')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'jwt.config',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'jwt_config_form';
  }

  /**
   * AJAX Function callback.
   *
   * @param array $form
   *   Drupal form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Drupal form state object.
   *
   * @return mixed
   *   Updated AJAXed form.
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    return $form['key-container'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['key-container'] = [
      '#type' => 'container',
      '#prefix' => '<div id="jwt-key-container">',
      '#suffix' => '</div>',
      '#weight' => 10,
    ];

    $form['key-container']['jwt_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('JWT Key'),
      '#default_value' => $this->config('jwt.config')->get('key_id'),
      '#key_filters' => [
        'type' => ['jwt_hs', 'jwt_rs'],
      ],
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $key_id = $form_state->getValue('jwt_key');
    $key = $this->keyRepo->getKey($key_id);

    $algorithm = $key->getKeyType()->getConfiguration()['algorithm'];
    if ($key != NULL && $key->getKeyType()->getPluginId() != $this->transcoder->getAlgorithmType($algorithm)) {
      $form_state->setErrorByName('jwt_key', $this->t('Invalid key type selected.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $values = $form_state->getValues();

    if (isset($values['jwt_key'])) {
      $this->config('jwt.config')->set('key_id', $values['jwt_key'])->save();
    }
  }

}
