# Authentication

LDAP Authentication lets people log in with directory credentials. It uses
[External Authentication](https://www.drupal.org/project/externalauth) to
map directory identities to Drupal accounts and LDAP Users to create or
update those accounts.

Configure at `/admin/config/people/ldap/authentication`. Select one or more
**authentication servers**. Drupal tries them in order until one succeeds.
Most sites select a single server.

## Mixed vs exclusive

| Mode | Behavior |
| ---- | --------- |
| **Mixed** | Drupal authentication runs first. LDAP runs if that fails. Local accounts (including user 1) still work. |
| **Exclusive** | Only LDAP authentication is allowed for normal users. Password-reset links go to the LDAP help URL you configure. The Drupal password form stays available to administrators, not to anonymous users. Password fields on the profile form are removed except for administrators. |

Start in **mixed** until you can log in as a directory user. Switch to
exclusive only when you intend the directory to be the sole source of
logins.

**Exclude administrators** (`skipAdministrators`) keeps members of admin
roles on Drupal authentication so you are not locked out if LDAP is down.

## Access restrictions

- **Allow only if text in DN**: at least one listed substring must appear
  in the user’s DN (case insensitive).
- **Exclude if text in DN**: deny if any listed substring appears.
- **Deny if no authorizations**: login fails when LDAP Authorization would
  grant no roles (or other consumers). Requires LDAP Authorization.

## Email and password on Drupal forms

**Email behavior** (`emailOption`): hide the field (`remove`), show it
disabled (`disable`), or leave it editable (`allow`). **Email update**
controls whether a differing directory mail overwrites the Drupal mail at
login.

**Email templates** can fill an address when LDAP does not provide one
(pattern `@username@yourdomain.com`). Optional prompts send the user to a
form when the stored mail still matches the template regex.

**Password behavior** (`passwordOption`): `disable`, `hide`, or `allow`.
Allowing Drupal password changes does not update LDAP unless you also
provision the password attribute in LDAP Users.

## Single sign-on

This repository does **not** ship SSO. The
`ldap_authentication.login_validator_sso` service
(`LoginValidatorSso`) is the integration point for a separate project such
as `ldap_sso`. Configure help URLs and exclusive-mode password behavior
here; do not expect Kerberos or NTLM from these modules alone.
