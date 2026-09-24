# CHANGELOG ADVANCED PROJECT FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.5.0 — preparation

- Add the native project-list column “Progressions facturation”, with numeric percentage, native progress bar, sorting, filtering and column selection. Customer invoice allocations replace their source lines and credit notes retain their sign; a non-positive order total produces an explained dash.
- Show the five main budget tiles and complete expense breakdown below the project-card description in the native right half, with a compact responsive layout and a visible fallback when JavaScript or the expected container is unavailable. Emit the card hook output directly, as required by the native page, so the tiles appear on desktop and mobile.
- Share customer contribution queries and tile rendering with the Budget Report. The card loads all-period totals without preparing report charts, matrices or forecasts; query failures display an explicit warning instead of zero amounts. Enforce project/report permissions and customer-source third-party/entity scope.
- Retain Dolibarr 20 / PHP 8.0 minimums and module ID 450021. Reactivate after updating all files to register the additional native hook context, then refresh the browser cache; no new table or data migration. CLI/source/browser-fixture evidence and operational limits are documented separately.

## 1.4.1 — preparation

- Complete missing shipment costs with applicable recorded PriceList history and successful dated DynamicPrices costs, without recalculating optional providers; preserve known zero prices and validation snapshots.
- Add the native Refresh dialog with free, Dolibarr, PMP, optional-provider and net supplier prices. Freeze instructions per project/product, with an explicit opt-in for eligible existing projects and no rule for new projects.
- Recheck native permissions, project scope, units, currencies and source changes; serialize saves transactionally with unique project/product instructions, stale-quote rejection and idempotent replay.
- Retain instructions and supplemental evidence after deactivation, and expose provenance across tooltips and PDF/XLSX/ODS outputs. Keep descriptor discovery independent of application compatibility classes, avoiding an undefined-constant fatal error during a mixed-version update. Update all files from the same commit, then reactivate the module to install the two additional tables.
- Use the shipped product's commercial category for costs and invoice regularizations, including shipments without an order line; honor shared product categories and the master dictionary unless native Multicompany dictionary separation is enabled. Avoid duplicate financial lines when resolving legacy category codes. Show a neutral grey "No cost to value" status when only customer or pending supplier orders exist, while preserving known zero costs. Rework About and Compatibility with native layouts, descriptor metadata, optional-provider diagnostics and FR/EN translations. PHP 8.0 / Dolibarr 20 remain the minimum; operational acceptance is tracked separately from CLI fixtures.
- Fix budget PDF storage to use the native owner-project directory without appending the project reference twice. Regenerate previously misplaced reports after updating; no existing files are deleted or moved automatically.
- Sort the product list by Status by default: incomplete valuation, unit mismatch, complete valuation, then no cost to value. Add a native multiselect state filter before pagination and a distinct unit-mismatch badge, preserving manual sorting and navigation filters.

## 1.4.0

- Add entity-specific, disabled-by-default shipment valuation using frozen net supplier tariffs or native PMP, with historical price evidence retained across deactivation and revalidation.
- Reconcile project/product quantities with allocated supplier invoices and remaining purchase commitments; recognize dated regularizations without changing accounting or stock.
- Add the native project product list with product/service links and native Ajax tooltips, explanatory tooltips and incomplete-valuation warnings; share calculations across reports, categories, signed charts and PDF/XLSX/ODS outputs, with measured native PDF footers and the configured currency and share of total spent on the shipment-cost summary.
- Install two idempotent historical tables and a listener for native shipment/price events (including the v23 price-event rename); reactivate the module after update, then enable the option per entity.
- Use direct native permissions and entity-bound settings; add regression tests and PHPStan level 5 / PHP 8.0 analysis. Document source-level v20–v24 compatibility and the remaining operational Dolibarr/Multicompany validation.

## 1.3.0

- Add native customer-order list links and evenly spaced detail tooltips to global report totals, plus an access-aware project multiselect filter shared with exports
- Add per-project invoiced totals and invoicing rates to the global budget table, with native invoice links, contribution tooltips, and numeric ODS/XLSX columns
- Add an optional observation-period restriction for all dated budget-report content while retaining selected projects, with matching XLSX/ODS exports
- Add the same inclusive content-period filters to individual project reports, forecasts, XLSX/ODS exports, and page-generated PDFs while keeping the project visible
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
