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

## Tableau natif Dolibarr

Correction de présentation demandée après clarification : la synthèse utilise un véritable `<table class="tagtable liste centpercent">` avec `thead`, `tbody`, en-têtes `liste_titre`, lignes `oddeven` et cellules `amount`. La fiche compacte comporte trois paires d’en-têtes/valeurs, puis les dépenses en lignes ; sans permission, Dépensé occupe les deux colonnes de la dernière paire. Le rapport large dispose de cinq ou six colonnes. Le conteneur `div-table-responsive-no-min` gère le défilement horizontal natif sur écran étroit. Les anciennes grilles CSS, bordures et tailles de police spécifiques de synthèse sont supprimées.

Référence : fichier core local `htdocs/admin/tools/ui/content/tables.php`, exemple Basic usage. La page distante indiquée par l’utilisateur n’est pas accessible par l’outil web. La disposition native remplace la transformation du tableau en grille sur téléphone ; le tableau conserve ses lignes/cellules et défile dans son conteneur. Aucune modification des calculs, documents, permissions ou du core.

Contrôles du tableau natif : suite de marge 870 assertions sur core20/core24 sous PHP 8.4.22 ; PHPStan niveau 5/cible PHP 8.0 avec core24 sans erreur ; lint et diff réussis. Contrôle DOM Edge sur 1600/760/390/320 px : lignes réellement `table-row`, sommes des `colspan` cohérentes sur toutes les lignes avec/sans marge, aucun débordement de page ; défilement interne du rapport à 390/320 px. Aperçu avec CSS Eldy local dont les blocs PHP sont retirés et variables de contexte simulées : le style exact de l’instance déployée reste à vérifier.

## Titres sur une ligne

À la demande de l’utilisateur, la fiche affiche désormais ses cinq ou six indicateurs dans une seule ligne d’en-têtes, suivie d’une ligne de valeurs, comme le rapport. L’ordre propre à la fiche reste Commandes, Facturé, Budget, Budget Restant, Dépensé, Marge brute. Sans permission, la colonne Marge brute est omise. Le conteneur natif conserve le défilement horizontal sur mobile, sans réduction des caractères ni conversion des lignes du tableau en grille.

Vérification de cet ajustement : 870 assertions avec core24, lint et diff réussis. DOM Edge à 1600/760/390/320 px : une seule ligne d’en-têtes (six colonnes avec droit, cinq sans), `colspan` cohérents, aucun débordement de page et défilement interne à 390/320 px. PHPStan n’est pas relancé pour cette substitution de l’entier du nombre de colonnes ; le contrôle niveau 5 de la structure native précédente reste pertinent. Pas de déploiement.

## Coin supérieur gauche

Le CSS Eldy local applique l’arrondi gauche de `tagtable liste` au premier `td`, mais ne couvre pas son premier `th`. Une règle limitée à la synthèse transmet le rayon natif du tableau au `thead`, à sa première ligne et à son premier `th` avec `border-top-left-radius: inherit`. Aucun rayon fixe, découpage `overflow: hidden`, réglage ou fichier core modifié ; les infobulles et le défilement natif restent disponibles.

Contrôles du 6 octobre 2026 : 870 assertions core24, lint CSS/PHP et diff réussis. Edge local avec CSS Eldy core24 et valeurs de contexte simulées : rayons du tableau et de ses deux cellules de coin identiques pour 0/6/12 px, avec/sans marge, sur 1600/760/390/320 px ; défilement interne conservé sur mobile. Aperçu visuel relu. PHPStan non relancé : seul le CSS de production change. Le rendu exact de l’instance v25-alpha reste à confirmer après déploiement.

## Recette de la PR #9 — 6 novembre 2026

Les paragraphes précédents décrivent les validations historiques. Cette recette porte sur la branche intégrant `main` au commit `43321d8d95d592c3741fdb6f847f46900cbb74d2`, puis sur le correctif de cache CSS `360ee78e4d1549a274ec71370c5a10b823d869a8`. Déploiement effectué par Git Version Control dans cPanel et recette dans le navigateur intégré, sur develop, Dolibarr 25.0.0-alpha avec Multicompany. La révision exacte du core et le PHP web n'ont pas été établis : ne pas confondre cette instance avec le checkout de fixtures.

### Essais réels

- TEST 1, administrateur : concordance fiche, rapport individuel et ligne globale pour PJ2602-0008 (commandes 1 000 €, dépenses 175 €, marge 825 €, 83 %) et P26001 (commandes nulles, dépenses 10 432 €, marge −10 432 €, taux indéfini). Total global : commandes 1 000 €, dépenses 10 607 €, marge −9 607 €, −961 %, calculé depuis les totaux. Avertissement de coûts incomplets conservé.
- Période du 1er au 31 janvier 2027, exclusion activée, PJ2602-0008 : indicateurs nuls et taux indéfini ; fiche toutes dates inchangée. Sélection effectuée avec les calendriers natifs.
- PDF réel de P26001 : quatre pages, six indicateurs, marge négative et taux indéfini visibles sur la première page ; pied sans chevauchement observé sur cette page. Le contrôle exhaustif des bornes et des cas de montants longs reste celui des PDF de fixtures.
- XLSX et ODS réels, globaux et P26001 : archives relues, marges numériques −9 607 et −10 432 ; ratio global −9,607 (format pourcentage), tiret pour les commandes nulles.
- `utilisateur.test` : seuls les droits natifs de lecture des projets accessibles et du rapport ont été temporairement ajoutés, sans droit de marge. Fiche et rapports à cinq indicateurs, colonne globale de marge absente. Projet autorisé PJ2510-0003 : dépenses 945 €, restant −945 €. Projet hors périmètre refusé par URL directe. Quatre fichiers XLSX/ODS réels relus (individuel et global) : aucune marge explicite dans les parties XML, feuilles et formules ; les cellules suivant les cinq indicateurs portent les autres données autorisées. Le bouton PDF est absent sans droit de génération : absence de marge dans un PDF généré par ce profil non testée en réel, couverte par fixtures.
- Les deux droits ajoutés à `utilisateur.test` ont ensuite été retirés depuis la session administrateur ; leur état initial désactivé et l'absence des droits de marge ont été vérifiés.
- À 390 px : six titres sur une ligne, défilement horizontal interne permettant d'atteindre la marge, sans débordement horizontal de page. Après correction du cache CSS, rayon natif du premier titre identique à celui du tableau (8 px sur cette instance).
- TEST 2 : accès au rapport refusé avec la configuration existante, sans activation de module ni élargissement des droits. Contexte TEST 1 restauré. Deuxième entité et projet partagé non validés en réel ; leurs restrictions restent couvertes par les fixtures.

### Contrôles locaux complémentaires

- Après le correctif CSS : 873 assertions cumulées de `margin_test.php` sur core20, core24 et checkout core25-alpha `6805f682cb72a0a7e519e0c79f0de5f667eb45e4`, sous PHP CLI 8.4.22. Les simulations SQLite ne constituent pas des essais ERP complets. Les caches v21–v23 incomplets ne permettent pas cette exécution ; contrats natifs lus, parcours complets non exécutés.
- `costpdf_test.php` core24 : 774 assertions cumulées ; `margin_pdf_test.py` réussi, cinq cas de marge, absence sans droit et bornes des tuiles. PDF de fixtures rendus et relus visuellement, notamment grands montants et rapport multipage.
- PHPStan niveau 5, cible PHP 8.0, core24 : aucune erreur. Lint et contrôle du diff réussis.
- Paquet de recette du commit `360ee78` : `module_lmdbadvancedproject-1.6.0.zip`, SHA-256 `4ebf05361709b0675932c32d62f9d396fb146faa547f2932501be15b9ad8ae9b`. Contrôle du contenu et 30 essais bootstrap (htdocs/custom/environnement absent) réussis ; validateur ZIP natif core24 réussi. Installation interactive de ce ZIP non exécutée. Ce paquet de recette n'a pas remplacé la release publiée.

La recette ne vaut pas validation réelle de toutes les versions v20–v25, d'un administrateur dont `hasRight()` retourne faux, d'un utilisateur externe, ni d'une génération PDF sans droit de marge. Ces derniers refus sont vérifiés par tests automatisés. Aucun objet métier ou réglage Multicompany n'a été modifié pour fabriquer un scénario.

Après recette, cPanel a été replacé sur sa branche initiale `main`, commit `52fc13811ddf953bcec80aff923bfa32841b7532`. La PR #9 et la release existante n'ont pas été fusionnée ou remplacée par cette restitution. Les documents générés pendant les essais restent des effets de recette, sans suppression de documents préexistants.
