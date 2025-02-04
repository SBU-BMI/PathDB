<?php

declare(strict_types=1);

namespace Drupal\Tests\authorization\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\authorization\Form\AuthorizationSettingsForm;

/**
 * Test Authorization Settings Form.
 *
 * @group authorization
 */
class AuthorizationSettingsFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'authorization',
  ];

  /**
   * {@inheritdoc}
   */
  public function setup(): void {
    parent::setup();
    $this->installConfig(['authorization']);
  }

  /**
   * Test Settings form.
   */
  public function testForm() {
    $settings = $this->container->get('config.factory')->get('authorization.settings');
    $this->assertFalse($settings->get('authorization_message'));
    $form = AuthorizationSettingsForm::create(\Drupal::getContainer());
    $form_state = new FormState();
    $form_array = [];
    $this->assertEquals('authorization_settings', $form->getFormId());

    $built_form = $form->buildForm([], $form_state);
    $this->assertIsArray($built_form);

    $this->assertArrayHasKey('authorization_message', $built_form);
    $this->assertArrayHasKey('actions', $built_form);
    $this->assertArrayHasKey('#theme', $built_form);

    $form_state->setValue('authorization_message', TRUE);
    $form->submitForm($form_array, $form_state);
    $settings = $this->container->get('config.factory')->get('authorization.settings');
    $this->assertTrue($settings->get('authorization_message'));
  }

}
