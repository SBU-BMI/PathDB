<?php

// @codingStandardsIgnoreFile

[
  'properties' =>
  [
    'sid' => 'openldap1',
    'name' => 'Test Open LDAP',
    'inDatabase' => TRUE,
    'status' => 1,
    'ldap_type' => 'openldap',
    'address' => 'ldap.hogwarts.edu',
    'port' => 389,
    'tls' => FALSE,
    'bind_method' => 1,
    'basedn' =>
    [
      0 => 'dc=hogwarts,dc=edu',
    ],
    'binddn' => 'cn=service-account,ou=people,dc=hogwarts,dc=edu',
    'bindpw' => 'goodpwd',
    'user_dn_expression' => NULL,
    'user_attr' => 'cn',
    'mail_attr' => 'mail',
    'mail_template' => NULL,
    'unique_persistent_attr' => 'guid',
    'unique_persistent_attr_binary' => FALSE,
    'ldap_to_drupal_user' => FALSE,
    'ldapToDrupalUserPhp' => NULL,
    'groupObjectClass' => 'groupofnames',
    'groupUserMembershipsAttrExists' => FALSE,
    'groupUserMembershipsAttr' => NULL,
    'groupMembershipsAttr' => 'member',
    'groupMembershipsAttrMatchingUserAttr' => 'dn',
    'search_pagination' => 0,
    'searchPageSize' => NULL,
  ],
  'methodResponses' =>
  [
    'connect' => 0,
  ],
  'search_results' =>
  [
    '(&(objectClass=group)(|(member=cn=gryffindor,ou=groups,dc=hogwarts,dc=edu)(member=cn=students,ou=groups,dc=hogwarts,dc=edu)(member=cn=honors students,ou=groups,dc=hogwarts,dc=edu)))' =>
    [
      'dc=hogwarts,dc=edu' =>
      [
        0 =>
        [
          'count' => 1,
          'dn' => 'cn=users,ou=groups,dc=hogwarts,dc=edu',
        ],
        'count' => 1,
      ],
    ],
    '(cn=hpotter)' =>
    [
      'dc=hogwarts,dc=edu' =>
      [
        0 =>
        [
          'count' => 1,
          'dn' => 'cn=hpotter,ou=people,dc=hogwarts,dc=edu',
          'FULLENTRY' => TRUE,
        ],
        'count' => 1,
      ],
    ],
    '(cn=hpotter-granger)' =>
    [
      'dc=hogwarts,dc=edu' =>
      [
        0 =>
        [
          'count' => 1,
          'dn' => 'cn=hpotter,ou=people,dc=hogwarts,dc=edu',
          'FULLENTRY' => TRUE,
        ],
        'count' => 1,
      ],
    ],
    '(cn=ssnape)' =>
    [
      'dc=hogwarts,dc=edu' =>
      [
        0 =>
        [
          'count' => 1,
          'dn' => 'cn=ssnape,ou=people,dc=hogwarts,dc=edu',
          'FULLENTRY' => TRUE,
        ],
        'count' => 1,
      ],
    ],
    '(cn=adumbledore)' =>
    [
      'dc=hogwarts,dc=edu' =>
      [
        0 =>
        [
          'count' => 1,
          'dn' => 'cn=adumbledore,ou=people,dc=hogwarts,dc=edu',
          'FULLENTRY' => TRUE,
        ],
        'count' => 1,
      ],
    ],
    '(&(objectClass=groupofnames)(member=cn=hpotter,ou=people,dc=hogwarts,dc=edu))' =>
    [
      'dc=hogwarts,dc=edu' =>
      [
        0 =>
        [
          'count' => 1,
          'dn' => 'cn=gryffindor,ou=groups,dc=hogwarts,dc=edu',
        ],
        1 =>
        [
          'count' => 1,
          'dn' => 'cn=students,ou=groups,dc=hogwarts,dc=edu',
        ],
        2 =>
        [
          'count' => 1,
          'dn' => 'cn=honors students,ou=groups,dc=hogwarts,dc=edu',
        ],
        'count' => 3,
      ],
    ],
    '(&(objectClass=groupofnames)(|(member=cn=gryffindor,ou=groups,dc=hogwarts,dc=edu)(member=cn=students,ou=groups,dc=hogwarts,dc=edu)(member=cn=honors students,ou=groups,dc=hogwarts,dc=edu)))' =>
    [
      'dc=hogwarts,dc=edu' =>
      [
        0 =>
        [
          'count' => 1,
          'dn' => 'cn=users,ou=groups,dc=hogwarts,dc=edu',
        ],
        'count' => 1,
      ],
    ],
  ],
  'users' =>
  [
    'cn=hpotter,ou=people,dc=hogwarts,dc=edu' =>
    [
      'attr' =>
      [
        'cn' =>
        [
          0 => 'hpotter',
          'count' => 1,
        ],
        'mail' =>
        [
          0 => 'hpotter@hogwarts.edu',
          'count' => 1,
        ],
        'uid' =>
        [
          0 => '1',
          'count' => 1,
        ],
        'guid' =>
        [
          0 => '101',
          'count' => 1,
        ],
        'sn' =>
        [
          0 => 'Potter',
          'count' => 1,
        ],
        'givenname' =>
        [
          0 => 'Harry',
          'count' => 1,
        ],
        'house' =>
        [
          0 => 'Gryffindor',
          'count' => 1,
        ],
        'department' =>
        [
          0 => '',
          'count' => 1,
        ],
        'faculty' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'staff' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'student' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'gpa' =>
        [
          0 => '3.8',
          'count' => 1,
        ],
        'probation' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'password' =>
        [
          0 => 'goodpwd',
          'count' => 1,
        ],
      ],
    ],
    'cn=hgrainger,ou=people,dc=hogwarts,dc=edu' =>
    [
      'attr' =>
      [
        'cn' =>
        [
          0 => 'hgrainger',
          'count' => 1,
        ],
        'mail' =>
        [
          0 => 'hgrainger@hogwarts.edu',
          'count' => 1,
        ],
        'uid' =>
        [
          0 => '2',
          'count' => 1,
        ],
        'guid' =>
        [
          0 => '102',
          'count' => 1,
        ],
        'sn' =>
        [
          0 => 'Granger',
          'count' => 1,
        ],
        'givenname' =>
        [
          0 => 'Hermione',
          'count' => 1,
        ],
        'house' =>
        [
          0 => 'Gryffindor',
          'count' => 1,
        ],
        'department' =>
        [
          0 => '',
          'count' => 1,
        ],
        'faculty' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'staff' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'student' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'gpa' =>
        [
          0 => '4',
          'count' => 1,
        ],
        'probation' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'password' =>
        [
          0 => 'goodpwd',
          'count' => 1,
        ],
      ],
    ],
    'cn=rweasley,ou=people,dc=hogwarts,dc=edu' =>
    [
      'attr' =>
      [
        'cn' =>
        [
          0 => 'rweasley',
          'count' => 1,
        ],
        'mail' =>
        [
          0 => 'rweasley@hogwarts.edu',
          'count' => 1,
        ],
        'uid' =>
        [
          0 => '3',
          'count' => 1,
        ],
        'guid' =>
        [
          0 => '103',
          'count' => 1,
        ],
        'sn' =>
        [
          0 => 'Weasley',
          'count' => 1,
        ],
        'givenname' =>
        [
          0 => 'Ron',
          'count' => 1,
        ],
        'house' =>
        [
          0 => 'Gryffindor',
          'count' => 1,
        ],
        'department' =>
        [
          0 => '',
          'count' => 1,
        ],
        'faculty' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'staff' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'student' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'gpa' =>
        [
          0 => '3.6',
          'count' => 1,
        ],
        'probation' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'password' =>
        [
          0 => 'goodpwd',
          'count' => 1,
        ],
      ],
    ],
    'cn=fweasley,ou=people,dc=hogwarts,dc=edu' =>
    [
      'attr' =>
      [
        'cn' =>
        [
          0 => 'fweasley',
          'count' => 1,
        ],
        'mail' =>
        [
          0 => 'fweasley@hogwarts.edu',
          'count' => 1,
        ],
        'uid' =>
        [
          0 => '4',
          'count' => 1,
        ],
        'guid' =>
        [
          0 => '104',
          'count' => 1,
        ],
        'sn' =>
        [
          0 => 'Weasley',
          'count' => 1,
        ],
        'givenname' =>
        [
          0 => 'Fred',
          'count' => 1,
        ],
        'house' =>
        [
          0 => 'Gryffindor',
          'count' => 1,
        ],
        'department' =>
        [
          0 => '',
          'count' => 1,
        ],
        'faculty' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'staff' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'student' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'gpa' =>
        [
          0 => '3',
          'count' => 1,
        ],
        'probation' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'password' =>
        [
          0 => 'goodpwd',
          'count' => 1,
        ],
      ],
    ],
    'cn=gweasley,ou=people,dc=hogwarts,dc=edu' =>
    [
      'attr' =>
      [
        'cn' =>
        [
          0 => 'gweasley',
          'count' => 1,
        ],
        'mail' =>
        [
          0 => 'gweasley@hogwarts.edu',
          'count' => 1,
        ],
        'uid' =>
        [
          0 => '5',
          'count' => 1,
        ],
        'guid' =>
        [
          0 => '105',
          'count' => 1,
        ],
        'sn' =>
        [
          0 => 'Weasley',
          'count' => 1,
        ],
        'givenname' =>
        [
          0 => 'George',
          'count' => 1,
        ],
        'house' =>
        [
          0 => 'Gryffindor',
          'count' => 1,
        ],
        'department' =>
        [
          0 => '',
          'count' => 1,
        ],
        'faculty' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'staff' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'student' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'gpa' =>
        [
          0 => '2.7',
          'count' => 1,
        ],
        'probation' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'password' =>
        [
          0 => 'goodpwd',
          'count' => 1,
        ],
      ],
    ],
    'cn=dmalfoy,ou=people,dc=hogwarts,dc=edu' =>
    [
      'attr' =>
      [
        'cn' =>
        [
          0 => 'dmalfoy',
          'count' => 1,
        ],
        'mail' =>
        [
          0 => 'dmalfoy@hogwarts.edu',
          'count' => 1,
        ],
        'uid' =>
        [
          0 => '6',
          'count' => 1,
        ],
        'guid' =>
        [
          0 => '106',
          'count' => 1,
        ],
        'sn' =>
        [
          0 => 'Malfoy',
          'count' => 1,
        ],
        'givenname' =>
        [
          0 => 'Draco',
          'count' => 1,
        ],
        'house' =>
        [
          0 => 'Slytherin',
          'count' => 1,
        ],
        'department' =>
        [
          0 => '',
          'count' => 1,
        ],
        'faculty' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'staff' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'student' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'gpa' =>
        [
          0 => '3.7',
          'count' => 1,
        ],
        'probation' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'password' =>
        [
          0 => 'goodpwd',
          'count' => 1,
        ],
      ],
    ],
    'cn=triddle,ou=people,dc=hogwarts,dc=edu' =>
    [
      'attr' =>
      [
        'cn' =>
        [
          0 => 'triddle',
          'count' => 1,
        ],
        'mail' =>
        [
          0 => 'triddle@hogwarts.edu',
          'count' => 1,
        ],
        'uid' =>
        [
          0 => '7',
          'count' => 1,
        ],
        'guid' =>
        [
          0 => '107',
          'count' => 1,
        ],
        'sn' =>
        [
          0 => 'Riddle',
          'count' => 1,
        ],
        'givenname' =>
        [
          0 => 'Tom',
          'count' => 1,
        ],
        'house' =>
        [
          0 => 'Slytherin',
          'count' => 1,
        ],
        'department' =>
        [
          0 => '',
          'count' => 1,
        ],
        'faculty' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'staff' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'student' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'gpa' =>
        [
          0 => '3.6',
          'count' => 1,
        ],
        'probation' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'password' =>
        [
          0 => 'goodpwd',
          'count' => 1,
        ],
      ],
    ],
    'cn=ggoyle,ou=people,dc=hogwarts,dc=edu' =>
    [
      'attr' =>
      [
        'cn' =>
        [
          0 => 'ggoyle',
          'count' => 1,
        ],
        'mail' =>
        [
          0 => 'ggoyle@hogwarts.edu',
          'count' => 1,
        ],
        'uid' =>
        [
          0 => '8',
          'count' => 1,
        ],
        'guid' =>
        [
          0 => '108',
          'count' => 1,
        ],
        'sn' =>
        [
          0 => 'Goyle',
          'count' => 1,
        ],
        'givenname' =>
        [
          0 => 'Gregory',
          'count' => 1,
        ],
        'house' =>
        [
          0 => 'Slytherin',
          'count' => 1,
        ],
        'department' =>
        [
          0 => '',
          'count' => 1,
        ],
        'faculty' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'staff' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'student' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'gpa' =>
        [
          0 => '3.3',
          'count' => 1,
        ],
        'probation' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'password' =>
        [
          0 => 'goodpwd',
          'count' => 1,
        ],
      ],
    ],
    'cn=ssnape,ou=people,dc=hogwarts,dc=edu' =>
    [
      'attr' =>
      [
        'cn' =>
        [
          0 => 'ssnape',
          'count' => 1,
        ],
        'mail' =>
        [
          0 => 'ssnape@hogwarts.edu',
          'count' => 1,
        ],
        'uid' =>
        [
          0 => '9',
          'count' => 1,
        ],
        'guid' =>
        [
          0 => '109',
          'count' => 1,
        ],
        'sn' =>
        [
          0 => 'Snape',
          'count' => 1,
        ],
        'givenname' =>
        [
          0 => 'Severus ',
          'count' => 1,
        ],
        'house' =>
        [
          0 => 'Slytherin',
          'count' => 1,
        ],
        'department' =>
        [
          0 => 'Defence Against the Dark Arts professor ',
          'count' => 1,
        ],
        'faculty' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'staff' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'student' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'gpa' =>
        [
          0 => '',
          'count' => 1,
        ],
        'probation' =>
        [
          0 => 0,
          'count' => 1,
        ],
        'password' =>
        [
          0 => 'goodpwd',
          'count' => 1,
        ],
      ],
    ],
    'cn=adumbledore,ou=people,dc=hogwarts,dc=edu' =>
    [
      'attr' =>
      [
        'cn' =>
        [
          0 => 'adumbledore',
          'count' => 1,
        ],
        'mail' =>
        [
          0 => 'adumbledore@hogwarts.edu',
          'count' => 1,
        ],
        'uid' =>
        [
          0 => '10',
          'count' => 1,
        ],
        'guid' =>
        [
          0 => '110',
          'count' => 1,
        ],
        'sn' =>
        [
          0 => 'Dumbledore',
          'count' => 1,
        ],
        'givenname' =>
        [
          0 => 'Albus',
          'count' => 1,
        ],
        'house' =>
        [
          0 => '',
          'count' => 1,
        ],
        'department' =>
        [
          0 => 'Head Master Of Hogwarts',
          'count' => 1,
        ],
        'faculty' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'staff' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'student' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'gpa' =>
        [
          0 => '',
          'count' => 1,
        ],
        'probation' =>
        [
          0 => 0,
          'count' => 1,
        ],
        'password' =>
        [
          0 => 'goodpwd',
          'count' => 1,
        ],
      ],
    ],
    'cn=mmcgonagall,ou=people,dc=hogwarts,dc=edu' =>
    [
      'attr' =>
      [
        'cn' =>
        [
          0 => 'mmcgonagall',
          'count' => 1,
        ],
        'mail' =>
        [
          0 => 'mmcgonagall@hogwarts.edu',
          'count' => 1,
        ],
        'uid' =>
        [
          0 => '11',
          'count' => 1,
        ],
        'guid' =>
        [
          0 => '111',
          'count' => 1,
        ],
        'sn' =>
        [
          0 => 'McGonagall',
          'count' => 1,
        ],
        'givenname' =>
        [
          0 => 'Minerva',
          'count' => 1,
        ],
        'house' =>
        [
          0 => 'Gryffindor',
          'count' => 1,
        ],
        'department' =>
        [
          0 => 'Tranfiguration/ Deputy Head Mistress',
          'count' => 1,
        ],
        'faculty' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'staff' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'student' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'gpa' =>
        [
          0 => '',
          'count' => 1,
        ],
        'probation' =>
        [
          0 => 0,
          'count' => 1,
        ],
        'password' =>
        [
          0 => 'goodpwd',
          'count' => 1,
        ],
      ],
    ],
    'cn=spomana,ou=people,dc=hogwarts,dc=edu' =>
    [
      'attr' =>
      [
        'cn' =>
        [
          0 => 'spomana',
          'count' => 1,
        ],
        'mail' =>
        [
          0 => 'spomana@hogwarts.edu',
          'count' => 1,
        ],
        'uid' =>
        [
          0 => '12',
          'count' => 1,
        ],
        'guid' =>
        [
          0 => '112',
          'count' => 1,
        ],
        'sn' =>
        [
          0 => 'Pomona',
          'count' => 1,
        ],
        'givenname' =>
        [
          0 => 'Sprout',
          'count' => 1,
        ],
        'house' =>
        [
          0 => 'Hufflepuff',
          'count' => 1,
        ],
        'department' =>
        [
          0 => 'Herbology',
          'count' => 1,
        ],
        'faculty' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'staff' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'student' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'gpa' =>
        [
          0 => '',
          'count' => 1,
        ],
        'probation' =>
        [
          0 => 0,
          'count' => 1,
        ],
        'password' =>
        [
          0 => 'goodpwd',
          'count' => 1,
        ],
      ],
    ],
    'cn=rhagrid,ou=people,dc=hogwarts,dc=edu' =>
    [
      'attr' =>
      [
        'cn' =>
        [
          0 => 'rhagrid',
          'count' => 1,
        ],
        'mail' =>
        [
          0 => 'rhagrid@hogwarts.edu',
          'count' => 1,
        ],
        'uid' =>
        [
          0 => '13',
          'count' => 1,
        ],
        'guid' =>
        [
          0 => '113',
          'count' => 1,
        ],
        'sn' =>
        [
          0 => 'Hagrid',
          'count' => 1,
        ],
        'givenname' =>
        [
          0 => 'Rubeus',
          'count' => 1,
        ],
        'house' =>
        [
          0 => 'Gryffindor',
          'count' => 1,
        ],
        'department' =>
        [
          0 => '',
          'count' => 1,
        ],
        'faculty' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'staff' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'student' =>
        [
          0 => 1,
          'count' => 1,
        ],
        'gpa' =>
        [
          0 => '',
          'count' => 1,
        ],
        'probation' =>
        [
          0 => 0,
          'count' => 1,
        ],
        'password' =>
        [
          0 => 'goodpwd',
          'count' => 1,
        ],
      ],
    ],
    'cn=service-account,ou=people,dc=hogwarts,dc=edu' =>
    [
      'attr' =>
      [
        'cn' =>
        [
          0 => 'service-account',
          'count' => 1,
        ],
        'mail' =>
        [
          0 => 'service-account@hogwarts.edu',
          'count' => 1,
        ],
        'uid' =>
        [
          0 => '19',
          'count' => 1,
        ],
        'guid' =>
        [
          0 => '119',
          'count' => 1,
        ],
        'sn' =>
        [
          0 => 'Service',
          'count' => 1,
        ],
        'givenname' =>
        [
          0 => 'Account',
          'count' => 1,
        ],
        'house' =>
        [
          0 => '',
          'count' => 1,
        ],
        'department' =>
        [
          0 => '',
          'count' => 1,
        ],
        'faculty' =>
        [
          0 => 0,
          'count' => 1,
        ],
        'staff' =>
        [
          0 => 0,
          'count' => 1,
        ],
        'student' =>
        [
          0 => 0,
          'count' => 1,
        ],
        'gpa' =>
        [
          0 => '',
          'count' => 1,
        ],
        'probation' =>
        [
          0 => 0,
          'count' => 1,
        ],
        'password' =>
        [
          0 => 'goodpwd',
          'count' => 1,
        ],
      ],
    ],
  ],
  'groups' =>
  [
    'cn=gryffindor,ou=groups,dc=hogwarts,dc=edu' =>
    [
      'attr' =>
      [
        'cn' =>
        [
          0 => 'gryffindor',
          'count' => 1,
        ],
        'gid' =>
        [
          0 => '1',
          'count' => 1,
        ],
        'guid' =>
        [
          0 => 201,
          'count' => 1,
        ],
        'member' =>
        [
          0 => 'cn=hpotter,ou=people,dc=hogwarts,dc=edu',
          1 => 'cn=hgrainger,ou=people,dc=hogwarts,dc=edu',
          2 => 'cn=rweasley,ou=people,dc=hogwarts,dc=edu',
          3 => 'cn=fweasley,ou=people,dc=hogwarts,dc=edu',
          4 => 'cn=gweasley,ou=people,dc=hogwarts,dc=edu',
          5 => 'cn=mmcgonagall,ou=people,dc=hogwarts,dc=edu',
          6 => 'cn=rhagrid,ou=people,dc=hogwarts,dc=edu',
          'count' => 7,
        ],
      ],
    ],
    'cn=slytherin,ou=groups,dc=hogwarts,dc=edu' =>
    [
      'attr' =>
      [
        'cn' =>
        [
          0 => 'slytherin',
          'count' => 1,
        ],
        'gid' =>
        [
          0 => '2',
          'count' => 1,
        ],
        'guid' =>
        [
          0 => 202,
          'count' => 1,
        ],
        'member' =>
        [
          0 => 'cn=dmalfoy,ou=people,dc=hogwarts,dc=edu',
          1 => 'cn=triddle,ou=people,dc=hogwarts,dc=edu',
          2 => 'cn=ggoyle,ou=people,dc=hogwarts,dc=edu',
          3 => 'cn=ssnape,ou=people,dc=hogwarts,dc=edu',
          4 => 'cn=dmalfoy,ou=people,dc=hogwarts,dc=edu',
          'count' => 5,
        ],
      ],
    ],
    'cn=hufflepuff,ou=groups,dc=hogwarts,dc=edu' =>
    [
      'attr' =>
      [
        'cn' =>
        [
          0 => 'hufflepuff',
          'count' => 1,
        ],
        'gid' =>
        [
          0 => '3',
          'count' => 1,
        ],
        'guid' =>
        [
          0 => 203,
          'count' => 1,
        ],
        'member' =>
        [
          0 => 'cn=spomana,ou=people,dc=hogwarts,dc=edu',
          'count' => 1,
        ],
      ],
    ],
    'cn=students,ou=groups,dc=hogwarts,dc=edu' =>
    [
      'attr' =>
      [
        'cn' =>
        [
          0 => 'students',
          'count' => 1,
        ],
        'gid' =>
        [
          0 => '4',
          'count' => 1,
        ],
        'guid' =>
        [
          0 => 204,
          'count' => 1,
        ],
        'member' =>
        [
          0 => 'cn=hpotter,ou=people,dc=hogwarts,dc=edu',
          1 => 'cn=hgrainger,ou=people,dc=hogwarts,dc=edu',
          2 => 'cn=rweasley,ou=people,dc=hogwarts,dc=edu',
          3 => 'cn=fweasley,ou=people,dc=hogwarts,dc=edu',
          4 => 'cn=gweasley,ou=people,dc=hogwarts,dc=edu',
          5 => 'cn=dmalfoy,ou=people,dc=hogwarts,dc=edu',
          6 => 'cn=ggoyle,ou=people,dc=hogwarts,dc=edu',
          'count' => 7,
        ],
      ],
    ],
    'cn=honors students,ou=groups,dc=hogwarts,dc=edu' =>
    [
      'attr' =>
      [
        'cn' =>
        [
          0 => 'honors students',
          'count' => 1,
        ],
        'gid' =>
        [
          0 => '5',
          'count' => 1,
        ],
        'guid' =>
        [
          0 => 205,
          'count' => 1,
        ],
        'member' =>
        [
          0 => 'cn=hpotter,ou=people,dc=hogwarts,dc=edu',
          1 => 'cn=hgrainger,ou=people,dc=hogwarts,dc=edu',
          'count' => 2,
        ],
      ],
    ],
    'cn=probation students,ou=groups,dc=hogwarts,dc=edu' =>
    [
      'attr' =>
      [
        'cn' =>
        [
          0 => 'probation students',
          'count' => 1,
        ],
        'gid' =>
        [
          0 => '6',
          'count' => 1,
        ],
        'guid' =>
        [
          0 => 206,
          'count' => 1,
        ],
        'member' =>
        [
          0 => 'cn=dmalfoy,ou=people,dc=hogwarts,dc=edu',
          1 => 'cn=ggoyle,ou=people,dc=hogwarts,dc=edu',
          'count' => 2,
        ],
      ],
    ],
    'cn=faculty,ou=groups,dc=hogwarts,dc=edu' =>
    [
      'attr' =>
      [
        'cn' =>
        [
          0 => 'faculty',
          'count' => 1,
        ],
        'gid' =>
        [
          0 => '7',
          'count' => 1,
        ],
        'guid' =>
        [
          0 => 207,
          'count' => 1,
        ],
        'member' =>
        [
          0 => 'cn=ssnape,ou=people,dc=hogwarts,dc=edu',
          1 => 'cn=adumbledore,ou=people,dc=hogwarts,dc=edu',
          2 => 'cn=mmcgonagall,ou=people,dc=hogwarts,dc=edu',
          3 => 'cn=spomana,ou=people,dc=hogwarts,dc=edu',
          'count' => 4,
        ],
      ],
    ],
    'cn=staff,ou=groups,dc=hogwarts,dc=edu' =>
    [
      'attr' =>
      [
        'cn' =>
        [
          0 => 'staff',
          'count' => 1,
        ],
        'gid' =>
        [
          0 => '8',
          'count' => 1,
        ],
        'guid' =>
        [
          0 => 208,
          'count' => 1,
        ],
        'member' =>
        [
          0 => 'cn=rhagrid,ou=people,dc=hogwarts,dc=edu',
          'count' => 1,
        ],
      ],
    ],
    'cn=users,ou=groups,dc=hogwarts,dc=edu' =>
    [
      'attr' =>
      [
        'cn' =>
        [
          0 => 'users',
          'count' => 1,
        ],
        'gid' =>
        [
          0 => '9',
          'count' => 1,
        ],
        'guid' =>
        [
          0 => 209,
          'count' => 1,
        ],
        'member' =>
        [
          0 => 'cn=students,ou=groups,dc=hogwarts,dc=edu',
          1 => 'cn=faculty,ou=groups,dc=hogwarts,dc=edu',
          2 => 'cn=staff,ou=groups,dc=hogwarts,dc=edu',
          'count' => 3,
        ],
      ],
    ],
  ],
  'ldap' =>
  [
    'cn=hpotter,ou=people,dc=hogwarts,dc=edu' =>
    [
      'cn' =>
      [
        0 => 'hpotter',
        'count' => 1,
      ],
      'mail' =>
      [
        0 => 'hpotter@hogwarts.edu',
        'count' => 1,
      ],
      'uid' =>
      [
        0 => '1',
        'count' => 1,
      ],
      'guid' =>
      [
        0 => '101',
        'count' => 1,
      ],
      'sn' =>
      [
        0 => 'Potter',
        'count' => 1,
      ],
      'givenname' =>
      [
        0 => 'Harry',
        'count' => 1,
      ],
      'house' =>
      [
        0 => 'Gryffindor',
        'count' => 1,
      ],
      'department' =>
      [
        0 => '',
        'count' => 1,
      ],
      'faculty' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'staff' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'student' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'gpa' =>
      [
        0 => '3.8',
        'count' => 1,
      ],
      'probation' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'password' =>
      [
        0 => 'goodpwd',
        'count' => 1,
      ],
      'count' => 14,
    ],
    'cn=hgrainger,ou=people,dc=hogwarts,dc=edu' =>
    [
      'cn' =>
      [
        0 => 'hgrainger',
        'count' => 1,
      ],
      'mail' =>
      [
        0 => 'hgrainger@hogwarts.edu',
        'count' => 1,
      ],
      'uid' =>
      [
        0 => '2',
        'count' => 1,
      ],
      'guid' =>
      [
        0 => '102',
        'count' => 1,
      ],
      'sn' =>
      [
        0 => 'Granger',
        'count' => 1,
      ],
      'givenname' =>
      [
        0 => 'Hermione',
        'count' => 1,
      ],
      'house' =>
      [
        0 => 'Gryffindor',
        'count' => 1,
      ],
      'department' =>
      [
        0 => '',
        'count' => 1,
      ],
      'faculty' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'staff' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'student' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'gpa' =>
      [
        0 => '4',
        'count' => 1,
      ],
      'probation' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'password' =>
      [
        0 => 'goodpwd',
        'count' => 1,
      ],
      'count' => 14,
    ],
    'cn=rweasley,ou=people,dc=hogwarts,dc=edu' =>
    [
      'cn' =>
      [
        0 => 'rweasley',
        'count' => 1,
      ],
      'mail' =>
      [
        0 => 'rweasley@hogwarts.edu',
        'count' => 1,
      ],
      'uid' =>
      [
        0 => '3',
        'count' => 1,
      ],
      'guid' =>
      [
        0 => '103',
        'count' => 1,
      ],
      'sn' =>
      [
        0 => 'Weasley',
        'count' => 1,
      ],
      'givenname' =>
      [
        0 => 'Ron',
        'count' => 1,
      ],
      'house' =>
      [
        0 => 'Gryffindor',
        'count' => 1,
      ],
      'department' =>
      [
        0 => '',
        'count' => 1,
      ],
      'faculty' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'staff' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'student' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'gpa' =>
      [
        0 => '3.6',
        'count' => 1,
      ],
      'probation' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'password' =>
      [
        0 => 'goodpwd',
        'count' => 1,
      ],
      'count' => 14,
    ],
    'cn=fweasley,ou=people,dc=hogwarts,dc=edu' =>
    [
      'cn' =>
      [
        0 => 'fweasley',
        'count' => 1,
      ],
      'mail' =>
      [
        0 => 'fweasley@hogwarts.edu',
        'count' => 1,
      ],
      'uid' =>
      [
        0 => '4',
        'count' => 1,
      ],
      'guid' =>
      [
        0 => '104',
        'count' => 1,
      ],
      'sn' =>
      [
        0 => 'Weasley',
        'count' => 1,
      ],
      'givenname' =>
      [
        0 => 'Fred',
        'count' => 1,
      ],
      'house' =>
      [
        0 => 'Gryffindor',
        'count' => 1,
      ],
      'department' =>
      [
        0 => '',
        'count' => 1,
      ],
      'faculty' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'staff' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'student' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'gpa' =>
      [
        0 => '3',
        'count' => 1,
      ],
      'probation' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'password' =>
      [
        0 => 'goodpwd',
        'count' => 1,
      ],
      'count' => 14,
    ],
    'cn=gweasley,ou=people,dc=hogwarts,dc=edu' =>
    [
      'cn' =>
      [
        0 => 'gweasley',
        'count' => 1,
      ],
      'mail' =>
      [
        0 => 'gweasley@hogwarts.edu',
        'count' => 1,
      ],
      'uid' =>
      [
        0 => '5',
        'count' => 1,
      ],
      'guid' =>
      [
        0 => '105',
        'count' => 1,
      ],
      'sn' =>
      [
        0 => 'Weasley',
        'count' => 1,
      ],
      'givenname' =>
      [
        0 => 'George',
        'count' => 1,
      ],
      'house' =>
      [
        0 => 'Gryffindor',
        'count' => 1,
      ],
      'department' =>
      [
        0 => '',
        'count' => 1,
      ],
      'faculty' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'staff' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'student' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'gpa' =>
      [
        0 => '2.7',
        'count' => 1,
      ],
      'probation' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'password' =>
      [
        0 => 'goodpwd',
        'count' => 1,
      ],
      'count' => 14,
    ],
    'cn=dmalfoy,ou=people,dc=hogwarts,dc=edu' =>
    [
      'cn' =>
      [
        0 => 'dmalfoy',
        'count' => 1,
      ],
      'mail' =>
      [
        0 => 'dmalfoy@hogwarts.edu',
        'count' => 1,
      ],
      'uid' =>
      [
        0 => '6',
        'count' => 1,
      ],
      'guid' =>
      [
        0 => '106',
        'count' => 1,
      ],
      'sn' =>
      [
        0 => 'Malfoy',
        'count' => 1,
      ],
      'givenname' =>
      [
        0 => 'Draco',
        'count' => 1,
      ],
      'house' =>
      [
        0 => 'Slytherin',
        'count' => 1,
      ],
      'department' =>
      [
        0 => '',
        'count' => 1,
      ],
      'faculty' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'staff' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'student' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'gpa' =>
      [
        0 => '3.7',
        'count' => 1,
      ],
      'probation' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'password' =>
      [
        0 => 'goodpwd',
        'count' => 1,
      ],
      'count' => 14,
    ],
    'cn=triddle,ou=people,dc=hogwarts,dc=edu' =>
    [
      'cn' =>
      [
        0 => 'triddle',
        'count' => 1,
      ],
      'mail' =>
      [
        0 => 'triddle@hogwarts.edu',
        'count' => 1,
      ],
      'uid' =>
      [
        0 => '7',
        'count' => 1,
      ],
      'guid' =>
      [
        0 => '107',
        'count' => 1,
      ],
      'sn' =>
      [
        0 => 'Riddle',
        'count' => 1,
      ],
      'givenname' =>
      [
        0 => 'Tom',
        'count' => 1,
      ],
      'house' =>
      [
        0 => 'Slytherin',
        'count' => 1,
      ],
      'department' =>
      [
        0 => '',
        'count' => 1,
      ],
      'faculty' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'staff' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'student' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'gpa' =>
      [
        0 => '3.6',
        'count' => 1,
      ],
      'probation' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'password' =>
      [
        0 => 'goodpwd',
        'count' => 1,
      ],
      'count' => 14,
    ],
    'cn=ggoyle,ou=people,dc=hogwarts,dc=edu' =>
    [
      'cn' =>
      [
        0 => 'ggoyle',
        'count' => 1,
      ],
      'mail' =>
      [
        0 => 'ggoyle@hogwarts.edu',
        'count' => 1,
      ],
      'uid' =>
      [
        0 => '8',
        'count' => 1,
      ],
      'guid' =>
      [
        0 => '108',
        'count' => 1,
      ],
      'sn' =>
      [
        0 => 'Goyle',
        'count' => 1,
      ],
      'givenname' =>
      [
        0 => 'Gregory',
        'count' => 1,
      ],
      'house' =>
      [
        0 => 'Slytherin',
        'count' => 1,
      ],
      'department' =>
      [
        0 => '',
        'count' => 1,
      ],
      'faculty' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'staff' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'student' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'gpa' =>
      [
        0 => '3.3',
        'count' => 1,
      ],
      'probation' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'password' =>
      [
        0 => 'goodpwd',
        'count' => 1,
      ],
      'count' => 14,
    ],
    'cn=ssnape,ou=people,dc=hogwarts,dc=edu' =>
    [
      'cn' =>
      [
        0 => 'ssnape',
        'count' => 1,
      ],
      'mail' =>
      [
        0 => 'ssnape@hogwarts.edu',
        'count' => 1,
      ],
      'uid' =>
      [
        0 => '9',
        'count' => 1,
      ],
      'guid' =>
      [
        0 => '109',
        'count' => 1,
      ],
      'sn' =>
      [
        0 => 'Snape',
        'count' => 1,
      ],
      'givenname' =>
      [
        0 => 'Severus ',
        'count' => 1,
      ],
      'house' =>
      [
        0 => 'Slytherin',
        'count' => 1,
      ],
      'department' =>
      [
        0 => 'Defence Against the Dark Arts professor ',
        'count' => 1,
      ],
      'faculty' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'staff' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'student' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'gpa' =>
      [
        0 => '',
        'count' => 1,
      ],
      'probation' =>
      [
        0 => 0,
        'count' => 1,
      ],
      'password' =>
      [
        0 => 'goodpwd',
        'count' => 1,
      ],
      'count' => 14,
    ],
    'cn=adumbledore,ou=people,dc=hogwarts,dc=edu' =>
    [
      'cn' =>
      [
        0 => 'adumbledore',
        'count' => 1,
      ],
      'mail' =>
      [
        0 => 'adumbledore@hogwarts.edu',
        'count' => 1,
      ],
      'uid' =>
      [
        0 => '10',
        'count' => 1,
      ],
      'guid' =>
      [
        0 => '110',
        'count' => 1,
      ],
      'sn' =>
      [
        0 => 'Dumbledore',
        'count' => 1,
      ],
      'givenname' =>
      [
        0 => 'Albus',
        'count' => 1,
      ],
      'house' =>
      [
        0 => '',
        'count' => 1,
      ],
      'department' =>
      [
        0 => 'Head Master Of Hogwarts',
        'count' => 1,
      ],
      'faculty' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'staff' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'student' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'gpa' =>
      [
        0 => '',
        'count' => 1,
      ],
      'probation' =>
      [
        0 => 0,
        'count' => 1,
      ],
      'password' =>
      [
        0 => 'goodpwd',
        'count' => 1,
      ],
      'count' => 14,
    ],
    'cn=mmcgonagall,ou=people,dc=hogwarts,dc=edu' =>
    [
      'cn' =>
      [
        0 => 'mmcgonagall',
        'count' => 1,
      ],
      'mail' =>
      [
        0 => 'mmcgonagall@hogwarts.edu',
        'count' => 1,
      ],
      'uid' =>
      [
        0 => '11',
        'count' => 1,
      ],
      'guid' =>
      [
        0 => '111',
        'count' => 1,
      ],
      'sn' =>
      [
        0 => 'McGonagall',
        'count' => 1,
      ],
      'givenname' =>
      [
        0 => 'Minerva',
        'count' => 1,
      ],
      'house' =>
      [
        0 => 'Gryffindor',
        'count' => 1,
      ],
      'department' =>
      [
        0 => 'Tranfiguration/ Deputy Head Mistress',
        'count' => 1,
      ],
      'faculty' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'staff' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'student' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'gpa' =>
      [
        0 => '',
        'count' => 1,
      ],
      'probation' =>
      [
        0 => 0,
        'count' => 1,
      ],
      'password' =>
      [
        0 => 'goodpwd',
        'count' => 1,
      ],
      'count' => 14,
    ],
    'cn=spomana,ou=people,dc=hogwarts,dc=edu' =>
    [
      'cn' =>
      [
        0 => 'spomana',
        'count' => 1,
      ],
      'mail' =>
      [
        0 => 'spomana@hogwarts.edu',
        'count' => 1,
      ],
      'uid' =>
      [
        0 => '12',
        'count' => 1,
      ],
      'guid' =>
      [
        0 => '112',
        'count' => 1,
      ],
      'sn' =>
      [
        0 => 'Pomona',
        'count' => 1,
      ],
      'givenname' =>
      [
        0 => 'Sprout',
        'count' => 1,
      ],
      'house' =>
      [
        0 => 'Hufflepuff',
        'count' => 1,
      ],
      'department' =>
      [
        0 => 'Herbology',
        'count' => 1,
      ],
      'faculty' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'staff' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'student' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'gpa' =>
      [
        0 => '',
        'count' => 1,
      ],
      'probation' =>
      [
        0 => 0,
        'count' => 1,
      ],
      'password' =>
      [
        0 => 'goodpwd',
        'count' => 1,
      ],
      'count' => 14,
    ],
    'cn=rhagrid,ou=people,dc=hogwarts,dc=edu' =>
    [
      'cn' =>
      [
        0 => 'rhagrid',
        'count' => 1,
      ],
      'mail' =>
      [
        0 => 'rhagrid@hogwarts.edu',
        'count' => 1,
      ],
      'uid' =>
      [
        0 => '13',
        'count' => 1,
      ],
      'guid' =>
      [
        0 => '113',
        'count' => 1,
      ],
      'sn' =>
      [
        0 => 'Hagrid',
        'count' => 1,
      ],
      'givenname' =>
      [
        0 => 'Rubeus',
        'count' => 1,
      ],
      'house' =>
      [
        0 => 'Gryffindor',
        'count' => 1,
      ],
      'department' =>
      [
        0 => '',
        'count' => 1,
      ],
      'faculty' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'staff' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'student' =>
      [
        0 => 1,
        'count' => 1,
      ],
      'gpa' =>
      [
        0 => '',
        'count' => 1,
      ],
      'probation' =>
      [
        0 => 0,
        'count' => 1,
      ],
      'password' =>
      [
        0 => 'goodpwd',
        'count' => 1,
      ],
      'count' => 14,
    ],
    'cn=service-account,ou=people,dc=hogwarts,dc=edu' =>
    [
      'cn' =>
      [
        0 => 'service-account',
        'count' => 1,
      ],
      'mail' =>
      [
        0 => 'service-account@hogwarts.edu',
        'count' => 1,
      ],
      'uid' =>
      [
        0 => '19',
        'count' => 1,
      ],
      'guid' =>
      [
        0 => '119',
        'count' => 1,
      ],
      'sn' =>
      [
        0 => 'Service',
        'count' => 1,
      ],
      'givenname' =>
      [
        0 => 'Account',
        'count' => 1,
      ],
      'house' =>
      [
        0 => '',
        'count' => 1,
      ],
      'department' =>
      [
        0 => '',
        'count' => 1,
      ],
      'faculty' =>
      [
        0 => 0,
        'count' => 1,
      ],
      'staff' =>
      [
        0 => 0,
        'count' => 1,
      ],
      'student' =>
      [
        0 => 0,
        'count' => 1,
      ],
      'gpa' =>
      [
        0 => '',
        'count' => 1,
      ],
      'probation' =>
      [
        0 => 0,
        'count' => 1,
      ],
      'password' =>
      [
        0 => 'goodpwd',
        'count' => 1,
      ],
      'count' => 14,
    ],
    'cn=gryffindor,ou=groups,dc=hogwarts,dc=edu' =>
    [
      'cn' =>
      [
        0 => 'gryffindor',
        'count' => 1,
      ],
      'gid' =>
      [
        0 => '1',
        'count' => 1,
      ],
      'guid' =>
      [
        0 => 201,
        'count' => 1,
      ],
      'member' =>
      [
        0 => 'cn=hpotter,ou=people,dc=hogwarts,dc=edu',
        1 => 'cn=hgrainger,ou=people,dc=hogwarts,dc=edu',
        2 => 'cn=rweasley,ou=people,dc=hogwarts,dc=edu',
        3 => 'cn=fweasley,ou=people,dc=hogwarts,dc=edu',
        4 => 'cn=gweasley,ou=people,dc=hogwarts,dc=edu',
        5 => 'cn=mmcgonagall,ou=people,dc=hogwarts,dc=edu',
        6 => 'cn=rhagrid,ou=people,dc=hogwarts,dc=edu',
        'count' => 7,
      ],
    ],
    'cn=slytherin,ou=groups,dc=hogwarts,dc=edu' =>
    [
      'cn' =>
      [
        0 => 'slytherin',
        'count' => 1,
      ],
      'gid' =>
      [
        0 => '2',
        'count' => 1,
      ],
      'guid' =>
      [
        0 => 202,
        'count' => 1,
      ],
      'member' =>
      [
        0 => 'cn=dmalfoy,ou=people,dc=hogwarts,dc=edu',
        1 => 'cn=triddle,ou=people,dc=hogwarts,dc=edu',
        2 => 'cn=ggoyle,ou=people,dc=hogwarts,dc=edu',
        3 => 'cn=ssnape,ou=people,dc=hogwarts,dc=edu',
        4 => 'cn=dmalfoy,ou=people,dc=hogwarts,dc=edu',
        'count' => 5,
      ],
    ],
    'cn=hufflepuff,ou=groups,dc=hogwarts,dc=edu' =>
    [
      'cn' =>
      [
        0 => 'hufflepuff',
        'count' => 1,
      ],
      'gid' =>
      [
        0 => '3',
        'count' => 1,
      ],
      'guid' =>
      [
        0 => 203,
        'count' => 1,
      ],
      'member' =>
      [
        0 => 'cn=spomana,ou=people,dc=hogwarts,dc=edu',
        'count' => 1,
      ],
    ],
    'cn=students,ou=groups,dc=hogwarts,dc=edu' =>
    [
      'cn' =>
      [
        0 => 'students',
        'count' => 1,
      ],
      'gid' =>
      [
        0 => '4',
        'count' => 1,
      ],
      'guid' =>
      [
        0 => 204,
        'count' => 1,
      ],
      'member' =>
      [
        0 => 'cn=hpotter,ou=people,dc=hogwarts,dc=edu',
        1 => 'cn=hgrainger,ou=people,dc=hogwarts,dc=edu',
        2 => 'cn=rweasley,ou=people,dc=hogwarts,dc=edu',
        3 => 'cn=fweasley,ou=people,dc=hogwarts,dc=edu',
        4 => 'cn=gweasley,ou=people,dc=hogwarts,dc=edu',
        5 => 'cn=dmalfoy,ou=people,dc=hogwarts,dc=edu',
        6 => 'cn=ggoyle,ou=people,dc=hogwarts,dc=edu',
        'count' => 7,
      ],
    ],
    'cn=honors students,ou=groups,dc=hogwarts,dc=edu' =>
    [
      'cn' =>
      [
        0 => 'honors students',
        'count' => 1,
      ],
      'gid' =>
      [
        0 => '5',
        'count' => 1,
      ],
      'guid' =>
      [
        0 => 205,
        'count' => 1,
      ],
      'member' =>
      [
        0 => 'cn=hpotter,ou=people,dc=hogwarts,dc=edu',
        1 => 'cn=hgrainger,ou=people,dc=hogwarts,dc=edu',
        'count' => 2,
      ],
    ],
    'cn=probation students,ou=groups,dc=hogwarts,dc=edu' =>
    [
      'cn' =>
      [
        0 => 'probation students',
        'count' => 1,
      ],
      'gid' =>
      [
        0 => '6',
        'count' => 1,
      ],
      'guid' =>
      [
        0 => 206,
        'count' => 1,
      ],
      'member' =>
      [
        0 => 'cn=dmalfoy,ou=people,dc=hogwarts,dc=edu',
        1 => 'cn=ggoyle,ou=people,dc=hogwarts,dc=edu',
        'count' => 2,
      ],
    ],
    'cn=faculty,ou=groups,dc=hogwarts,dc=edu' =>
    [
      'cn' =>
      [
        0 => 'faculty',
        'count' => 1,
      ],
      'gid' =>
      [
        0 => '7',
        'count' => 1,
      ],
      'guid' =>
      [
        0 => 207,
        'count' => 1,
      ],
      'member' =>
      [
        0 => 'cn=ssnape,ou=people,dc=hogwarts,dc=edu',
        1 => 'cn=adumbledore,ou=people,dc=hogwarts,dc=edu',
        2 => 'cn=mmcgonagall,ou=people,dc=hogwarts,dc=edu',
        3 => 'cn=spomana,ou=people,dc=hogwarts,dc=edu',
        'count' => 4,
      ],
    ],
    'cn=staff,ou=groups,dc=hogwarts,dc=edu' =>
    [
      'cn' =>
      [
        0 => 'staff',
        'count' => 1,
      ],
      'gid' =>
      [
        0 => '8',
        'count' => 1,
      ],
      'guid' =>
      [
        0 => 208,
        'count' => 1,
      ],
      'member' =>
      [
        0 => 'cn=rhagrid,ou=people,dc=hogwarts,dc=edu',
        'count' => 1,
      ],
    ],
    'cn=users,ou=groups,dc=hogwarts,dc=edu' =>
    [
      'cn' =>
      [
        0 => 'users',
        'count' => 1,
      ],
      'gid' =>
      [
        0 => '9',
        'count' => 1,
      ],
      'guid' =>
      [
        0 => 209,
        'count' => 1,
      ],
      'member' =>
      [
        0 => 'cn=students,ou=groups,dc=hogwarts,dc=edu',
        1 => 'cn=faculty,ou=groups,dc=hogwarts,dc=edu',
        2 => 'cn=staff,ou=groups,dc=hogwarts,dc=edu',
        'count' => 3,
      ],
    ],
  ],
  'csv' =>
  [
    'groups' =>
    [
      201 =>
      [
        'guid' => '201',
        'gid' => '1',
        'cn' => 'gryffindor',
        'dn' => 'cn=gryffindor,ou=groups,dc=hogwarts,dc=edu',
      ],
      202 =>
      [
        'guid' => '202',
        'gid' => '2',
        'cn' => 'slytherin',
        'dn' => 'cn=slytherin,ou=groups,dc=hogwarts,dc=edu',
      ],
      203 =>
      [
        'guid' => '203',
        'gid' => '3',
        'cn' => 'hufflepuff',
        'dn' => 'cn=hufflepuff,ou=groups,dc=hogwarts,dc=edu',
      ],
      204 =>
      [
        'guid' => '204',
        'gid' => '4',
        'cn' => 'students',
        'dn' => 'cn=students,ou=groups,dc=hogwarts,dc=edu',
      ],
      205 =>
      [
        'guid' => '205',
        'gid' => '5',
        'cn' => 'honors students',
        'dn' => 'cn=honors students,ou=groups,dc=hogwarts,dc=edu',
      ],
      206 =>
      [
        'guid' => '206',
        'gid' => '6',
        'cn' => 'probation students',
        'dn' => 'cn=probation students,ou=groups,dc=hogwarts,dc=edu',
      ],
      207 =>
      [
        'guid' => '207',
        'gid' => '7',
        'cn' => 'faculty',
        'dn' => 'cn=faculty,ou=groups,dc=hogwarts,dc=edu',
      ],
      208 =>
      [
        'guid' => '208',
        'gid' => '8',
        'cn' => 'staff',
        'dn' => 'cn=staff,ou=groups,dc=hogwarts,dc=edu',
      ],
      209 =>
      [
        'guid' => '209',
        'gid' => '9',
        'cn' => 'users',
        'dn' => 'cn=users,ou=groups,dc=hogwarts,dc=edu',
      ],
    ],
    'users' =>
    [
      101 =>
      [
        'guid' => '101',
        'uid' => '1',
        'cn' => 'hpotter',
        'lname' => 'Potter',
        'fname' => 'Harry',
        'house' => 'Gryffindor',
        'department' => '',
        'faculty' => 'N',
        'staff' => 'N',
        'student' => 'Y',
        'gpa' => '3.8',
        'probation' => 'N',
        'dn' => 'cn=hpotter,ou=people,dc=hogwarts,dc=edu',
      ],
      102 =>
      [
        'guid' => '102',
        'uid' => '2',
        'cn' => 'hgrainger',
        'lname' => 'Granger',
        'fname' => 'Hermione',
        'house' => 'Gryffindor',
        'department' => '',
        'faculty' => 'N',
        'staff' => 'N',
        'student' => 'Y',
        'gpa' => '4',
        'probation' => 'N',
        'dn' => 'cn=hgrainger,ou=people,dc=hogwarts,dc=edu',
      ],
      103 =>
      [
        'guid' => '103',
        'uid' => '3',
        'cn' => 'rweasley',
        'lname' => 'Weasley',
        'fname' => 'Ron',
        'house' => 'Gryffindor',
        'department' => '',
        'faculty' => 'N',
        'staff' => 'N',
        'student' => 'Y',
        'gpa' => '3.6',
        'probation' => 'N',
        'dn' => 'cn=rweasley,ou=people,dc=hogwarts,dc=edu',
      ],
      104 =>
      [
        'guid' => '104',
        'uid' => '4',
        'cn' => 'fweasley',
        'lname' => 'Weasley',
        'fname' => 'Fred',
        'house' => 'Gryffindor',
        'department' => '',
        'faculty' => 'N',
        'staff' => 'N',
        'student' => 'Y',
        'gpa' => '3',
        'probation' => 'N',
        'dn' => 'cn=fweasley,ou=people,dc=hogwarts,dc=edu',
      ],
      105 =>
      [
        'guid' => '105',
        'uid' => '5',
        'cn' => 'gweasley',
        'lname' => 'Weasley',
        'fname' => 'George',
        'house' => 'Gryffindor',
        'department' => '',
        'faculty' => 'N',
        'staff' => 'N',
        'student' => 'Y',
        'gpa' => '2.7',
        'probation' => 'N',
        'dn' => 'cn=gweasley,ou=people,dc=hogwarts,dc=edu',
      ],
      106 =>
      [
        'guid' => '106',
        'uid' => '6',
        'cn' => 'dmalfoy',
        'lname' => 'Malfoy',
        'fname' => 'Draco',
        'house' => 'Slytherin',
        'department' => '',
        'faculty' => 'N',
        'staff' => 'N',
        'student' => 'Y',
        'gpa' => '3.7',
        'probation' => 'Y',
        'dn' => 'cn=dmalfoy,ou=people,dc=hogwarts,dc=edu',
      ],
      107 =>
      [
        'guid' => '107',
        'uid' => '7',
        'cn' => 'triddle',
        'lname' => 'Riddle',
        'fname' => 'Tom',
        'house' => 'Slytherin',
        'department' => '',
        'faculty' => 'N',
        'staff' => 'N',
        'student' => 'Y',
        'gpa' => '3.6',
        'probation' => 'N',
        'dn' => 'cn=triddle,ou=people,dc=hogwarts,dc=edu',
      ],
      108 =>
      [
        'guid' => '108',
        'uid' => '8',
        'cn' => 'ggoyle',
        'lname' => 'Goyle',
        'fname' => 'Gregory',
        'house' => 'Slytherin',
        'department' => '',
        'faculty' => 'N',
        'staff' => 'N',
        'student' => 'Y',
        'gpa' => '3.3',
        'probation' => 'Y',
        'dn' => 'cn=ggoyle,ou=people,dc=hogwarts,dc=edu',
      ],
      109 =>
      [
        'guid' => '109',
        'uid' => '9',
        'cn' => 'ssnape',
        'lname' => 'Snape',
        'fname' => 'Severus ',
        'house' => 'Slytherin',
        'department' => 'Defence Against the Dark Arts professor ',
        'faculty' => 'Y',
        'staff' => 'Y',
        'student' => 'N',
        'gpa' => '',
        'probation' => '',
        'dn' => 'cn=ssnape,ou=people,dc=hogwarts,dc=edu',
      ],
      110 =>
      [
        'guid' => '110',
        'uid' => '10',
        'cn' => 'adumbledore',
        'lname' => 'Dumbledore',
        'fname' => 'Albus',
        'house' => '',
        'department' => 'Head Master Of Hogwarts',
        'faculty' => 'Y',
        'staff' => 'Y',
        'student' => 'N',
        'gpa' => '',
        'probation' => '',
        'dn' => 'cn=adumbledore,ou=people,dc=hogwarts,dc=edu',
      ],
      111 =>
      [
        'guid' => '111',
        'uid' => '11',
        'cn' => 'mmcgonagall',
        'lname' => 'McGonagall',
        'fname' => 'Minerva',
        'house' => 'Gryffindor',
        'department' => 'Tranfiguration/ Deputy Head Mistress',
        'faculty' => 'Y',
        'staff' => 'Y',
        'student' => 'N',
        'gpa' => '',
        'probation' => '',
        'dn' => 'cn=mmcgonagall,ou=people,dc=hogwarts,dc=edu',
      ],
      112 =>
      [
        'guid' => '112',
        'uid' => '12',
        'cn' => 'spomana',
        'lname' => 'Pomona',
        'fname' => 'Sprout',
        'house' => 'Hufflepuff',
        'department' => 'Herbology',
        'faculty' => 'Y',
        'staff' => 'Y',
        'student' => 'N',
        'gpa' => '',
        'probation' => '',
        'dn' => 'cn=spomana,ou=people,dc=hogwarts,dc=edu',
      ],
      113 =>
      [
        'guid' => '113',
        'uid' => '13',
        'cn' => 'rhagrid',
        'lname' => 'Hagrid',
        'fname' => 'Rubeus',
        'house' => 'Gryffindor',
        'department' => '',
        'faculty' => 'N',
        'staff' => 'Y',
        'student' => 'N',
        'gpa' => '',
        'probation' => '',
        'dn' => 'cn=rhagrid,ou=people,dc=hogwarts,dc=edu',
      ],
      119 =>
      [
        'guid' => '119',
        'uid' => '19',
        'cn' => 'service-account',
        'lname' => 'Service',
        'fname' => 'Account',
        'house' => '',
        'department' => '',
        'faculty' => '',
        'staff' => '',
        'student' => '',
        'gpa' => '',
        'probation' => '',
        'dn' => 'cn=service-account,ou=people,dc=hogwarts,dc=edu',
      ],
    ],
    'memberships' =>
    [
      1 =>
      [
        'membershipid' => '1',
        'gid' => '1',
        'group_cn' => 'gryffindor',
        'member_guid' => '101',
        'group_guid' => '201',
      ],
      2 =>
      [
        'membershipid' => '2',
        'gid' => '1',
        'group_cn' => 'gryffindor',
        'member_guid' => '102',
        'group_guid' => '201',
      ],
      3 =>
      [
        'membershipid' => '3',
        'gid' => '1',
        'group_cn' => 'gryffindor',
        'member_guid' => '103',
        'group_guid' => '201',
      ],
      4 =>
      [
        'membershipid' => '4',
        'gid' => '1',
        'group_cn' => 'gryffindor',
        'member_guid' => '104',
        'group_guid' => '201',
      ],
      5 =>
      [
        'membershipid' => '5',
        'gid' => '1',
        'group_cn' => 'gryffindor',
        'member_guid' => '105',
        'group_guid' => '201',
      ],
      6 =>
      [
        'membershipid' => '6',
        'gid' => '1',
        'group_cn' => 'gryffindor',
        'member_guid' => '111',
        'group_guid' => '201',
      ],
      7 =>
      [
        'membershipid' => '7',
        'gid' => '1',
        'group_cn' => 'gryffindor',
        'member_guid' => '113',
        'group_guid' => '201',
      ],
      8 =>
      [
        'membershipid' => '8',
        'gid' => '2',
        'group_cn' => 'slytherin',
        'member_guid' => '106',
        'group_guid' => '202',
      ],
      9 =>
      [
        'membershipid' => '9',
        'gid' => '2',
        'group_cn' => 'slytherin',
        'member_guid' => '107',
        'group_guid' => '202',
      ],
      10 =>
      [
        'membershipid' => '10',
        'gid' => '2',
        'group_cn' => 'slytherin',
        'member_guid' => '108',
        'group_guid' => '202',
      ],
      11 =>
      [
        'membershipid' => '11',
        'gid' => '2',
        'group_cn' => 'slytherin',
        'member_guid' => '109',
        'group_guid' => '202',
      ],
      12 =>
      [
        'membershipid' => '12',
        'gid' => '2',
        'group_cn' => 'slytherin',
        'member_guid' => '106',
        'group_guid' => '202',
      ],
      13 =>
      [
        'membershipid' => '13',
        'gid' => '3',
        'group_cn' => 'hufflepuff',
        'member_guid' => '112',
        'group_guid' => '203',
      ],
      14 =>
      [
        'membershipid' => '14',
        'gid' => '4',
        'group_cn' => 'students',
        'member_guid' => '101',
        'group_guid' => '204',
      ],
      15 =>
      [
        'membershipid' => '15',
        'gid' => '4',
        'group_cn' => 'students',
        'member_guid' => '102',
        'group_guid' => '204',
      ],
      16 =>
      [
        'membershipid' => '16',
        'gid' => '4',
        'group_cn' => 'students',
        'member_guid' => '103',
        'group_guid' => '204',
      ],
      17 =>
      [
        'membershipid' => '17',
        'gid' => '4',
        'group_cn' => 'students',
        'member_guid' => '104',
        'group_guid' => '204',
      ],
      18 =>
      [
        'membershipid' => '18',
        'gid' => '4',
        'group_cn' => 'students',
        'member_guid' => '105',
        'group_guid' => '204',
      ],
      19 =>
      [
        'membershipid' => '19',
        'gid' => '4',
        'group_cn' => 'students',
        'member_guid' => '106',
        'group_guid' => '204',
      ],
      20 =>
      [
        'membershipid' => '20',
        'gid' => '4',
        'group_cn' => 'students',
        'member_guid' => '108',
        'group_guid' => '204',
      ],
      21 =>
      [
        'membershipid' => '21',
        'gid' => '5',
        'group_cn' => 'honors students',
        'member_guid' => '101',
        'group_guid' => '205',
      ],
      22 =>
      [
        'membershipid' => '22',
        'gid' => '5',
        'group_cn' => 'honors students',
        'member_guid' => '102',
        'group_guid' => '205',
      ],
      23 =>
      [
        'membershipid' => '23',
        'gid' => '6',
        'group_cn' => 'probation students',
        'member_guid' => '106',
        'group_guid' => '206',
      ],
      24 =>
      [
        'membershipid' => '24',
        'gid' => '6',
        'group_cn' => 'probation students',
        'member_guid' => '108',
        'group_guid' => '206',
      ],
      25 =>
      [
        'membershipid' => '25',
        'gid' => '7',
        'group_cn' => 'faculty',
        'member_guid' => '109',
        'group_guid' => '207',
      ],
      26 =>
      [
        'membershipid' => '26',
        'gid' => '7',
        'group_cn' => 'faculty',
        'member_guid' => '110',
        'group_guid' => '207',
      ],
      27 =>
      [
        'membershipid' => '27',
        'gid' => '7',
        'group_cn' => 'faculty',
        'member_guid' => '111',
        'group_guid' => '207',
      ],
      28 =>
      [
        'membershipid' => '28',
        'gid' => '7',
        'group_cn' => 'faculty',
        'member_guid' => '112',
        'group_guid' => '207',
      ],
      29 =>
      [
        'membershipid' => '29',
        'gid' => '8',
        'group_cn' => 'staff',
        'member_guid' => '113',
        'group_guid' => '208',
      ],
      30 =>
      [
        'membershipid' => '30',
        'gid' => '9',
        'group_cn' => 'users',
        'member_guid' => '204',
        'group_guid' => '209',
      ],
      31 =>
      [
        'membershipid' => '31',
        'gid' => '9',
        'group_cn' => 'users',
        'member_guid' => '207',
        'group_guid' => '209',
      ],
      32 =>
      [
        'membershipid' => '32',
        'gid' => '9',
        'group_cn' => 'users',
        'member_guid' => '208',
        'group_guid' => '209',
      ],
    ],
    'conf' =>
    [
      'hogwarts' =>
      [
        'id' => 'hogwarts',
        'mailhostname' => 'hogwarts.edu',
        'userbasedn' => 'ou=people,dc=hogwarts,dc=edu',
        'groupbasedn' => 'ou=groups,dc=hogwarts,dc=edu',
      ],
    ],
  ],
];
