# ADVANCED PROJECT FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## Features

Advanced Project extends project budget analysis in Dolibarr.

The Budget Report page tracks project budgets, time spent on tasks, vendor invoices, staff expenses, and remaining balances with Dolibarr's configured currency. You can access this feature from the Project module.

The report includes a monthly time matrix by project on the global page and by task on an individual project. The total time cost and total decimal hours are displayed together in the Spent tile.

Reports can be exported as XLSX or ODS. XLSX files contain the report charts, while ODS files contain the same tables and visible chart source data. Individual project reports can also be generated with the native **Project budget report** PDF document model, either from the project document selector or from the Budget report tab.

Spreadsheet exports require the PhpSpreadsheet library bundled with Dolibarr 20 and the PHP `ZipArchive` extension. The module compatibility tab reports the availability of these components.

Date range and project status filters on the global report are preserved in spreadsheet exports. Supplier spending is split between ordered supplier orders, delivered supplier orders, and supplier invoices, with percentages displayed in the Spent tile. Detail modals include totals, localized dates, and Dolibarr document links.

## Translations

Translations are available in English, French, Italian, Spanish, and German. They can be completed manually by editing files in the `langs` directory.

## Licenses

### Main code

GPLv3 or (at your option) any later version. See file COPYING for more information.

### Documentation

All texts and readmes are licensed under GFDL.
