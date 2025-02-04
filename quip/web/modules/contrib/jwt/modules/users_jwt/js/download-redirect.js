/**
 * Redirect when a download happens.
 */
(() => {
  const button = document.getElementById('edit-download');
  if (button) {
    const handleClick = () => {
      const cookieName = 'users_jwt_download';
      document.cookie = `${cookieName}=1; max-age=20; path=/`;
      const downloadCookieCheck = window.setInterval(() => {
        const waiting = document.cookie
          .split(';')
          .some((item) => item.trim().startsWith(cookieName));
        if (!waiting) {
          window.clearInterval(downloadCookieCheck);
          // Use the href of the cancel link to redirect.
          const a = document.getElementById('edit-cancel');
          document.location.href = a ? a.href : '/';
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
