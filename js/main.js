/* The Prodigal Channel: page behavior. */

(function () {
  "use strict";

  /* ---------- Anonymous visit statistics ---------- */

  /*
   * Sends cookie-free counts to api/track.php: one page view, which articles
   * are opened, which links marked data-track are clicked, and how far down
   * the page people scroll. The private admin page can switch this off for
   * the owner's own browser.
   */
  var countVisits = !!navigator.sendBeacon;
  try {
    if (localStorage.getItem("prodigal-no-stats") === "1") countVisits = false;
  } catch (error) {
    /* Storage blocked: count as usual. */
  }

  function track(name, label, details) {
    if (!countVisits) return;
    var data = new URLSearchParams(details || {});
    data.set("e", name);
    if (label) data.set("l", label);
    navigator.sendBeacon("api/track.php", data);
  }

  var query = new URLSearchParams(location.search);
  var timezone = "";
  try {
    timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || "";
  } catch (error) {
    /* Older browser: leave it out. */
  }
  track("pageview", "", {
    r: document.referrer,
    s: query.get("utm_source") || "",
    c: query.get("utm_campaign") || "",
    w: String(window.innerWidth),
    tz: timezone
  });

  /* Once counted, drop the tags so the address bar and shared links stay clean. */
  if (query.has("utm_source") || query.has("utm_campaign") || query.has("utm_medium")) {
    ["utm_source", "utm_campaign", "utm_medium", "utm_content", "utm_term"].forEach(function (key) {
      query.delete(key);
    });
    var rest = query.toString();
    history.replaceState(null, "", location.pathname + (rest ? "?" + rest : "") + location.hash);
  }

  document.addEventListener("click", function (event) {
    var link = event.target.closest("[data-track]");
    if (link) track("click", link.dataset.track);
  });

  var depths = [25, 50, 75, 100];
  var scrollQueued = false;
  function checkScroll() {
    scrollQueued = false;
    var page = document.documentElement;
    var seen = ((window.scrollY + window.innerHeight) / page.scrollHeight) * 100;
    while (depths.length && seen >= depths[0] - 1) {
      track("scroll", String(depths.shift()));
    }
    if (!depths.length) window.removeEventListener("scroll", queueScroll);
  }
  function queueScroll() {
    if (scrollQueued) return;
    scrollQueued = true;
    requestAnimationFrame(checkScroll);
  }
  window.addEventListener("scroll", queueScroll, { passive: true });

  /* ---------- Dialogs (insight articles and the privacy note) ---------- */

  document.querySelectorAll("[data-dialog]").forEach(function (trigger) {
    trigger.addEventListener("click", function () {
      document.getElementById(trigger.dataset.dialog).showModal();
      track("open", trigger.dataset.dialog.replace(/^dialog-/, ""));
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
