# Tokens

LDAP Users mappings and some templates replace `[brackets]` from a
directory entry. The `ldap.token_processor` service
(`Drupal\ldap_servers\Processor\TokenProcessor`) performs the replacement.

Attribute names in tokens are **lowercase**, matching how the server form
stores `user_attr`, `mail_attr`, and related fields.

Unreplaced tokens are stripped from the result.

## From the DN

For a DN such as `cn=jdoe,ou=campus accounts,ou=toledo campus,dc=ad,dc=university,dc=edu`:

| Token | Example |
| ----- | ------- |
| `[cn]` / `[cn:0]` | `jdoe` |
| `[cn:last]` | `jdoe` |
| `[ou]` / `[ou:0]` | first OU |
| `[ou:last]` | last OU |
| `[ou:reverse:0]` | last OU (hierarchy from the right) |
| `[dc:0]`, `[dc:1]`, … | domain components |
| `[dn]` | full distinguished name |

## From attributes

| Token | Meaning |
| ----- | ------- |
| `[mail]` / `[mail:0]` | First value of `mail`. |
| `[mail:last]` | Last value if multi-valued. |
| `[samaccountname]` | Attribute named `samaccountname`. |

Index `:0` is the first value. `:last` is the last.

## Conversions

Append `;function` after the token key:

- `;base64_encode` — Base64 encode the value.
- `;bin2hex` — hexadecimal encoding.
- `;msguid` — Microsoft GUID string via `convertMsguidToString()`.

Example: `[guid:0;base64_encode]`.

## Case

LDAP is case-aware but not case-sensitive. Compare DNs and attribute values
without relying on capitalization. Authorization queries and tokens should
use the lowercase names the server configuration stores.
