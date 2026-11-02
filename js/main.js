/* The Prodigal Channel: page behaviour. No network requests are made here. */

(function () {
  "use strict";

  /* ---------- Dialogs (insight articles and the privacy note) ---------- */

  document.querySelectorAll("[data-dialog]").forEach(function (trigger) {
    trigger.addEventListener("click", function () {
      document.getElementById(trigger.dataset.dialog).showModal();
    });
  });

  document.querySelectorAll("dialog").forEach(function (dialog) {
    dialog.addEventListener("click", function (event) {
      var closesDialog =
        event.target === dialog || // click on the backdrop
        event.target.closest(".close") ||
        event.target.closest("a");

      if (closesDialog) dialog.close();
    });
  });

  /* ---------- Sign-up form ---------- */

  /*
   * The site's original database no longer exists, so this form has nowhere
   * to send anything. Nothing entered here is stored or transmitted. To
   * collect sign-ups, connect the form to a backend in this handler.
   */
  var form = document.getElementById("signup-form");
  var status = document.getElementById("signup-status");

  form.addEventListener("submit", function (event) {
    event.preventDefault();
    status.textContent =
      "Sign-ups aren’t open yet. Your details were not saved. Please check back soon.";
    status.focus();
  });
})();
