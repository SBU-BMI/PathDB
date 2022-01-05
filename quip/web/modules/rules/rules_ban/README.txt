The 'Rules Ban' module provides Rules support on behalf of the core Drupal Ban
module. This functionality can't be included in the main Rules module because
it depends on the Ban module being enabled.

This module provides the following:

  1) A Condition called IpIsBanned which determines if an IP address has been
     blocked by the Ban module.

  2) An Action called BanIp which blocks an IP address using the Ban module.

  3) An Action called UnBanIp which unblocks an IP address using the Ban module.
