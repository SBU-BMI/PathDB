# JWT OAuth Client Credentials

Issues short-lived JWTs via the OAuth 2.0 **client credentials** grant
(RFC 6749 §4.4), for machine-to-machine (M2M) integrations. Each Drupal account
can hold one or more client credentials and act as a **service account**: a
token minted with those credentials authenticates as that user.

## How it works

The token endpoint issues an ordinary site JWT — the same shape `jwt_auth_issuer`
produces. It is signed with the site JWT key and validated on subsequent
requests by the global `jwt_auth` provider (and the user is resolved by
`jwt_auth_consumer` from the `drupal.uid` claim). No new authentication provider
is added.

The controller builds the token directly via the `jwt.transcoder` service,
stamping the `iat`, `exp`, and `drupal.uid` claims from the authenticated
client's service account. It does **not** dispatch the JWT `GENERATE` event, so
the token carries exactly the claims this grant intends regardless of which
other modules are installed — and the module depends only on `jwt`, not on
`jwt_auth_issuer`.

Credentials are stored in the `user.data` store (module key `jwt_oauth_ccf`),
keyed by a globally-unique client id — mirroring `users_jwt`. Only a **hash** of
the client secret is stored; the plaintext is shown exactly once, as a file
download, at generation time.

## Token endpoint

    POST /oauth2/token
    Content-Type: application/x-www-form-urlencoded

    grant_type=client_credentials&client_id=...&client_secret=...

Credentials may instead be supplied via an HTTP Basic `Authorization` header
(RFC 6749 §2.3.1). A successful response (with `Cache-Control: no-store`):

    {
      "access_token": "<jwt>",
      "token_type": "Bearer",
      "expires_in": 3600
    }

Use the token on subsequent requests as `Authorization: Bearer <access_token>`.

Errors follow RFC 6749 §5.2 (`invalid_request`, `invalid_client`,
`unsupported_grant_type`, `server_error`). Failed attempts are throttled per
client id and source address via Drupal's flood control.

Tokens live for 1 hour (`TokenController::TOKEN_LIFETIME`); the reported
`expires_in` is derived from the token's own `exp` claim, so it always matches.

## Managing credentials

A **OAuth clients** tab appears on user profiles (`/user/{user}/oauth-clients`).
From there you can create a labelled credential and delete existing ones.

When creating a credential you may either leave the secret blank — a strong
secret is generated and downloaded once — or supply your own (at least 16
characters). Either way only a hash is stored (via the core `password`
service); the plaintext is never recoverable.

Deleting a credential stops it from minting *new* tokens but does **not** revoke
tokens already issued — those remain valid until they expire (stateless JWTs).

### Permissions

- `manage own oauth client credentials` — manage credentials on your own
  account.
- `administer oauth client credentials` — manage credentials on any account.

Both are marked *restricted*: a credential can mint tokens that fully
impersonate its account, so grant them only to trusted roles and prefer
dedicated, least-privilege service accounts.

## Security notes

- Serve only over HTTPS. The client secret is a bearer credential.
- Store the client secret like a password; rotate it (delete + regenerate) on
  compromise.
- Scope the service account tightly — the token carries all of its permissions,
  roles, and Organic Groups access.

## Relationship to `oauth2_server_service_user`

Both bind an M2M client to a real Drupal service account. `oauth2_server_service_user`
does so on top of the contrib `oauth2_server` module (a full OAuth2 server with
opaque, stateful tokens). This module is a lighter, self-contained alternative
that issues stateless JWTs consumed by the JWT stack already in use, without the
`oauth2_server` dependency. Choose one per integration.
