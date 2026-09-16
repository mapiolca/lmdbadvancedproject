# Vérifications des coûts d’expédition

Ces tests CLI utilisent les classes natives d’un checkout Dolibarr, les arrondis natifs et une base relationnelle SQLite en mémoire. L’adaptateur traduit uniquement les différences SQL de syntaxe nécessaires aux fixtures. Il ne simule pas une installation opérationnelle de Dolibarr ou du module Multicompany.

Prérequis : PHP CLI 8.0+, PDO SQLite, ZipArchive, bibliothèques PhpSpreadsheet/TCPDF incluses dans le core. `DOLIBARR_ROOT` désigne son répertoire `htdocs`. Aucun accès à une base ERP existante. Les fichiers temporaires restent dans `test/.cache/`, ignoré par Git. Les anciennes dépendances natives peuvent émettre des dépréciations sur PHP 8.5 ; les tests d’exports masquent ces seules dépréciations, pas les avertissements ni les erreurs.

```sh
export DOLIBARR_ROOT=/chemin/vers/dolibarr/htdocs
export DOLIBARR_VERSION=20.0.0 # version correspondant au checkout testé
php test/costledger_test.php
php test/costsettings_test.php
php test/costsnapshots_test.php
php test/costpdf_test.php
python3 test/run_phpstan.py --core "$DOLIBARR_ROOT" --phar /chemin/vers/phpstan.phar
python3 test/check_native_contracts.py --repository /chemin/vers/dolibarr
```

`costsources_test.php` et `costexports_test.php` peuvent également être exécutés seuls. Les suites supérieures incluent leurs prérequis ; les comptes d’assertions sont cumulés et ne doivent pas être additionnés entre scripts.

## Contrôles automatisés

- Ledger : FIFO, achats absents/antérieurs/postérieurs, couvertures partielles/excédentaires, prix différents, projets/unités séparés, avoirs, prix nul/manquant, ordre stable, régularisations positives/négatives et addition des périodes. Deux cents jeux déterministes supplémentaires vérifient les invariants de couverture et d’additivité.
- Sources SQL : répartitions de factures sans double comptage, statut des expéditions, projet hérité/contradictoire, permissions refusées et périmètres d’entités, filtres SQL et liens natifs d’engagement.
- Instantanés : `Product::fetch()` natif, PMP figé, changement de méthode/tarif/PMP, rejeu, annulation/revalidation, retour en brouillon pendant désactivation, PMP nul et devises incompatibles.
- Rapports : mêmes montants dans le rapport projet, le global, les catégories et les graphiques mensuels ; documents antérieurs conservés pour le rapprochement.
- Exports : création puis relecture réelle XLSX/ODS (totaux, produits, solde provisoire), PDF généré avec le moteur natif, régularisation négative, mesure native des pieds longs/HTML et d’un hook de pied, refus documentaire sans droit ou répertoire de l’entité propriétaire.
- SQL : les deux créations de table sont rejouées deux fois avec un préfixe long dans la fixture. La validation MySQL/MariaDB réelle et la concurrence restent à effectuer sur instance.
- PHPStan : niveau 5, cible PHP 8.0, aucune suppression d’erreur ni baseline. `phpstan-native.stub` corrige exclusivement quatre contrats PHPDoc natifs trop étroits (sélecteurs, dimensions PDF, hooks structurés), vérifiés dans le code core. Les gardes runtime sur les retours natifs sont conservées.

La non-régression complète du rapport désactivé peut être comparée à un ancien fichier de bibliothèque fourni en lecture seule :

```sh
git show b6dbc4b2b68ab81fac081361768efac2a60b7df7:lib/budgetreport.lib.php > test/.cache/budgetreport-base.lib.php
LMDBAP_LEGACY_OUTPUT="$PWD/test/.cache/legacy-new.json" php test/costexports_test.php
LMDBAP_BASE_BUDGET_LIB="$PWD/test/.cache/budgetreport-base.lib.php" LMDBAP_LEGACY_OUTPUT="$PWD/test/.cache/legacy-base.json" php test/costexports_test.php
cmp test/.cache/legacy-new.json test/.cache/legacy-base.json
```

## Recette d’instance à effectuer avant déploiement

1. Sur Dolibarr 20/PHP 8.0 puis sur la version du parc, activer, désactiver et réactiver le module : droits inchangés, absence de doublon, conservation du switch à `0`, du choix PMP et d’une constante vide volontaire.
2. Vérifier le switch natif, Select2, l’apparition/disparition de l’onglet sur fiche/notes/documents, l’URL directe avec/sans droits, les filtres et la remise à zéro. Modifier la limite 20/50/100, trier, changer de page et masquer une colonne. Le sélecteur `#limit` doit avoir un parent `form` et aucun champ caché concurrent. Tester bureau et mobile.
3. Valider réellement une expédition puis la remettre en brouillon, annuler et revalider. Contrôler les versions d’instantané en transaction, un rejeu et deux validations concurrentes. Tester date absente, passée et future, changement de tarif/remise et PMP entre validations.
4. Reproduire 12 commandées/10 expédiées/4 facturées et les scénarios mars/avril, avec puis sans répartition de facture. Vérifier écran, PDF, XLSX, ODS, catégories et courbes, y compris un coût négatif et un coût inconnu.
5. Configurer deux entités Multicompany avec produits/projets/documents/tarifs partagés puis non partagés. Vérifier les libellés Environnement, les filtres, le PMP natif de chaque entité et l’absence de fuite ; tester des devises de base identiques puis différentes. Un administrateur sans droit fonctionnel doit rester refusé.
6. Tester les actions de réglage et génération avec token valide, manquant et erroné. Tester un compte externe rattaché à un tiers et des droits partiels.
7. Générer un PDF multipage avec beaucoup de produits, libellés longs, aucun produit, texte de pied vide/court/long/HTML, détails société et hook de pied actifs. Vérifier les sauts et l’absence de chevauchement. Tester un projet partagé dont le répertoire de l’entité propriétaire est absent : aucun repli vers l’entité de consultation.
8. Tester l’installation/rejeu des migrations avec MySQL/MariaDB et un préfixe long ; vérifier les index et les verrous. Aucune table native n’est modifiée par cette évolution, hors constantes et déclarations que gère l’activation native du module.

Agenda, Notifications, nouveaux objets CRUD, numérotation, import et cron : non applicables, aucun mécanisme supplémentaire ajouté. Les listeners consomment les événements natifs sans les réémettre. Aucun nouveau partage indépendant n’est déclaré : les tables d’instantanés suivent les objets natifs propriétaires.

Aucun navigateur connecté à une instance servant ce patch n’a été validé ; aucun déploiement n’est inclus. La lecture des tags v20–v24 et l’exécution de fixtures sous PHP 8.5 ne constituent pas une certification de toutes les combinaisons ERP/PHP/Multicompany.
