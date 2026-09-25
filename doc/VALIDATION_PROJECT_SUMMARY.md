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
- `htdocs/projet/card.php` : hook `mainCardTabAddMore`, contexte `projectcard`, description dans `form`/`fichehalfright`/`table.tableforfield`, sans hook direct sous la description sur les tags étudiés. La page appelle ce hook sans afficher `HookManager::resPrint` : le hook doit émettre directement son HTML. Le module rend un bloc serveur puis le déplace par DOM ; sans JavaScript ou conteneur attendu, il reste au hook. [Source v20](https://github.com/Dolibarr/dolibarr/blob/697bf01970740a3339cd99cf055b4428fc5e051c/htdocs/projet/card.php), [source v24](https://github.com/Dolibarr/dolibarr/blob/769c7db907099643558e77d7002c109cfda919e5/htdocs/projet/card.php).
- `getTitleFieldOfList()`, `natural_search()`, `GETPOST()`, `getEntity()`, `price()` et `price2num()` sont appelés directement. Le rendu des tâches `getTaskProgressView()` inclut des règles de charge propres aux tâches ; les classes natives de sa barre sont réutilisées pour ce ratio de montants, sans détourner un objet Task. Aucun seuil de version supplémentaire n’est introduit.

## Contrôles exécutés

PHP CLI **8.4.22**, Windows ; module **1.5.0**. Les fixtures SQL utilisent SQLite en mémoire avec un préfixe long. Les bibliothèques natives sont celles des tags **20.0.0** et **24.0.0** ; aucun serveur ERP ou Multicompany réel n’est chargé.

- `test/projectsummary_test.php` : **777 assertions cumulées**, dont la suite existante de calculs et exports. Factures validées/payées, avoir, brouillon exclu, ventilation remplaçant la ligne complète, projet sans commande, pourcentage négatif et supérieur à 100, comparaison liste/rapport/fiche, filtre de période réservé au rapport, temps multi-mois et taux utilisateur, frais approuvés, dépassement du budget, mode expéditions activé/désactivé.
- Même suite : entités partagées/refusées, tiers source inaccessible, affectation commerciale, compte externe, administrateur privé du droit rapport, module désactivé, erreur SQL affichée, cinq tuiles et absence en édition. Exécution des hooks par le **HookManager natif**, filtre numérique SQL et son comptage rapide, conservation des paramètres, tri natif, masquage, compteurs de colonnes et zéro requête pour cent cellules rendues. Le chargement compact exécute moins de requêtes que le rapport complet.
- Suites de non-régression sur core20 : `costsettings`, `costsnapshots`, `costcategories`, `costcategorysharing`, `costlist`, `costvaluation`, `costproviders`, `costpdf` ; succès. Elles incluent la sérialisation réelle XLSX/ODS et PDF ainsi que les fixtures de conservation des constantes. Découverte native du descripteur testée sans classe de compatibilité, avec fichier ancien et avec classe ancienne déjà chargée.
- PHPStan : `python test/run_phpstan.py --core test/.cache/core20/htdocs --format table` puis même commande avec `core24` ; **niveau 5**, cible syntaxique PHP **8.0**, configuration `phpstan.neon`, aucune erreur. Ni baseline ni exclusion ajoutée pour masquer une erreur.
- Lint PHP des fichiers modifiés, syntaxe JavaScript et `git diff --check`.
- Navigateur Chromium intégré : HTML produit par le hook, feuille du module et structure de fiche simulée ; bureau **1280 px**, tablette **768 px**, téléphone **390 px**. Cinq tuiles, section unique dans `fichehalfright`, détail des dépenses et absence de débordement horizontal. Variante sans chargement du script : bloc serveur visible. Cette fixture emploie une mise en page native simplifiée, pas le thème complet d’une instance.

### Correction après signalement des tuiles absentes

Le journal fourni le 24 septembre montre le chargement des contextes `projectcard` et `globalcard` du module ainsi que les requêtes de la nouvelle colonne en liste. Il ne prouve ni le SHA exact déployé ni le rendu du navigateur. Aucun contenu métier du journal n’est recopié dans le dépôt.

Le défaut a été reproduit avant correction : le test capturant la sortie réelle de `executeHooks('mainCardTabAddMore', ...)` trouve **zéro bloc au lieu d’un**. Le hook remplissait seulement `resprints`, alors que la fiche native n’affiche pas ce tampon. Le test initial contrôlait le tampon et la fixture navigateur le réutilisait : ils ne prouvaient donc pas l’émission du bloc dans la page native.

Le premier correctif émet directement le bloc, sans changer les calculs ni le placement responsive. La suite corrigée passe sur core20/core24 avec les configurations `classic` et `phone` : un bloc, cinq tuiles, aucun contenu laissé dans le tampon inutilisé. Elle capture aussi l’absence de sortie sans droit, en édition ou module désactivé, et l’avertissement lors d’une erreur SQL. Ces premiers tests ne couvraient pas encore l’ordre des contextes expliqué ci-dessous.

### Coexistence des modules et pied de liste

Le second journal, à 13:46:08 le 24 septembre, montre qu’un autre module initialise `globalcard` avant que `projectcard` existe. La lecture de `HookManager::initHooks()` et `executeHooks()` en v20/v24 confirme que cet ordre est conservé, puis que chaque module est appelé une seule fois. Tester seulement `currentcontext === 'projectcard'` supprimait donc la synthèse. Le test reproduit zéro bloc avant correction avec l’initialisation native. Le hook vérifie désormais la présence de `projectcard` dans les contextes actifs, toujours avec un objet Project, ses droits et son périmètre. Les deux ordres sont testés ; `globalcard` seul reste refusé.

Diagnostic navigateur en lecture seule sur `develop.lesmetiersdubatiment.fr`, version affichée **24.0.1**, entité TEST 1 : aucun bloc de synthèse dans la fiche, 12 cellules en en-tête et dans les lignes de liste, 11 dans le total. Il s’agit du code servi avant ce second correctif, sans preuve du SHA exact déployé. Aucune donnée ni configuration distante n’a été modifiée.

La lecture du module Multicompany local **22.0.1**, commit `0da9f094ec12755107290742faa4b8779a262e73`, `class/actions_multicompany.class.php::printFieldListValue()` (branche `projectlist`), montre une cellule d’entité ajoutée sans incrément de `totalarray['nbfield']`. Notre colonne incrémente déjà ce compteur natif. La version Multicompany exacte du serveur n’a pas été vérifiée. Aucun fichier Multicompany ou core n’est modifié.

Le hook natif `printFieldListFooter` est appelé après le rendu des totaux. Un script limité à la liste contenant la progression insère une cellule vide à la position de l’entité uniquement si un badge Multicompany est présent et qu’une seule cellule manque. Les montants restent ceux du template natif ; une ligne déjà complète, un décalage ambigu ou des cellules fusionnées ne sont pas retouchés. Cette correction de présentation nécessite JavaScript ; le compteur serveur de notre colonne fonctionne aussi sans JavaScript.

Contrôles complémentaires exécutés : template natif `list_print_total.tpl.php` sur core20/core24, puis navigateur avec cellule manquante/déjà comptée, sans entité, progression masquée et décalage ambigu. Totaux de page et structure de total général simulée à partir du même rendu : 12/12 cellules après correction, position du montant conservée, aucun ajout en double malgré deux chargements du script. Nouvelle fixture de fiche issue de la sortie réelle après initialisation `globalcard` en premier : cinq tuiles sous la description, dans `fichehalfright`, à **390 px**, sans débordement horizontal. Le thème reste simplifié ; les nouveaux correctifs ne sont ni déployés ni validés sur le serveur distant par cette intervention.

### Adaptation mobile de la fiche et du rapport

Les captures utilisateur montrent des tuiles superposées et des camemberts partiellement hors écran. Diagnostic en lecture seule sur le rapport servi par Dolibarr **24.0.1** : la règle native `table.noborder > tbody > tr > td` impose `height: 32px` (`theme/eldy/global.inc.php`, règle également observée dans la feuille calculée du navigateur). Cette hauteur subsiste quand le module transforme les cellules en blocs ou en grille. La largeur minimale de 560 px des graphiques explique leur débordement sur téléphone.

Le correctif reste dans le module : hauteur automatique et marges adaptées aux cellules de synthèse, grille commune de deux colonnes sur mobile, dépenses en pleine largeur, une colonne sous 360 px, montants autorisés à revenir à la ligne et taux de facturation sur sa propre ligne. Les graphiques épousent leur conteneur ; leurs légendes natives passent dessous avec abréviation visuelle des seuls libellés trop larges. Les données et textes complets des infobulles sont conservés. Filtres, actions d’export, colonnes de texte et fenêtres de détail s’adaptent aux petites largeurs. Les tableaux utilisent `div-table-responsive-no-min` et sont accessibles au clavier pour le défilement horizontal. Aucun calcul, droit, filtre SQL, export documentaire ni réglage de l’instance n’est changé.

Les deux copies natives de Chart.js **3.7.1**, issues des tags Dolibarr 20.0.0 et 24.0.0 cités plus haut, ont une empreinte SHA-256 identique. `DolGraph::draw_chart()` utilise les options `plugins.legend` sur ce socle ; le correctif réutilise la bibliothèque fournie par Dolibarr, son générateur de légendes, sa mesure de police et ses interactions. Aucune dépendance ajoutée.

Vérifications locales du 24 septembre, PHP **8.4.22** :

- Reproduction avant correction avec la règle Eldy exacte : chaque tuile mesure 32 px, pour un contenu allant jusqu’à 197 px ; après correction, aucun contenu ne dépasse sa cellule.
- Sorties réelles des fonctions PHP sur les fixtures SQL, bibliothèque Chart.js native, règle Eldy et styles de base de thème simulés. **20 cas** dans Chromium : fiche, rapport projet, synthèse à grands montants/solde négatif et rapport global, à **320, 360, 390, 768 et 1280 px**. Aucun chevauchement, aucune tuile rognée, aucun graphique plus large que son conteneur ni débordement horizontal de page. La fixture globale exclut les liens de factures, dont le constructeur natif v24 requiert une connexion `DoliDB` réelle ; ces liens n’ont pas été testés par cette fixture.
- Captures inspectées à 320/360/390 px ; légendes lisibles avec ellipses seulement lorsque nécessaire. Matrice temporelle défilée jusqu’à sa dernière colonne au clavier ; fenêtre de détail de commande ouverte et fermée à 360 px, contenu défilable et dialogue contenu dans l’écran. Aucun avertissement ou erreur JavaScript observé.
- **777 assertions** sur core20 puis core24, syntaxe PHP et PHPStan **niveau 5 / cible PHP 8.0** sans erreur avec les commandes indiquées plus haut. Les modifications sont uniquement de présentation ; aucun nouveau scénario financier n’est introduit.

Limites : ces pages locales incluent les règles natives responsables du défaut et le moteur de graphiques réel, mais pas l’intégralité du thème, les autres modules ni Safari/iOS. Le nouveau code n’est pas déployé sur l’instance ; recette finale sur le téléphone et rafraîchissement du cache CSS restent nécessaires. Les contrôles précédents sur une structure de thème simplifiée ne détectaient pas la hauteur native des cellules.

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
