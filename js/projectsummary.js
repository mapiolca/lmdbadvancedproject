/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
(function () {
	'use strict';
	function placeSummary() {
		var summary = document.getElementById('lmdbap-project-summary');
		if (!summary) return;
		// v20–v24: the project read form contains the description table in its
		// right half. The later document/agenda halves are outside that form.
		var forms = document.querySelectorAll('form');
		for (var i = 0; i < forms.length; i++) {
			var action = forms[i].querySelector('input[name="action"][value="update"]');
			var right = forms[i].querySelector('.fichehalfright');
			if (!action || !right) continue;
			var table = right.querySelector('table.tableforfield');
			if (table && table.parentNode === right) {
				right.insertBefore(summary, table.nextSibling);
				return;
			}
		}
		// Keep the server-rendered section visible if another module changed
		// the card structure. No data is fetched or computed by this script.
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', placeSummary);
	else placeSummary();
}());
