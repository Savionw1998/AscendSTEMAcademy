/* Ascend App Sessions: app behaviour that has to run in the browser. */
(function () {
	"use strict";

	var d = document;
	var standalone = !!(window.matchMedia && window.matchMedia("(display-mode: standalone)").matches);
	var inApp = standalone || /(?:^|;\s*)asa_app=1(?:;|$)/.test(d.cookie);

	/*
	 * Ultimate Member login form: always remember the family, and label the fields for password
	 * managers. PHP already does this when it knows the visit is from the app; this covers login
	 * pages served from the page cache.
	 */
	function tuneLoginForms() {
		d.querySelectorAll(".um-login form").forEach(function (form) {
			var remember = form.querySelector('input[type="checkbox"][name="rememberme"]');
			if (remember) {
				remember.checked = true;
				(remember.closest(".um-field") || remember).style.display = "none";
			}
			var user = form.querySelector('input[data-key="user_login"], input[data-key="username"], input[data-key="user_email"]');
			if (user) {
				user.setAttribute("autocomplete", "username");
			}
			var pass = form.querySelector('input[type="password"][data-key="user_password"]');
			if (pass) {
				pass.setAttribute("autocomplete", "current-password");
			}
		});
	}

	if (inApp) {
		tuneLoginForms();
	}
})();
