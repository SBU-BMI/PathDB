<?php

declare(strict_types=1);

namespace Drupal\Tests\authorization\Unit\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\authorization\AuthorizationProfileInterface;
use Drupal\authorization\Form\AuthorizationProfileDeleteForm;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests AuthorizationProfileDeleteForm.
 *
 * @group authorization
 */
class AuthorizationProfileDeleteFormTest extends UnitTestCase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\Messenger|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $messenger;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $currentUser;

  /**
   * The form.
   *
   * @var \Drupal\authorization\Form\AuthorizationProfileDeleteForm
   */
  protected $form;

  /**
   * {@inheritdoc}
   */
  protected function setup(): void {
    parent::setUp();

    $container = new ContainerBuilder();

    $string_translation = $this->getStringTranslationStub();
    $container->set('string_translation', $string_translation);

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->messenger = $this->createMock(Messenger::class);
    $this->currentUser = $this->createMock(AccountInterface::class);

    $container->set('entity_type.manager', $this->entityTypeManager);
    $container->set('messenger', $this->messenger);
    $container->set('current_user', $this->currentUser);

    \Drupal::setContainer($container);

    $this->form = AuthorizationProfileDeleteForm::create($container);
  }

  /**
   * Test getQuestion() method.
   */
  public function testGetQuestion() {
    $authorizationProfile = $this->createMock(AuthorizationProfileInterface::class);
    $authorizationProfile->expects($this->once())
      ->method('label')
      ->willReturn('Test Authorization Profile');

    $this->form->setEntity($authorizationProfile);
    $question = (string) $this->form->getQuestion();
    $this->assertEquals('Are you sure you want to delete <em class="placeholder">Test Authorization Profile</em>?', $question);
  }

  /**
   * Test getCancelUrl() method.
   */
  public function testGetConfirmText() {
    $confirmText = (string) $this->form->getConfirmText();
    $this->assertEquals('Delete', $confirmText);
  }

  /**
   * Test getCancelUrl() method.
   */
  public function testGetCancelUrl() {
    $authorizationProfile = $this->createMock(AuthorizationProfileInterface::class);
    $authorizationProfile->expects($this->once())
      ->method('id')
      ->willReturn('test-authorization-profile');
    $this->form->setEntity($authorizationProfile);
    $cancelUrl = $this->form->getCancelUrl();
    $this->assertEquals('entity.authorization_profile.edit_form', $cancelUrl->getRouteName());
  }

  /**
   * Test submitForm() method.
   */
  public function testSubmitForm() {
    $authorizationProfile = $this->createMock(AuthorizationProfileInterface::class);
    $authorizationProfile->expects($this->once())
      ->method('label')
      ->willReturn('Test Authorization Profile');
    $authorizationProfile->expects($this->once())
      ->method('bundle')
      ->willReturn('authorization_profile');
    $authorizationProfile->expects($this->once())
      ->method('delete');

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('setRedirect')
      ->with('entity.authorization_profile.collection');
    $form = [];
    $this->form->setEntity($authorizationProfile);
    $this->form->submitForm($form, $form_state);
  }

}
