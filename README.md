# ADVANCED PROJECT FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

Current version: **1.5.0**

## Features

Advanced Project extends project budget analysis in Dolibarr.

The Budget Report page tracks project budgets, time spent on tasks, vendor invoices, staff expenses, and remaining balances with Dolibarr's configured currency. You can access this feature from the Project module.

The global report and each individual project report can restrict dated project content to an inclusive observation period. The project itself remains visible: customer orders and budgets, customer and supplier invoices, supplier orders, expense reports, and time entries outside the period are excluded from screen totals, forecasts, category summaries, monthly charts, and matrices only when content exclusion is enabled.

The global project table displays each project's invoiced amount and invoicing rate next to its orders. The amount links to Dolibarr's native customer invoice list, while its tooltip lists the contributing invoices, dates, and project-attributed amounts. ODS and XLSX exports provide separate numeric columns for the invoiced amount and rate.

Customer order totals also link to Dolibarr's native order list and expose the contributing orders, dates, and amounts in a native tooltip. A project multiselect limits the global report and its spreadsheet exports to the selected accessible projects.

The report includes a monthly time matrix by project on the global page and by task on an individual project. The total time cost and total decimal hours are displayed together in the Spent tile.

The monthly budget-versus-spent chart also overlays recorded hours on a secondary axis. Each series can be shown or hidden from the chart legend, and an information tooltip explains how hours relate to total spending.

On mobile, the card and report show two columns of indicators and full-width expense details, with a single column on very narrow screens. Tile heights follow their content, including long amounts. Pie charts fit the screen and display their native legends underneath; labels that cannot fit are shortened while their full text remains in tooltips. Wide report tables scroll horizontally, including with the keyboard, and the task column stays compact. The monthly chart keeps both vertical axes visible while its scrollbar changes the displayed month range. Displayed totals and hours follow Dolibarr's configured maximum number of decimals for total prices.

Reports can be exported as XLSX or ODS. Both formats include the total-time detail with contributor counts and the expense-report detail with users; XLSX files also contain the report charts, while ODS files contain visible chart source data. The project page passes its period and exclusion state to both spreadsheet formats and displays them in workbook metadata. Individual project reports can also be generated with the native **Project budget report** PDF document model, including both detail tables, either from the project document selector or from the Budget report tab. A PDF generated from the filtered tab uses and displays the same period; native generation without this page context remains a complete report. The generated filename and PDF metadata use the localized pattern `{project reference} - {Budget Report}`. The PDF follows Dolibarr's `MAIN_DISABLE_FORCE_SAVEAS` setting: inline documents open in a new tab, while forced downloads retain the native download behavior.

Spreadsheet exports require the PhpSpreadsheet library bundled with Dolibarr 20 and the PHP `ZipArchive` extension. The module compatibility tab reports the availability of these components.

Date range and project status filters on the global report are preserved in spreadsheet exports. Supplier spending is split between ordered supplier orders, delivered supplier orders, and supplier invoices, with percentages displayed in the Spent tile. Detail modals include totals, localized dates, and Dolibarr document links.

## Project list and card summary (1.5.0)

The native project list includes **Invoicing progress** (**Progressions facturation** in French): invoiced HT divided by ordered HT, as a numeric percentage followed by Dolibarr's native progress bar. The column supports native selection, sorting and numeric search (for example `>=50`), with filters retained during pagination. Without a positive order total, it displays an explained dash. Credit notes can produce a negative percentage, and overbilling can exceed 100%; only the bar is bounded to 0–100%.

The native project card shows the five main Budget Report tiles below the description in its right half: Orders, Invoiced, Budget, Spent and Remaining budget. The complete expense breakdown is retained. The compact layout displays Orders/Invoiced, then Budget/Remaining, then Spent across the full half-width; phones stack the tiles. The card always covers all dates, independently of report filters. The full report and card share their totals and renderer, and customer contributions also supply the native project list, including project allocations without double counting.

Reading projects and the module's budget report is required; administrators do not bypass these permissions. Project access and native entity scopes remain separate. Customer contributions additionally respect access to their linked third party (entity, sales assignment and external account); an absent optional third party remains distinct from an inaccessible or dangling link. Existing analytical report rules continue to determine spending. Native document permissions remain necessary for document links in the full report.

After updating all module files, reactivate the module to register `projectlist`, then refresh the browser cache. Existing settings and permissions are preserved. This version introduces no table or migration. Without JavaScript, or if another module changes the description container, the summary remains visible at the native card hook position. See [1.5.0 validation and remaining acceptance checks](doc/VALIDATION_PROJECT_SUMMARY.md).

## Optional shipment costs

An entity-specific switch adds provisional costs for shipped products not yet covered by supplier invoices. Choose the latest historical net supplier tariff or the native PMP frozen at shipment validation. Invoices later replace the provisional valuation; dated adjustments preserve period totals. Both historical values are retained, including after disabling the option. Unknown historical prices are reported as incomplete instead of using current prices.

A native **Product list** project tab compares customer/supplier orders, shipments, invoiced purchases, uncovered quantities and remaining commitments. Project/global reports, categories, graphs and PDF/XLSX/ODS outputs share the same reconciliation; spreadsheets also include products and dated contributions. Native invoice allocations, entity scopes and project/report permissions are respected.

The **Status** column defaults to incomplete valuations, unit mismatches, complete valuations, then no cost to value. Its native multiselect filter accepts several states; an empty selection shows all states. Filtering runs on reconciled rows before pagination. Column sorting remains available and navigation preserves the selected filters. A unit mismatch takes priority when the same product also has a missing price.

Shipment costs and invoice regularizations follow the shipped product's commercial category, including shipments without a linked order line. Product categories take priority over line categories. With Multicompany, the master dictionary and categories of shared products remain available unless native dictionary separation restricts access. A grey **No cost to value** badge identifies products with no contributing cost yet; known zero costs remain valid complete valuations. About presents descriptor metadata and documentation links in the native administration layout; Compatibility details effective requirements and optional-provider availability.

Version 1.4.1 completes missing shipment prices using eligible historical PriceList costs, then dated DynamicPrices costs. Rows that remain unvalued offer **Refresh**: select a free unit cost (including zero), Dolibarr cost, PMP, an available optional-provider cost or a net supplier price. The selected amount is frozen for that project/product and its future missing shipment costs. An unchecked option copies the instruction to eligible existing projects; it creates no rule for new projects. Known snapshots and invoices are preserved. Project modification, product-price and source permissions are required. Optional providers are never recalculated during a report consultation.

Budget PDFs use the native project directory once, so generated files match their download links, including shared projects. Regenerate a previously misplaced PDF after updating the code; existing files are not moved or deleted automatically.

Reactivate the module after installing 1.4.1 to create the instruction and supplemental snapshot tables. Instructions survive module deactivation and source-price changes. See [valuation rules and historical limits](doc/COST_VALUATION.md) and [1.4.1 validation evidence](doc/VALIDATION_COST_VALUATION.md). Operational browser and database acceptance requires an instance serving this version.

After updating, reactivate the module to install the historical tables and native trigger listener, then opt in from its settings. The option defaults to off; settings and snapshots survive reactivation. The native PMP method requires Stock. See the [detailed rules, examples and historical limitations](doc/SHIPMENT_COSTS.md) and [test/instance validation guide](test/README.md), with [executed checks and remaining limits](doc/VALIDATION_SHIPMENT_COSTS.md).

## Project overview integration

Invoice allocations are added to the project's native overview without replacing entries contributed by other modules, including Diffusion. Allocation settings and permissions still determine which allocation entries appear. Deploy the corresponding additive-hook correction in Diffusion as well when both modules are used; no hook priority override or database migration is required.

The joint regression runner is `test/project_overview_hooks.php` in the Diffusion module. It checks both hook orders, preserved third-module entries, allocation permissions and Diffusion visibility for authors and other readers using the native Dolibarr HookManager. Its fixtures simulate users and use SQLite in memory; they do not validate a deployed ERP or Multicompany instance.

## Translations

Translations are available in English, French, Italian, Spanish, and German. They can be completed manually by editing files in the `langs` directory.

## Licenses

### Main code

GPLv3 or (at your option) any later version. See file COPYING for more information.

### Documentation

All texts and readmes are licensed under GFDL.
