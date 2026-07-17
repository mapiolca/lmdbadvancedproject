# CHANGELOG ADVANCED PROJECT FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## Unreleased

- Add a shared monthly time axis and time matrices by project and by task, including zero-value rows and row/column totals
- Add a switchable monthly time-cost series and explanatory tooltip to the budget-versus-spent chart
- Register project budget report views in the UserNavHistory navigation bar through the native `globalcard` hook context
- Display total decimal hours next to the time cost in the Spent tile
- Add ODS and XLSX exports with report, time, and chart-data sheets; XLSX includes three native charts
- Add the native `budgetreport` project PDF model with vector charts, category summary, and paginated task/month matrices
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
