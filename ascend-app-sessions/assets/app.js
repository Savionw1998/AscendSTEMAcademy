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

	/*
	 * Bottom tab bar, only inside the app (display-mode: standalone). Tabs come from PHP
	 * (window.ascendAppTabs); a tab is current when the path starts with one of its paths.
	 */
	var ICONS = {
		dashboard: '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h5v-6h4v6h5V10"/>',
		timecard: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		games: '<rect x="2" y="7" width="20" height="11" rx="5"/><path d="M7 10.5v4M5 12.5h4"/><circle cx="15.5" cy="11.5" r="1"/><circle cx="18" cy="14" r="1"/>',
		account: '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/>'
	};

	function addTabBar(tabs) {
		var path = location.pathname;
		var nav = d.createElement("nav");
		nav.className = "asa-tabbar";
		nav.setAttribute("aria-label", "App");
		tabs.forEach(function (tab) {
			var a = d.createElement("a");
			a.href = tab.url;
			a.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true">' + (ICONS[tab.icon] || "") + "</svg><span></span>";
			a.lastChild.textContent = tab.label;
			if (tab.paths.some(function (p) { return path.indexOf(p) === 0; })) {
				a.setAttribute("aria-current", "page");
			}
			nav.appendChild(a);
		});
		d.body.appendChild(nav);
		d.documentElement.classList.add("asa-has-tabbar");
	}

	if (inApp) {
		tuneLoginForms();
	}
	if (standalone && window.ascendAppTabs) {
		addTabBar(window.ascendAppTabs);
	}
})();
