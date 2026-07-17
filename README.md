# ADVANCED PROJECT FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

Current version: **1.3.0**

## Features

Advanced Project extends project budget analysis in Dolibarr.

The Budget Report page tracks project budgets, time spent on tasks, vendor invoices, staff expenses, and remaining balances with Dolibarr's configured currency. You can access this feature from the Project module.

The report includes a monthly time matrix by project on the global page and by task on an individual project. The total time cost and total decimal hours are displayed together in the Spent tile.

The monthly budget-versus-spent chart also overlays recorded hours on a secondary axis. Each series can be shown or hidden from the chart legend, and an information tooltip explains how hours relate to total spending.

Wide charts and report tables remain readable with horizontal scrolling instead of overflowing the browser window. The monthly chart keeps both vertical axes visible while its scrollbar changes the displayed month range. Displayed totals and hours follow Dolibarr's configured maximum number of decimals for total prices.

Reports can be exported as XLSX or ODS. Both formats include the total-time detail with contributor counts and the expense-report detail with users; XLSX files also contain the report charts, while ODS files contain visible chart source data. Individual project reports can also be generated with the native **Project budget report** PDF document model, including both detail tables, either from the project document selector or from the Budget report tab. The generated PDF follows Dolibarr's `MAIN_DISABLE_FORCE_SAVEAS` setting: inline documents open in a new tab, while forced downloads retain the native download behavior.

Spreadsheet exports require the PhpSpreadsheet library bundled with Dolibarr 20 and the PHP `ZipArchive` extension. The module compatibility tab reports the availability of these components.

Date range and project status filters on the global report are preserved in spreadsheet exports. Supplier spending is split between ordered supplier orders, delivered supplier orders, and supplier invoices, with percentages displayed in the Spent tile. Detail modals include totals, localized dates, and Dolibarr document links.

## Translations

Translations are available in English, French, Italian, Spanish, and German. They can be completed manually by editing files in the `langs` directory.

## Licenses

### Main code

GPLv3 or (at your option) any later version. See file COPYING for more information.

### Documentation

All texts and readmes are licensed under GFDL.
