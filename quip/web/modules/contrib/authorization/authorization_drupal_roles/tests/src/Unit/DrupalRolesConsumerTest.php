<?php

declare(strict_types=1);

namespace Drupal\Tests\authorization_drupal_roles\Unit;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\authorization\AuthorizationProfileInterface;
use Drupal\authorization_drupal_roles\AuthorizationDrupalRolesInterface;
use Drupal\authorization_drupal_roles\Plugin\authorization\Consumer\DrupalRolesConsumer;
use Drupal\user\RoleInterface;
use Drupal\user\RoleStorageInterface;
use Drupal\user\UserInterface;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Authorization Drupal roles service test.
 *
 * @group authorization_drupal_roles
 */
class DrupalRolesConsumerTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * The consumer.
   *
   * @var \Drupal\authorization_drupal_roles\Plugin\authorization\Consumer\DrupalRolesConsumer
   */
  protected $consumer;

  /**
   * The profile.
   *
   * @var \Drupal\authorization\AuthorizationProfileInterface
   */
  protected $profile;

  /**
   * The transliteration.
   *
   * @var \Drupal\Component\Transliteration\TransliterationInterface
   */
  protected $transliteration;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The authorization Drupal roles.
   *
   * @var \Drupal\authorization_drupal_roles\AuthorizationDrupalRolesInterface
   */
  protected $authorizationDrupalRoles;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->container = new ContainerBuilder();
    $string_translation = $this->getStringTranslationStub();
    $this->container->set('string_translation', $string_translation);

    $this->transliteration = $this->prophesize(TransliterationInterface::class);
    $this->container->set('transliteration', $this->transliteration->reveal());

    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->container->set('entity_type.manager', $this->entityTypeManager->reveal());

    $this->authorizationDrupalRoles = $this->prophesize(AuthorizationDrupalRolesInterface::class);
    $this->container->set('authorization_drupal_roles.manager', $this->authorizationDrupalRoles->reveal());

    \Drupal::setContainer($this->container);

    $this->profile = $this->prophesize(AuthorizationProfileInterface::class);

    $configuration = [
      'profile' => $this->profile->reveal(),
    ];
    $this->consumer = new DrupalRolesConsumer(
      $configuration,
      'authorization_drupal_roles',
      [],
      $this->transliteration->reveal(),
      $this->entityTypeManager->reveal(),
      $this->authorizationDrupalRoles->reveal());
  }

  /**
   * Test static create.
   */
  public function testStaticCreate() {

    $consumer = DrupalRolesConsumer::create(
      $this->container,
      [],
      'authorization_drupal_roles',
      []);

    $this->assertInstanceOf(DrupalRolesConsumer::class, $consumer);
  }

  /**
   * Test configuration form.
   */
  public function testBuildConfigurationForm() {
    $form_state = $this->prophesize(FormStateInterface::class);

    $form = $this->consumer->buildConfigurationForm([], $form_state->reveal());

    $this->assertNotEmpty($form['description']);
    $this->assertCount(1, $form);
  }

  /**
   * Test row form.
   */
  public function testBuildRowForm() {
    $authenticated_role = $this->prophesize(RoleInterface::class);
    $super_role = $this->prophesize(RoleInterface::class);
    $super_role->label()
      ->willReturn('Super')
      ->shouldBeCalled($this->once());
    $user_roles = $this->prophesize(RoleStorageInterface::class);
    $user_roles->loadMultiple()
      ->willReturn([
        'super' => $super_role->reveal(),
        'authenticated' => $authenticated_role->reveal(),
      ])
      ->shouldBeCalled($this->once());
    $this->entityTypeManager->getStorage('user_role')
      ->willReturn($user_roles->reveal())
      ->shouldBeCalled($this->once());

    $this->profile->getConsumerMappings()
      ->willReturn([])
      ->shouldBeCalled($this->once());

    $form_state = $this->prophesize(FormStateInterface::class);

    $row = $this->consumer->buildRowForm([], $form_state->reveal());

    $this->assertTrue(array_key_exists('role', $row));
    $this->assertCount(3, $row['role']['#options']);
  }

  /**
   * Test granting a role.
   */
  public function testGrantSingleAuthorization() {
    $user = $this->prophesize(UserInterface::class);
    $user->id()
      ->willReturn(1)
      ->shouldBeCalled($this->once());
    $user->addRole('super')
      ->willReturn(NULL)
      ->shouldBeCalled($this->once());

    $this->authorizationDrupalRoles->getRoles(1, 'test')
      ->willReturn(['student'])
      ->shouldBeCalled($this->once());

    $this->authorizationDrupalRoles->setRoles(1, 'test', ['student', 'super'])
      ->willReturn(NULL)
      ->shouldBeCalled($this->once());

    $this->transliteration->transliterate('super', 'en', '')
      ->willReturn('super')
      ->shouldBeCalled($this->once());

    $this->consumer->grantSingleAuthorization($user->reveal(), 'super', 'test');
  }

  /**
   * Test revoking roles.
   */
  public function testRevokeGrants() {

    $user = $this->prophesize(UserInterface::class);
    $user->id()
      ->willReturn(1)
      ->shouldBeCalled($this->exactly(2));
    $user->removeRole('student')
      ->willReturn(NULL)
      ->shouldBeCalled($this->once());

    $this->transliteration->transliterate('super', 'en', '')
      ->willReturn('super')
      ->shouldBeCalled($this->once());

    $this->authorizationDrupalRoles->getRoles(1, 'test')
      ->willReturn(['student', 'super'])
      ->shouldBeCalled($this->once());

    $this->authorizationDrupalRoles->setRoles(1, 'test', [1 => 'super'])
      ->willReturn(NULL)
      ->shouldBeCalled($this->once());

    $this->consumer->revokeGrants($user->reveal(), ['super'], 'test');
  }

  /**
   * Test creating a role. Role is created.
   */
  public function testCreateConsumerTarget() {

    $this->transliteration->transliterate('super', 'en', '')
      ->willReturn('super')
      ->shouldBeCalled($this->once());

    $super_role = $this->prophesize(RoleInterface::class);
    $super_role->save()
      ->willReturn(NULL)
      ->shouldBeCalled($this->once());

    $user_roles = $this->prophesize(RoleStorageInterface::class);
    $user_roles->load('super')
      ->willReturn(NULL)
      ->shouldBeCalled($this->once());
    $user_roles->create(['id' => 'super', 'label' => 'super'])
      ->willReturn($super_role)
      ->shouldBeCalled($this->once());

    $this->entityTypeManager->getStorage('user_role')
      ->willReturn($user_roles->reveal())
      ->shouldBeCalled($this->once());

    $this->consumer->createConsumerTarget('super');
  }

  /**
   * Test creating a role. Role exists.
   */
  public function testCreateConsumerTargetExists() {

    $this->transliteration->transliterate('super', 'en', '')
      ->willReturn('super')
      ->shouldBeCalled($this->once());
    $super_role = $this->prophesize(RoleInterface::class);
    $user_roles = $this->prophesize(RoleStorageInterface::class);
    $user_roles->load('super')
      ->willReturn($super_role)
      ->shouldBeCalled($this->once());
    $this->entityTypeManager->getStorage('user_role')
      ->willReturn($user_roles->reveal())
      ->shouldBeCalled($this->once());

    $this->consumer->createConsumerTarget('super');
  }

  /**
   * Test filter proposals.
   */
  public function testFilterProposalsSource() {

    $proposals = ['proposal'];
    $mapping = [
      'role' => 'source',
    ];

    $result = $this->consumer->filterProposals($proposals, $mapping);

    $this->assertEquals($result, $proposals);
  }

  /**
   * Test filter proposals.
   */
  public function testFilterProposalsNone() {

    $proposals = ['proposal'];
    $mapping = [
      'role' => 'none',
    ];

    $result = $this->consumer->filterProposals($proposals, $mapping);

    $this->assertEquals($result, []);
  }

  /**
   * Test filter proposals.
   */
  public function testFilterProposalsRole() {

    $proposals = ['proposal'];
    $mapping = [
      'role' => 'super',
    ];

    $result = $this->consumer->filterProposals($proposals, $mapping);

    $this->assertEquals($result, ['super' => 'super']);
  }

  /**
   * Test filter proposals.
   */
  public function testFilterProposalsEmpty() {

    $proposals = [];
    $mapping = [
      'role' => 'super',
    ];

    $result = $this->consumer->filterProposals($proposals, $mapping);

    $this->assertEquals($result, []);
  }

}
