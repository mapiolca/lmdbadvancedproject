# Validation de la synthèse des projets — 1.5.0

Contrôles du 24 septembre 2026. La version est implémentée sur une branche dédiée pour revue ; elle n’est ni fusionnée, ni déployée, ni publiée comme release par cette intervention.

## Périmètre et règles

- Liste native : colonne « Progressions facturation », pourcentage signé facturé HT / commandé HT et barre avec les classes natives `progress-group`, `progress sm`, `progress-bar-info`. Aucun composant graphique externe. Commandé nul ou négatif : tiret expliqué ; seul le dessin de la barre est limité à 0–100 %.
- Fiche native : cinq tuiles, détail complet du dépensé et avertissements de valorisation incomplète. Calcul toutes dates, partagé avec le rapport, sans chargement des prévisions ni des matrices. Aucun calcul financier en JavaScript.
- Droits directs `hasRight()` sur projets/rapport, puis accès projet et entités. Les contributions clients contrôlent aussi le tiers source, son entité, l’affectation commerciale et le tiers du compte externe. Un tiers facultatif absent n’est pas assimilé à un lien renseigné mais inaccessible. Le filtre est commun aux totaux, détails de commande/facture, budgets et contributions clients des catégories/produits/exportations.
- Les dépenses conservent les règles analytiques existantes du rapport. Les liens documentaires restent soumis aux permissions des documents. Aucun nouveau droit, stockage, partage Multicompany, trigger, événement, cron ou effet métier.
- Réactiver après copie de tous les fichiers pour enregistrer le contexte `projectlist`, puis rafraîchir le cache navigateur. Aucune migration de données. ID existant **450021** et famille **Les Métiers du Bâtiment** conservés ; aucun nouvel ID attribué.

## Preuves de lecture du core

Les fichiers des tags ci-dessous ont été lus localement. Cela prouve les contrats utilisés, pas le fonctionnement d’instances complètes de chaque version.

| Tag | Commit |
|---|---|
| 20.0.0 | `697bf01970740a3339cd99cf055b4428fc5e051c` |
| 21.0.0 | `fd970b582a4d8c5779a2958a4e9f4fce225cf085` |
| 22.0.0 | `49b9a6d19f3deb6d410c0e9b3310e95be4ea7710` |
| 23.0.0 | `57a1f05d490a7a80944a8232e9c613e6556d2704` |
| 24.0.0 | `769c7db907099643558e77d7002c109cfda919e5` |

- `htdocs/projet/list.php` : `doActions` reçoit `arrayfields` par référence avant la sélection native ; hooks `printFieldListSelect`, `Where`, `SearchParam`, `Option`, `Title`, `Value`. Le titre et la première ligne participent chacun à leur compteur natif de colonnes. Le formulaire, les filtres et la pagination demeurent ceux du core. [Source v20](https://github.com/Dolibarr/dolibarr/blob/697bf01970740a3339cd99cf055b4428fc5e051c/htdocs/projet/list.php), [source v24](https://github.com/Dolibarr/dolibarr/blob/769c7db907099643558e77d7002c109cfda919e5/htdocs/projet/list.php).
- Le comptage rapide v24 remplace les champs SELECT puis retire `GROUP BY`. Les expressions de la nouvelle colonne évitent donc `GROUP BY` dans les sous-requêtes utilisées par le filtre. Les données sont agrégées en SQL ; aucun accès SQL n’est exécuté pour chaque cellule rendue.
- `htdocs/projet/card.php` : hook `mainCardTabAddMore`, contexte `projectcard`, description dans `form`/`fichehalfright`/`table.tableforfield`, sans hook direct sous la description sur les tags étudiés. Le module rend un bloc serveur puis le déplace par DOM ; sans JavaScript ou conteneur attendu, il reste au hook. [Source v20](https://github.com/Dolibarr/dolibarr/blob/697bf01970740a3339cd99cf055b4428fc5e051c/htdocs/projet/card.php), [source v24](https://github.com/Dolibarr/dolibarr/blob/769c7db907099643558e77d7002c109cfda919e5/htdocs/projet/card.php).
- `getTitleFieldOfList()`, `natural_search()`, `GETPOST()`, `getEntity()`, `price()` et `price2num()` sont appelés directement. Le rendu des tâches `getTaskProgressView()` inclut des règles de charge propres aux tâches ; les classes natives de sa barre sont réutilisées pour ce ratio de montants, sans détourner un objet Task. Aucun seuil de version supplémentaire n’est introduit.

## Contrôles exécutés

PHP CLI **8.4.22**, Windows ; module **1.5.0**. Les fixtures SQL utilisent SQLite en mémoire avec un préfixe long. Les bibliothèques natives sont celles des tags **20.0.0** et **24.0.0** ; aucun serveur ERP ou Multicompany réel n’est chargé.

- `test/projectsummary_test.php` : **764 assertions cumulées**, dont la suite existante de calculs et exports. Factures validées/payées, avoir, brouillon exclu, ventilation remplaçant la ligne complète, projet sans commande, pourcentage négatif et supérieur à 100, comparaison liste/rapport/fiche, filtre de période réservé au rapport, temps multi-mois et taux utilisateur, frais approuvés, dépassement du budget, mode expéditions activé/désactivé.
- Même suite : entités partagées/refusées, tiers source inaccessible, affectation commerciale, compte externe, administrateur privé du droit rapport, module désactivé, erreur SQL affichée, cinq tuiles et absence en édition. Exécution des hooks par le **HookManager natif**, filtre numérique SQL et son comptage rapide, conservation des paramètres, tri natif, masquage, compteurs de colonnes et zéro requête pour cent cellules rendues. Le chargement compact exécute moins de requêtes que le rapport complet.
- Suites de non-régression sur core20 : `costsettings`, `costsnapshots`, `costcategories`, `costcategorysharing`, `costlist`, `costvaluation`, `costproviders`, `costpdf` ; succès. Elles incluent la sérialisation réelle XLSX/ODS et PDF ainsi que les fixtures de conservation des constantes. Découverte native du descripteur testée sans classe de compatibilité, avec fichier ancien et avec classe ancienne déjà chargée.
- PHPStan : `python test/run_phpstan.py --core test/.cache/core20/htdocs --format table` puis même commande avec `core24` ; **niveau 5**, cible syntaxique PHP **8.0**, configuration `phpstan.neon`, aucune erreur. Ni baseline ni exclusion ajoutée pour masquer une erreur.
- Lint PHP des fichiers modifiés, syntaxe JavaScript et `git diff --check`.
- Navigateur Chromium intégré : HTML produit par le hook, feuille du module et structure de fiche simulée ; bureau **1280 px**, tablette **768 px**, téléphone **390 px**. Cinq tuiles, section unique dans `fichehalfright`, détail des dépenses et absence de débordement horizontal. Variante sans chargement du script : bloc serveur visible. Cette fixture emploie une mise en page native simplifiée, pas le thème complet d’une instance.

## Recette sur instance restant à réaliser

1. Déployer le commit de la PR dans une instance de test et confirmer la version servie. Réactiver en relevant les réglages avant/après ; vérifier conservation des options et droits, contexte de liste actif et cache CSS renouvelé.
2. Dans la liste native réelle, masquer/réafficher la colonne, trier dans les deux sens, filtrer avec `>=50`, réinitialiser puis paginer en 20/50/100. Vérifier le sélecteur `limit` dans le formulaire, les totaux, le colspan vide et la coexistence avec les autres modules.
3. Comparer fiche, rapport sans période et documents clients avec données réelles représentatives ; vérifier fiche ouverte/fermée, compte lecteur seul, compte externe et deux entités avec partage activé puis refusé.
4. Contrôler bureau/mobile avec le thème réel, catégories et autres hooks de fiche. Le placement sous la description dépend du conteneur natif documenté ; le repli reste visible si un autre module le remplace.
5. Mesurer les requêtes et plans MySQL/MariaDB sur un volume représentatif. Les fixtures valident les résultats et l’absence de requêtes par cellule, pas les plans ni les temps d’une base de production.

PHP 8.0 réel, installation complète Dolibarr 20/PHP 8.0, Multicompany réel, recette distante et performances MySQL/MariaDB : **non exécutés**. Aucun état métier ni réglage distant n’a été changé ; seuls le serveur local de prévisualisation et le viewport de test ont été temporaires et arrêtés/restaurés.

## Checklist de livraison

| Domaines du référentiel | Résultat |
|---|---|
| R-01, R-02, R-25 : périmètre/Git/branche/PR | Module seul, branche dédiée, diff complet et SHA distant à vérifier lors du push ; aucune modification core ni changement utilisateur préexistant |
| R-03, R-04, R-21 : versions/descripteur/langues/admin | Socle conservé, version 1.5.0, À propos lit le descripteur, nouvelle fonctionnalité traduite FR/EN, entrée setup unique |
| R-05, R-06, R-08 : entités/droits/montants | Contrôles directs et fixtures ; précision native conservée ; essais multientités réels encore requis |
| R-07, R-09, R-19, R-23 : UI/hooks/listes | Affichage seul, hooks additifs, filtre GET natif, pas de nouveau POST ni de contournement CSRF ; recette native de pagination encore requise |
| R-13, R-14, R-20 : documents/exports | Moteur du rapport partagé, non-régression XLSX/ODS/PDF ; aucun changement de stockage, upload, téléchargement ou massaction |
| R-10 à R-12, R-15 à R-18, R-22 | Pas de nouveau trigger, Agenda, notification, numérotation, API, catégorie/extrafield, schéma ou migration ; composants existants conservés |

Ces statuts distinguent implémentation, lecture du core, analyse statique, simulation et recette réelle. Ils ne constituent pas une certification de toutes les versions annoncées.
