moderated content bulk publish

This module provides a bulk operation plugin for Publishing and Unpublishing moderated states from the admin/content page.
Also provides a bulk operation for pin and unpin (sticky/not sticky).
Also provides a language switcher for the admin-toolbar for available languages if site has more than one language.
Also provides setting moderation state to other translations in the node edit form.
Also prevents a 403 when latest revision doesn't exist for a translation of a node (redirects to the node which will display correctly).
Installation/setup instructions:

Intended to work with content types using the editorial workflow provided by the Drupal 9 core workflow module (when enabled and configured for a content type).

Options for this module are here:
/admin/config/content/moderated-content-bulk-publish 

to configure this functionality you'll want to add "Node operations bulk" field to the /admin/structure/views/view/content view , select the following options from that:

 Pin Content
 Publish latest revision
 Unpin content
 Unpublish current revision
 Archive current revision 


 There are open issues for this module in the issue queue, we intend on continuing improvements and have been doing so for a couple years now.

 In early 2022 we also added a hook api which is now used by safedelete (optionally).

 There is an optional modal dialog confirmation box for confirming publishing can be configured in settings /admin/config/content/moderated-content-bulk-publish

Check the release notes for the upcomming changes in each release.

