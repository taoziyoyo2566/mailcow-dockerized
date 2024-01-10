// mailcow login via sogo
document.addEventListener('DOMContentLoaded', function () {
    var button = document.querySelector('button[type="submit"].md-fab.md-accent.md-hue-2.md-button.md-ink-ripple');
    var form = button.closest('form');
    var requiredInputs = form.querySelectorAll('input[required]');
  
    button.addEventListener('click', async function (event) {
      event.preventDefault();
  
      const response = await fetch('/sogo-auth.php?login=' + encodeURIComponent(requiredInputs[0].value), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'password=' + encodeURIComponent(requiredInputs[1].value),
      });
  
      window.location.reload();
    });
});

// Custom SOGo JS

// Change the visible font-size in the editor, this does not change the font of a html message by default
CKEDITOR.addCss("body {font-size: 16px !important}");

// Enable scayt by default
//CKEDITOR.config.scayt_autoStartup = true;

