# Vérifications des coûts d’expédition

Ces tests CLI utilisent les classes natives d’un checkout Dolibarr, les arrondis natifs et une base relationnelle SQLite en mémoire. L’adaptateur traduit uniquement les différences SQL de syntaxe nécessaires aux fixtures. Il ne simule pas une installation opérationnelle de Dolibarr ou du module Multicompany.

Prérequis : PHP CLI 8.0+, PDO SQLite, ZipArchive, bibliothèques PhpSpreadsheet/TCPDF incluses dans le core. `DOLIBARR_ROOT` désigne son répertoire `htdocs`. Aucun accès à une base ERP existante. Les fichiers temporaires restent dans `test/.cache/`, ignoré par Git. Les anciennes dépendances natives peuvent émettre des dépréciations sur PHP 8.5 ; les tests d’exports masquent ces seules dépréciations, pas les avertissements ni les erreurs.

```sh
export DOLIBARR_ROOT=/chemin/vers/dolibarr/htdocs
export DOLIBARR_VERSION=20.0.0 # version correspondant au checkout testé
php test/costledger_test.php
php test/costsettings_test.php
php test/descriptor_bootstrap_test.php
php test/descriptor_bootstrap_test.php legacy
php test/descriptor_bootstrap_test.php preloaded
php test/costcategories_test.php
php test/costlist_test.php
MULTICOMPANY_ROOT=/chemin/vers/multicompany php test/costcategorysharing_test.php # avec un core compatible avec ce module tiers
php test/costsnapshots_test.php
php test/costpdf_test.php
php test/costproviders_test.php
python3 test/run_phpstan.py --core "$DOLIBARR_ROOT" --phar /chemin/vers/phpstan.phar
python3 test/check_native_contracts.py --repository /chemin/vers/dolibarr
```

`costsources_test.php` et `costexports_test.php` peuvent également être exécutés seuls. Les suites supérieures incluent leurs prérequis ; les comptes d’assertions sont cumulés et ne doivent pas être additionnés entre scripts.

Pour la version 1.4.1, `costvaluation_test.php` couvre les consignes, droits, propagation, conflits, prix libres et accords écran/catégories/graphiques/XLSX/ODS. `costproviders_test.php` ajoute les contrats réels en lecture de DynamicPrices et PriceList : définir `DYNAMICPRICES_ROOT` et `PRICELIST_ROOT` vers leurs checkouts (par défaut, les dossiers frères `dynamicprices` et `pricelist`). Aucune base ERP, aucun recalcul ni aucune écriture dans ces modules ne sont effectués. Les extensions CLI doivent être chargées pour les tests. Sur Windows, elles peuvent être activées avec `php -d extension_dir=.../ext -d extension=pdo_sqlite -d extension=zip -d extension=gd` sans changer `php.ini`.

Les deux tables complémentaires sont créées et leurs index rejoués dans la fixture. Les verrous `FOR UPDATE` sont supprimés par l’adaptateur SQLite : les conflits séquentiels et l’unicité sont testés, pas l’ordonnancement concurrent de MySQL/MariaDB. Le stub PHPStan documente également la forme des options natives de `formconfirm` (les champs optionnels étaient tous marqués obligatoires dans le PHPDoc v20). Le runner exclut les points d’entrée core autonomes qui redéfinissent un `llxHeader()` vide, pour analyser le contrat réel de `main.inc.php` ; aucun fichier du module n’est exclu ni aucune erreur masquée.

## Contrôles automatisés

- Ledger : FIFO, achats absents/antérieurs/postérieurs, couvertures partielles/excédentaires, prix différents, projets/unités séparés, avoirs, prix nul/manquant, ordre stable, régularisations positives/négatives et addition des périodes. Deux cents jeux déterministes supplémentaires vérifient les invariants de couverture et d’additivité.
- Sources SQL : répartitions de factures sans double comptage, statut des expéditions, projet hérité/contradictoire, permissions refusées et périmètres d’entités, filtres SQL et liens natifs d’engagement.
- Instantanés : `Product::fetch()` natif, PMP figé, changement de méthode/tarif/PMP, rejeu, annulation/revalidation, retour en brouillon pendant désactivation, PMP nul et devises incompatibles.
- Rapports : mêmes montants dans le rapport projet, le global, les catégories et les graphiques mensuels ; documents antérieurs conservés pour le rapprochement.
- Catégories commerciales : priorité du produit expédié, expédition sans commande, repli sur la catégorie de ligne, codes identiques entre entités, catégorie étrangère refusée, régularisations et résumés XLSX/ODS relus. `costcategorysharing_test.php` exécute le constructeur et `getEntity()` du module Multicompany fourni avec une configuration isolée : dictionnaire maître, produit partagé, séparation des dictionnaires et absence de double comptage. Le PDF utilise également la fixture catégorisée.
- États : aucune contribution de coût (badge gris), coût nul connu (valorisation complète), coût manquant (incomplet), historique d’ouverture conservé et concordance des libellés d’export. Administration : métadonnées du descripteur et prédicats de compatibilité partagés ; rendu distant à contrôler après mise à jour.
- Liste des produits : quatre états triés dans l’ordre demandé, références naturelles, tri manuel, sélection multiple avant comptage/pagination, unité incompatible prioritaire en cas de prix également manquant et rapport source conservé. Le HTML du sélecteur natif est vérifié avec deux valeurs sélectionnées, activation Select2 et sélection vide sans JavaScript. La navigation, le changement de limite et la remise à zéro sont à vérifier sur l’instance servant le correctif.
- Découverte du module : constructeur natif testé sans classe de compatibilité, avec un ancien fichier sans constantes de version et avec cette ancienne classe déjà chargée. Le descripteur reste autonome ; la suite des réglages vérifie l’alignement des versions minimales avec les contrôles métier.
- Exports : création puis relecture réelle XLSX/ODS (totaux, produits, solde provisoire), PDF généré avec le moteur natif, régularisation négative, mesure native des pieds longs/HTML et d’un hook de pied, refus documentaire sans droit ou répertoire de l’entité propriétaire. Le chemin PDF doit correspondre au téléchargement natif avec une seule référence projet, y compris pour un projet de l’entité 2 consulté depuis l’entité 1 ; un répertoire propriétaire absent interdit tout repli.
- SQL : les deux créations de table sont rejouées deux fois avec un préfixe long dans la fixture. La validation MySQL/MariaDB réelle et la concurrence restent à effectuer sur instance.
- PHPStan : niveau 5, cible PHP 8.0, aucune suppression d’erreur ni baseline. `phpstan-native.stub` corrige exclusivement les contrats PHPDoc natifs trop étroits (confirmation, sélecteurs, dimensions PDF, hooks structurés), vérifiés dans le code core. Les gardes runtime sur les retours natifs sont conservées.

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
9. Version 1.4.1 : ouvrir **Actualiser**, choisir chaque source, saisir zéro, un prix localisé, une valeur invalide et négative. Vérifier l’unité, la devise, le palier, la date, le prix net et la case décochée. Tester la validation avec/sans token, POST–Redirect–GET et la conservation des filtres.
10. Tester deux projets avec le même produit, propagation avec filtres/pagination, projet créé après ouverture, futures expéditions et absence d’écrasement. Modifier une source ou facturer pendant que la modale est ouverte : conflit explicite, aucun enregistrement partiel. Tester deux sessions simultanées et un double envoi.
11. Activer/désactiver les fournisseurs de prix, refuser leurs droits, changer unité/devise/ciblage/palier, comparer historiques antérieurs/postérieurs et DynamicPrices calculé avant/après expédition. Confirmer qu’une consultation ne déclenche aucun recalcul et que les consignes/instantanés persistent après réactivation.

Agenda, Notifications, nouveaux objets CRUD, numérotation, import et cron : non applicables, aucun mécanisme supplémentaire ajouté. Les listeners consomment les événements natifs sans les réémettre. Aucun nouveau partage indépendant n’est déclaré : les tables d’instantanés suivent les objets natifs propriétaires.

Une première recette navigateur a été exécutée sur Dolibarr 24.0.1/PHP 8.3.33 avec une version intermédiaire de la branche ; aucun fichier serveur n’a été déployé par l’agent. La lecture des tags v20–v24 et l’exécution de fixtures sous PHP 8.4.22 ne constituent pas une certification de toutes les combinaisons ERP/PHP/Multicompany. Les résultats datés et les contrôles distants restant à effectuer figurent dans [le compte rendu 1.4.1](../doc/VALIDATION_COST_VALUATION.md).

## Synthèse des projets — 1.5.0

`php test/projectsummary_test.php` utilise `DOLIBARR_ROOT` et `DOLIBARR_VERSION` comme les fixtures précédentes, avec PDO SQLite, Zip et GD. Il inclut les contrôles du rapport et les exports XLSX/ODS, puis vérifie les requêtes de progression, les ventilations, avoirs, montants nuls, périmètres d’accès, hooks natifs, filtres, comptage et synthèse compacte. Aucune connexion à une base ERP n’est effectuée.

Pour la fiche, le test capture la sortie effectivement émise par `mainCardTabAddMore`, comme la page native qui n’affiche pas `HookManager::resPrint`. Il utilise `HookManager::initHooks()` avec `globalcard` déjà initialisé par un autre module, puis avec `projectcard` en premier. Les configurations de navigateur `classic` et `phone` doivent produire chacune un seul bloc et cinq tuiles ; le seul contexte `globalcard`, les refus de permission, l’édition et la désactivation ne doivent rien émettre. Une erreur SQL doit produire un avertissement visible.

Voir [les preuves et la recette restant à réaliser](../doc/VALIDATION_PROJECT_SUMMARY.md). La variable facultative `LMDBAP_SUMMARY_HTML` écrit le fragment de fiche produit par le hook dans un fichier de test choisi, à conserver dans `test/.cache/`.

`LMDBAP_LIST_HTML` écrit une page de fixtures basée sur `list_print_total.tpl.php` natif : cellule d’entité non comptée, déjà comptée, sans entité, progression masquée et décalage ambigu. Servir le module localement puis ouvrir cette page sous `test/.cache/` pour contrôler `js/projectlist.js`. Les deux chargements du script doivent laisser les totaux de page et généraux à 12 cellules dans les deux premiers cas, 11 dans les deux suivants et 10 dans le cas ambigu. Le montant doit conserver sa valeur et sa colonne ; aucune somme des pourcentages n’est ajoutée.
