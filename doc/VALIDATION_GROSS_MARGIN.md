# Validation de la marge brute — 1.6.0

Contrôles du 5 octobre 2026. Base : `b226642f817adc8b081bd782cb90f998fca59428` (1.5.0). Branche dédiée : `fix/1.5.1-marge-brute`. La version est préparée pour une nouvelle PR ; aucune fusion, release ou installation serveur n'est réalisée par ce travail.

## Comportement implémenté

- Marge brute = commandes HT moins dépenses retenues, normalisée avec `price2num(..., 'MT')`. Pourcentage arrondi à l'entier de commandes HT ; commandes nulles ou négatives : tiret. Le rapport conserve sa période et la fiche conserve toutes les dates.
- Tuile après Budget Restant ; fiche compacte : Dépensé et Marge brute sur la troisième ligne, détail des dépenses dessous. Six tuiles avec droit, cinq sans droit ; une colonne sous 360 px.
- Appels directs à `$user->hasRight('margins', 'liretous')` pour les tuiles, le tableau global et les générateurs PDF/XLSX/ODS. Un administrateur sans ce droit ne reçoit aucune marge explicite ; le reste du rapport reste accessible selon ses permissions.
- XLSX/ODS : montant et ratio numériques, format pourcentage entier, tiret si ratio indéfini ; colonnes globales et totaux conditionnels. Le taux total provient des montants cumulés, pas de la moyenne des taux. Aucune valeur de marge supplémentaire dans les feuilles de données/graphiques.
- PDF : sixième tuile avec montant et pourcentage sur deux lignes. Répertoire de l'entité propriétaire et refus documentaires natifs conservés.
- Descripteur et README en 1.6.0 ; À propos lit déjà le descripteur. ID 450021 et famille Les Métiers du Bâtiment conservés, aucun nouvel ID attribué. Aucun nouveau droit, SQL, objet, trigger, Agenda, Notification, cron, numérotation ou import.
- Les documents déjà générés ne sont pas réécrits et conservent les droits documentaires existants. Les figures financières autorisées permettent toujours de recalculer une marge : cette évolution contrôle ses affichages explicites, sans changer l'accès aux commandes ou dépenses.

## Contrôles exécutés

PHP CLI **8.4.22**, PDO SQLite, ZipArchive et GD chargés via options CLI ; bibliothèques PhpSpreadsheet et TCPDF natives des checkouts core. Aucune base ERP ni configuration serveur modifiée.

| Contrôle | Résultat |
|---|---|
| `margin_test.php` sur 20.0.0 et 24.0.0 | 870 assertions cumulées par exécution, dont 93 supplémentaires de marge ; ne pas additionner les suites imbriquées |
| `costpdf_test.php` sur 20.0.0 et 24.0.0 | 774 assertions cumulées ; génération réelle de PDF, accès propriétaire et projet partagé simulé |
| `margin_pdf_test.py` | Contenu autorisé/interdit et bornes des tuiles vérifiés pour marge positive, négative, nulle, taux indéfini et grands montants |
| PHPStan niveau 5, cible PHP 8.0, core20 et core24 | Aucune erreur, configuration du module inchangée, aucune suppression ou baseline ajouté |
| Découverte du descripteur | Modes sans classe de compatibilité, classe historique et classe préchargée réussis |
| Lint PHP et `git diff --check` | Réussis |
| XLSX/ODS | Écriture puis relecture native, montant et ratio numériques, omission des marges sans droit dans toutes les feuilles |
| Navigateur local | Edge sans interface, fixture HTML simulée : 1600, 760, 390 et 320 px, aucun débordement horizontal ; contrôle visuel des dispositions |
| PDF visuel | Pages rendues par Poppler ; tuiles du rapport multipage et grands montants lisibles, sans chevauchement observé |

Les checkouts exécutés correspondent aux commits `697bf01970740a3339cd99cf055b4428fc5e051c` (20.0.0) et `769c7db907099643558e77d7002c109cfda919e5` (24.0.0). `check_native_contracts.py` vérifie aussi la permission native dans les tags 21.0.0, 22.0.0 et 23.0.0, aux révisions déjà consignées dans VALIDATION_PROJECT_SUMMARY.md. C'est une lecture de sources, pas un essai d'instance.

## Limites et recette restante

- v20 à v25 restent la cible déclarée ; aucun essai exécuté sur v21, v22, v23 ou v25, et le tag v25 est absent du checkout disponible. Les tests PHP 8.4 ne valident pas à eux seuls le runtime PHP 8.0.
- Aucune instance distante ne sert ce patch par notre intervention. La fixture locale n'établit pas le rendu dans tous les thèmes natifs ni le comportement d'une installation Multicompany réelle.
- Après déploiement, comparer fiche, onglet et rapport global avec un même projet, puis exporter avec/sans le droit Visualiser les marges. Vérifier aussi un compte externe autorisé et un projet partagé entre deux entités, les périodes et un coût incomplet.
- Vérifier les URL directes de génération et la conservation du CSRF et des autres droits avec des sessions réelles. Les refus de génération PDF et les droits d'export sont couverts au niveau fixture ; le bootstrap HTTP complet n'a pas été exécuté.
- Copier tous les fichiers du patch, puis rafraîchir le cache navigateur. Aucune migration ni modification supplémentaire du core n'est requise. Régénérer les documents dont le contenu doit refléter cette version et la permission de l'utilisateur générateur.

Le numéro de version est passé de la préparation 1.5.1 à 1.6.0 à la demande de l’utilisateur. Seules les métadonnées et leurs attentes de test changent ; les validations fonctionnelles ci-dessus restent applicables. Les trois modes de découverte du descripteur et le lint ont été réexécutés après cet alignement.

## Ajustement visuel des bordures

Le tableau de synthèse utilise désormais un contour droit et des séparateurs de 1 px dans la couleur du thème. La classe native `noborder`, dont les coins de dernière ligne ne conviennent pas à cette grille, est retirée de ce seul tableau de présentation. Les séparateurs doublés sont supprimés, notamment après Budget Restant et avant le détail des dépenses. Les calculs et droits restent inchangés.

Après cet ajustement : suite de marge réexécutée sur core24 (870 assertions), lint PHP et diff vérifiés ; fixture locale Edge inspectée aux quatre largeurs indiquées ci-dessus. Le rendu sur une instance déployée reste à vérifier après actualisation du cache navigateur.

## Style natif Eldy

À la demande de l’utilisateur, le contour et les séparateurs verticaux personnalisés sont remplacés par la classe native `bordertop`. Les libellés utilisent toujours `opacitymedium` et `center` ; les couleurs de texte courantes proviennent désormais du thème. Le CSS du module conserve la grille responsive et les tailles des indicateurs. Le détail des dépenses est placé sous la grille également dans le rapport large. Aucun changement de calcul, de droit ou de document.

Lecture de sources : `.bordertop` et son usage de `--colortopbordertitle1` existent dans Eldy sur les tags 20.0.0 à 24.0.0 du checkout core. v25 reste non vérifiée localement ; la capture fournie montre une instance 25.0.0-alpha, sans identifier sa révision ou le code du module servi. Les aperçus utilisent la règle `.bordertop` extraite du core24, avec une variable de couleur et un contexte simulés. Ils ne constituent pas une recette sur une instance complète.

Contrôles après ce changement : 870 assertions sur core20 et core24 sous PHP 8.4.22 ; PHPStan niveau 5, cible PHP 8.0 avec core24, sans erreur ; lint et diff réussis. Aperçus Edge à 1600, 760, 390 et 320 px, sans débordement horizontal. Aucune installation ni modification du core.
