<?php

/**
 * @file
 * Views integration for ldap_query.
 */

declare(strict_types = 1);

/**
 * Implements hook_views_data().
 */
function ldap_query_views_data(): array {
  $data['ldap_query']['table']['group'] = t('LDAP Query');

  $data['ldap_query']['table']['base'] = [
    'query_id' => 'ldap_query',
    'title' => t('LDAP Query'),
    'help' => t('Data from LDAP Server.'),
  ];

  // Fields.
  // @todo DN often empty for most cases. Less than ideal base field.
  $data['ldap_query']['dn'] = [
    'title' => t('DN'),
    'help' => t('Distinguished name attribute.'),
    'field' => [
      'id' => 'ldap_attribute',
    ],
    'sort' => [
      'id' => 'ldap_attribute',
    ],
    'filter' => [
      'id' => 'ldap_attribute',
    ],
  ];
  $data['ldap_query']['attribute'] = [
    'title' => t('LDAP Attribute'),
    'help' => t('An attribute in an LDAP server.'),
    'argument' => [
      'id' => 'ldap_variable_attribute',
    ],
    'field' => [
      'id' => 'ldap_variable_attribute',
    ],
    'sort' => [
      'id' => 'ldap_variable_attribute',
    ],
    'filter' => [
      'id' => 'ldap_variable_attribute',
    ],
  ];

  $data['ldap_query']['image_attribute'] = [
    'title' => t('LDAP Image attribute'),
    'help' => t('Renders base64 images directly.'),
    'argument' => [
      'id' => 'ldap_variable_attribute',
    ],
    'field' => [
      'id' => 'ldap_variable_image_attribute',
    ],
    'sort' => [
      'id' => 'ldap_variable_attribute',
    ],
    'filter' => [
      'id' => 'ldap_variable_attribute',
    ],
  ];

  $data['ldap_query']['cn'] = [
    'title' => t('CN'),
    'help' => t('Common name attribute.'),
    'argument' => [
      'id' => 'ldap_attribute',
    ],
    'field' => [
      'id' => 'ldap_attribute',
    ],
    'sort' => [
      'id' => 'ldap_attribute',
    ],
    'filter' => [
      'id' => 'ldap_attribute',
    ],
  ];
  $data['ldap_query']['objectClass'] = [
    'title' => t('Object Class'),
    'help' => t('The LDAP object class attribute.'),
    'argument' => [
      'id' => 'ldap_attribute',
    ],
    'field' => [
      'id' => 'ldap_attribute',
    ],
    'sort' => [
      'id' => 'ldap_attribute',
    ],
    'filter' => [
      'id' => 'ldap_attribute',
    ],
  ];

  return $data;
}
