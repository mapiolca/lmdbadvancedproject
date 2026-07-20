# CHANGELOG ADVANCED PROJECT FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.3.0

- Add native customer-order list links and evenly spaced detail tooltips to global report totals, plus an access-aware project multiselect filter shared with exports
- Add per-project invoiced totals and invoicing rates to the global budget table, with native invoice links, contribution tooltips, and numeric ODS/XLSX columns
- Add an optional observation-period restriction for all dated budget-report content while retaining selected projects, with matching XLSX/ODS exports
- Add translated Dolibarr information tooltips to every global budget-report filter and use native date and binary selectors
- Add a shared monthly time axis and time matrices by project and by task, including zero-value rows and row/column totals
- Add a monthly recorded-hours series on a secondary axis with 10% rounded headroom, Chart.js legacy/modern compatibility, legend toggling, and an explanatory tooltip
- Keep wide budget-report charts and tables horizontally scrollable without widening the Dolibarr page, using the native horizontal-scroll page layout while the monthly chart retains both visible vertical axes
- Add a stylesheet revision to invalidate stale browser caches after report layout updates
- Apply Dolibarr's `MAIN_MAX_DECIMALS_TOT` precision to displayed hours and spreadsheet total formats
- Register project budget report views in the UserNavHistory navigation bar through the native `globalcard` hook context
- Display total decimal hours next to the time cost in the Spent tile
- Add distinct contributor counts to the project time detail and the expense-report user to its detail table
- Include total-time and expense-report detail tables in project XLSX, ODS, and PDF outputs
- Add ODS and XLSX exports with report, time, and chart-data sheets; XLSX includes three native charts
- Fix Excel workbook repair warnings by grouping monthly line series and correcting cartesian chart axis references in both global and project XLSX exports
- Add the native `budgetreport` project PDF model with vector charts, category summary, and paginated task/month matrices
- Improve the project PDF first page with a full-width monthly chart and monetary values in pie-chart legends
- Open inline project budget PDFs in a new browser tab while preserving Dolibarr's `MAIN_DISABLE_FORCE_SAVEAS` download behavior
- Resolve uncategorized labels in the selected output language and localize project budget PDF filenames and metadata
- Add a compatibility settings tab for Dolibarr, PHP, PhpSpreadsheet, ZipArchive, and PDF support
- Replace report monetary rounding with Dolibarr `price2num(..., 'MT')` and native `price()` formatting

## 1.2.1

- Skip the Commercial categories dictionary declaration when DynamicsPrices is already enabled

## 1.2

- Add project breakdowns for supplier and customer invoice lines behind separate disabled-by-default settings
- Show allocated invoice parts on project overview pages
- Include supplier invoice parts in budget report spending and category summaries without double-counting source lines
- Include customer invoice parts in invoiced totals without changing category summaries

## 1.1

- Add date range and project status filters to the global budget report
- Allow open and closed projects to be shown according to the selected status filter
- Split supplier spending between ordered supplier orders, delivered supplier orders, and supplier invoices
- Display supplier spending percentages in the Spent tile
- Improve order and supplier detail modals with totals, localized dates, Dolibarr document links, and a Dolibarr-style close button

## 1.0

Initial version
- Display project budget report in chart & table
- Only applicable for open project
