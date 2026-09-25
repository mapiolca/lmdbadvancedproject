/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
(function () {
	'use strict';
	function alignTotals() {
		var headers = document.querySelectorAll('th.lmdbap-billing-progress');
		for (var h = 0; h < headers.length; h++) {
			var table = headers[h].closest('table');
			var billing = table.querySelector('td.lmdbap-billing-progress');
			if (!billing) continue;
			var row = billing.parentNode;
			var badges = row.querySelectorAll('.multicompany-entity-card-container');
			if (badges.length !== 1 || row.cells.length !== headers[h].parentNode.cells.length) continue;
			var entityCell = badges[0].closest('td');
			if (!entityCell || entityCell.parentNode !== row) continue;
			var totals = table.querySelectorAll('tr.liste_total, tr.liste_grandtotal');
			for (var t = 0; t < totals.length; t++) {
				var total = totals[t];
				if (total.closest('table') !== table || total.cells.length !== row.cells.length - 1) continue;
				var cells = Array.from(row.cells).concat(Array.from(total.cells));
				if (cells.some(function (cell) { return cell.colSpan !== 1 || cell.rowSpan !== 1; })) continue;
				// Multicompany 22.0.1 adds its project cell without updating nbfield.
				// Insert only the missing blank at that position; never recalculate totals.
				total.insertCell(entityCell.cellIndex);
			}
		}
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', alignTotals);
	else alignTotals();
}());
