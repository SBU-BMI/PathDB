<?php

declare(strict_types = 1);

namespace Drupal\ldap_authorization\Plugin\authorization\Provider;

use Drupal\authorization\AuthorizationSkipAuthorization;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\authorization\Provider\ProviderPluginBase;
use Drupal\ldap_servers\Helper\ConversionHelper;
use Drupal\ldap_servers\LdapTransformationTraits;
use Drupal\ldap_user\Processor\DrupalUserProcessor;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The LDAP authorization provider for authorization module.
 *
 * @AuthorizationProvider(
 *   id = "ldap_provider",
 *   label = @Translation("LDAP Authorization")
 * )
 */
class LDAPAuthorizationProvider extends ProviderPluginBase {

  use LdapTransformationTraits;

  /**
   * {@inheritdoc}
   */
  protected $handlers = ['ldap', 'ldap_authentication'];

  /**
   * {@inheritdoc}
   */
  protected $syncOnLogonSupported = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $revocationSupported = TRUE;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;


  /**
   * Drupal User Processor.
   *
   * @var \Drupal\ldap_user\Processor\DrupalUserProcessor
   */
  protected $drupalUserProcessor;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param array $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\ldap_user\Processor\DrupalUserProcessor $drupal_user_processor
   *   Drupal user processor.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    DrupalUserProcessor $drupal_user_processor
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->drupalUserProcessor = $drupal_user_processor;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('ldap.drupal_user_processor')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\authorization\Entity\AuthorizationProfile $profile */
    $profile = $this->configuration['profile'];
    $tokens = $this->getTokens();
    $tokens += $profile->getTokens();
    if ($profile->hasValidConsumer() && method_exists($profile->getConsumer(), 'getTokens')) {
      $tokens += $profile->getConsumer()->getTokens();
    }

    $storage = $this->entityTypeManager->getStorage('ldap_server');
    $query_results = $storage->getQuery()->execute();
    /** @var \Drupal\ldap_servers\Entity\Server[] $servers */
    $servers = $storage->loadMultiple($query_results);

    $form['status'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Base configuration'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    if (count($servers) === 0) {
      $form['status']['server'] = [
        '#type' => 'markup',
        '#markup' => $this->t('<strong>Warning</strong>: You must create an LDAP Server first.'),
      ];
      $this->messenger()->addWarning($this->t('You must create an LDAP Server first.'));
    }
    else {
      $server_options = [];
      foreach ($servers as $id => $server) {
        /** @var \Drupal\ldap_servers\Entity\Server $server */
        $server_options[$id] = $server->label() . ' (' . $server->get('address') . ')';
      }
    }

    $provider_config = $profile->getProviderConfig();

    if (!empty($server_options)) {
      if (isset($provider_config['status'])) {
        $default_server = $provider_config['status']['server'];
      }
      elseif (count($server_options) === 1) {
        $default_server = key($server_options);
      }
      else {
        $default_server = '';
      }
      $form['status']['server'] = [
        '#type' => 'radios',
        '#title' => $this->t('LDAP Server used in @profile_name configuration.', $tokens),
        '#required' => TRUE,
        '#default_value' => $default_server,
        '#options' => $server_options,
      ];
    }

    $form['status']['only_ldap_authenticated'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Only apply the following <strong>LDAP</strong> to <strong>@consumer_name</strong> configuration to users authenticated via LDAP', $tokens),
      '#description' => $this->t('One uncommon reason for disabling this is when you are using Drupal authentication, but want to leverage LDAP for authorization; for this to work the Drupal username still has to map to an LDAP entry.'),
      '#default_value' => $provider_config['status']['only_ldap_authenticated'] ?? '',
    ];

    $form['filter_and_mappings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('LDAP to @consumer_name mapping and filtering', $tokens),
      '#description' => $this->t('Representations of groups derived from LDAP might initially look like:
        <ul>
        <li><code>cn=students,ou=groups,dc=hogwarts,dc=edu</code></li>
        <li><code>cn=gryffindor,ou=groups,dc=hogwarts,dc=edu</code></li>
        <li><code>cn=faculty,ou=groups,dc=hogwarts,dc=edu</code></li>
        </ul>
        <strong>Warning: If you enable "Create <em>@consumer_name</em> targets if they do not exist" under conditions, all LDAP groups will be synced!</strong>', $tokens),
      '#collapsible' => TRUE,
    ];

    $form['filter_and_mappings']['use_first_attr_as_groupid'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Convert full DN to value of first attribute before mapping'),
      '#description' => $this->t('Example: <code>cn=students,ou=groups,dc=hogwarts,dc=edu</code> would be converted to <code>students</code>'),
      '#default_value' => $provider_config['filter_and_mappings']['use_first_attr_as_groupid'] ?? '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRowForm(array $form, FormStateInterface $form_state, $index = 0): array {
    $row = [];
    /** @var \Drupal\authorization\Entity\AuthorizationProfile $profile */
    $profile = $this->configuration['profile'];
    $mappings = $profile->getProviderMappings();
    $row['query'] = [
      '#type' => 'textfield',
      '#title' => $this->t('LDAP query'),
      '#maxlength' => 254,
      '#default_value' => $mappings[$index]['query'] ?? NULL,
    ];
    $row['is_regex'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Is this query a regular expression?'),
      '#description' => $this->t(
        'Example (note the "i" for case-insensitive): %example',
        [
          '%example' => new FormattableMarkup('<code>/^memberOf=staff/i</code>', []),
        ]
      ),
      '#default_value' => $mappings[$index]['is_regex'] ?? NULL,
    ];

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function getProposals(UserInterface $user): array {

    // Do not continue if user should be excluded from LDAP authentication.
    if ($this->drupalUserProcessor->excludeUser($user)) {
      throw new AuthorizationSkipAuthorization('User in list of excluded users');
    }
    /** @var \Drupal\authorization\Entity\AuthorizationProfile $profile */
    $profile = $this->configuration['profile'];
    $config = $profile->getProviderConfig();

    // Load the correct server.
    $server_id = $config['status']['server'];
    /** @var \Drupal\ldap_servers\Entity\Server $server */
    $server = \Drupal::service('entity_type.manager')
      ->getStorage('ldap_server')
      ->load($server_id);
    if (!$server->status()) {
      return [];
    }

    /** @var \Drupal\ldap_servers\LdapUserManager $ldap_user_manager */
    $ldap_user_manager = \Drupal::service('ldap.user_manager');
    $ldap_user_manager->setServer($server);

    $ldap_user_data = $ldap_user_manager->getUserDataByAccount($user);

    if (!$ldap_user_data && $user->isNew()) {
      // If we don't have a real user yet, fall back to the account name.
      $ldap_user_data = $ldap_user_manager->getUserDataByIdentifier($user->getAccountName());
    }

    if (!$ldap_user_data && $this->configuration['status']['only_ldap_authenticated'] === TRUE) {
      throw new AuthorizationSkipAuthorization('Not LDAP authenticated');
    }

    /** @var \Drupal\ldap_servers\LdapGroupManager $group_manager */
    $group_manager = \Drupal::service('ldap.group_manager');
    $group_manager->setServerById($server_id);

    // Get user groups from DN.
    $derive_from_dn_authorizations = $group_manager->groupUserMembershipsFromDn($user->getAccountName());
    if (!$derive_from_dn_authorizations) {
      $derive_from_dn_authorizations = [];
    }

    // Get user groups from membership.
    $group_dns = $group_manager->groupMembershipsFromUser($user->getAccountName());
    if (!$group_dns) {
      $group_dns = [];
    }

    $proposed_ldap_authorizations = array_merge($derive_from_dn_authorizations, $group_dns);
    $proposed_ldap_authorizations = array_unique($proposed_ldap_authorizations);
    \Drupal::service('ldap.detail_log')->log(
        'Available authorizations to test: @authorizations',
        ['@authorizations' => implode("\n", $proposed_ldap_authorizations)],
        'ldap_authorization'
      );

    if ((count($proposed_ldap_authorizations))) {
      return array_combine($proposed_ldap_authorizations, $proposed_ldap_authorizations);
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function filterProposals(array $proposedLdapAuthorizations, array $providerMapping): array {
    $filtered_proposals = [];
    foreach ($proposedLdapAuthorizations as $key => $value) {
      if ($providerMapping['is_regex']) {
        $pattern = $providerMapping['query'];
        try {
          if (preg_match($pattern, $value, $matches)) {
            // If there is a sub-pattern then return the first one.
            // Named sub-patterns not supported.
            if (count($matches) > 1) {
              $filtered_proposals[$key] = $matches[1];
            }
            elseif (count($matches) === 1) {
              $filtered_proposals[$key] = $matches[0];
            }
            else {
              $filtered_proposals[$key] = $value;
            }
          }
        }
        catch (\Exception $e) {
          \Drupal::logger('ldap')
            ->error('Error in matching regular expression @regex',
              ['@regex' => $pattern]
            );
        }
      }
      elseif (mb_strtolower($value) === mb_strtolower($providerMapping['query'])) {
        $filtered_proposals[$key] = $value;
      }
    }
    return $filtered_proposals;
  }

  /**
   * {@inheritdoc}
   */
  public function sanitizeProposals(array $proposals): array {
    // Configure this provider.
    /** @var \Drupal\authorization\Entity\AuthorizationProfile $profile */
    $profile = $this->configuration['profile'];
    $config = $profile->getProviderConfig();
    foreach ($proposals as $key => $authorization_id) {
      /** @var string $lowercase_key */
      $lowercase_key = \mb_strtolower($key);

      // The string check should not be necessary, it's a guard against a
      // misconfiguration such as someone setting use_first_attr_as_groupid
      // while not getting group information from a DN but rather the user
      // that is not in DN format.
      if (
        $config['filter_and_mappings']['use_first_attr_as_groupid'] &&
        strpos($authorization_id, '=') !== FALSE
      ) {
        $attr_parts = self::splitDnWithAttributes($authorization_id);
        unset($attr_parts['count']);
        if (count($attr_parts) > 0) {
          $first_part = \explode('=', $attr_parts[0]);
          if ($first_part && isset($first_part[1])) {
            $authorization_id = ConversionHelper::unescapeDnValue(trim($first_part[1]));
          }
        }
        $lowercase_key = \mb_strtolower($authorization_id);
      }

      $proposals[$lowercase_key] = $authorization_id;
      if ($key !== $lowercase_key) {
        unset($proposals[$key]);
      }
    }
    return $proposals;
  }

  /**
   * {@inheritdoc}
   */
  public function validateRowForm(array &$form, FormStateInterface $form_state): void {
    parent::validateRowForm($form, $form_state);

    foreach ($form_state->getValues() as $value) {
      if (
        isset($value['provider_mappings']) &&
        $value['provider_mappings']['is_regex'] == 1 &&
        @preg_match($value['provider_mappings']['query'], '') === FALSE
      ) {
        $form_state->setErrorByName('mapping', $this->t('Invalid regular expression'));
      }
    }
  }

}
