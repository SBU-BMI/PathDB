# Connecting Drupal to a directory service via LDAP

## Prerequisites

To set up LDAP efficiently, you need to acquire the relevant information for the
domain you are authenticating against.

Contact your organization's staff to receive the necessary information. This
should include:

* The servers available to you (hostname, port, encryption preference)
* The binding method (service account including credentials, if necessary)
* If applicable, the structure of the data you are trying to sync, e.g.
sAMAccountName is the unique name attribute for your Active Directory.

### Requirements

The following requirements need to be met for you to work with any of the LDAP
modules.

* PHP version 7.1
* PHP LDAP extension.
* Drupal Core >=8.8.0.

For SSO please see ldap_sso/README.md.

## Installation

### Enabling communication

Enable the relevant modules and add your environment under the relevant tabs.
See README.md for an overview of the modules to figure out which you will need.

You should see "Server available" in the list of servers, if the base
configuration is correct. If not, you likely have misconfigured binding
settings, incorrect ports or certificate issues. Please note that the Linux LDAP
libraries do not work well with self-signed certificates, avoid them wherever
possible.

### Logging in via LDAP

You should review all tabs (Settings, Authentication, Users) to determine the
correct configuration for your use-case and configure them as needed. We
recommend that you configure authorization profiles after you have successfully
authenticated users.

If you are able to connect to the server but logging in fails, please see the
general instructions under Debugging for recommended steps.

## Debugging

We recommend you follow these steps to solve your issue:

1. Review the recent log messages for errors.
1. Enable detailed watchdog logging under LDAP settings to receive additional
 debugging information.
1. Isolate a test-case that ideally is proven to work with at least one other
LDAP consumer other than your Drupal site.
1. If all else fails consider
[filing a support request](https://www.drupal.org/node/add/project-issue/ldap).
Please note that you will need to provide detailed information on your
environment and usage scenario. The more complex this is the less likely it is
that the maintainers will be able to recreate your conditions.

## Tip: Exclude the service account credentials from your configuration

If you want to avoid adding your service account credentials to the database and
thus it being also synced with configuration export, you have the option of
entering a dummy password and providing the real password as a configuration
override via settings.php, e.g.:

```
$config['ldap_servers.server.YOURSERVER']['bindpw'] = 'actual-password';
```

Furthermore, you have the option of adding this password to a file outside the
webroot and only including that file.
