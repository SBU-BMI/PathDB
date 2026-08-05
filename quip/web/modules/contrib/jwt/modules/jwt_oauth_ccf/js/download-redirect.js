/**
 * Redirect back to the client list once the credential file has downloaded.
 */
(() => {
  const button = document.getElementById('edit-download');
  if (button) {
    const handleClick = () => {
      const cookieName = 'jwt_oauth_ccf_download';
      document.cookie = cookieName.concat('=1; max-age=20; path=/');
      const downloadCookieCheck = globalThis.setInterval(() => {
        const waiting = document.cookie
          .split(';')
          .some((item) => item.trim().startsWith(cookieName));
        if (!waiting) {
          globalThis.clearInterval(downloadCookieCheck);
          // Use the href of the cancel link to redirect.
          const a = document.getElementById('edit-cancel');
          globalThis.document.location.href = a ? a.href : '/';
        }
      }, 200);
    };
    const options = {
      once: true,
      passive: true,
    };
    button.addEventListener('click', handleClick, options);
    button.addEventListener('keydown', handleClick, options);
  }
})();
