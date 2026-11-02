/* The Prodigal Channel: page behavior. */

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

  /* Sends the form to api/signup.php, which saves it to MySQL. */
  var form = document.getElementById("signup-form");
  var status = document.getElementById("signup-status");
  var button = form.querySelector('button[type="submit"]');
  var buttonLabel = button.innerHTML;
  var failureMessage = "We couldn’t save your request. Please try again.";
  var pageOpenedAt = Date.now();

  function showStatus(message) {
    status.textContent = message;
    status.focus();
  }

  function setBusy(busy) {
    form.setAttribute("aria-busy", String(busy));
    button.disabled = busy;
    button.innerHTML = busy ? "Saving your request…" : buttonLabel;
  }

  form.addEventListener("submit", function (event) {
    event.preventDefault();
    status.textContent = "";
    setBusy(true);

    var data = new FormData(form);
    data.append("elapsed", String(Date.now() - pageOpenedAt));

    fetch("api/signup.php", { method: "POST", body: data })
      .then(function (response) {
        return response
          .json()
          .catch(function () {
            return {};
          })
          .then(function (data) {
            if (!response.ok || !data.ok) {
              throw new Error(data.error || failureMessage);
            }
          });
      })
      .then(function () {
        form.reset();
        form.hidden = true;
        status.classList.add("success");
        showStatus("Thank you for your submission! We will be in touch soon!");
      })
      .catch(function (error) {
        /* A network failure's own message isn't meant for visitors. */
        showStatus(error instanceof TypeError ? failureMessage : error.message);
      })
      .finally(function () {
        setBusy(false);
      });
  });
})();
