# Vérifications 1.4.1 — 2026-09-22

## Environnements et résultats

Implémentation sur la branche `codex/1.4.1-valorisation-produits`, module 450021 (identifiant et famille existants conservés). Aucun identifiant ni droit supplémentaire. Le descripteur porte 1.4.1 ; À propos continue de lire ses métadonnées. Les suites utilisent PHP **8.4.22**, une base SQLite en mémoire et les classes natives extraites des tags suivants :

| Core | Commit lu et exécuté |
|---|---|
| Dolibarr 20.0.0 | `697bf01970740a3339cd99cf055b4428fc5e051c` |
| Dolibarr 24.0.0 | `769c7db907099643558e77d7002c109cfda919e5` |

Les fournisseurs optionnels sont exécutés en lecture depuis les checkouts locaux : PriceList 2.3.0 (`7f857ded24df48cf94495a3579d533d3e98a5f93`) et DynamicPrices 3.0.1 (base `6b4dfec25b831b1fab2b6170667b66db489d3b3e`, checkout comportant des modifications préexistantes). Ces contrats ne constituent pas une certification de toutes leurs anciennes versions.

| Contrôle | Résultat |
|---|---|
| Moteur chronologique | 642 assertions |
| Chaîne complète et fournisseurs optionnels | 792 assertions cumulées |
| Instantanés de validation | 699 assertions cumulées |
| Réglages conservés et métadonnées admin | 696 assertions cumulées |
| Catégories commerciales et états sans coût | 732 assertions cumulées |
| Rapports, XLSX/ODS et génération PDF | 745 assertions cumulées |
| Lint PHP | Tous les fichiers PHP du module hors cache, aucune erreur |
| Contrats natifs | Lecture automatisée des tags 20, 21, 22, 23 et 24 |
| PHPStan | Aucune erreur avec les cores 20 et 24, niveau 5, cible PHP 8.0, sans baseline ni suppression d’erreur |

Les comptes cumulent les prérequis et ne s’additionnent pas. Les tests utilisent les vraies classes Product, Project, ProductFournisseur, DynamicPricesCostService et PriceList ; les permissions et entités sont des fixtures. Les tests d’exports sérialisent et relisent réellement XLSX/ODS. Le PDF est généré avec TCPDF natif ; son tableau de provenance a été rendu en PNG et inspecté, avec un pied long fourni par hook.

Compléments du 22 septembre : le test de catégories échouait avant correction quand une catégorie de ligne prenait priorité sur celle du produit. Les scénarios couvrent maintenant les expéditions sans commande, les codes identiques entre entités, le refus d’une catégorie étrangère, la reprise à la facturation et les résumés XLSX/ODS relus. La fixture PDF contient aussi la catégorie commerciale. Les états distinguent absence de contribution (gris), zéro connu (complet) et prix manquant (incomplet), y compris l’historique d’ouverture. Les pages admin reprennent les tableaux et colonnes natifs du modèle Diffusion, le même accès administrateur/module actif, les métadonnées du descripteur et les diagnostics centralisés ; leur nouveau rendu n’a pas encore été validé sur l’instance distante.

Correctif de découverte du module : l’erreur utilisateur `Undefined constant LmdbAdvancedProjectCompatibility::MIN_PHP_VERSION` a été reproduite avant correction avec un descripteur récent et une classe ancienne sans ces constantes. Le descripteur déclare désormais directement les minima natifs, sans charger la classe métier de compatibilité. Six essais passent avec les cores 20 et 24 : fichier ancien, classe ancienne déjà chargée et fichier absent. La suite des réglages conserve 696 assertions sur chaque core, dont l’alignement des minima du descripteur avec les contrôles métier. Ces essais isolés ne prouvent pas la cause du décalage sur le serveur : copie partielle et cache PHP ancien restent à distinguer sur l’instance. Le correctif serveur n’a pas été déployé par l’agent.

Scénarios vérifiés : zéro et texte invalide, arrondis et saisie française, native/PMP/fournisseur net, DynamicPrices actif/réussi/devise/date/droits, choix explicite d’un coût récent, palier PriceList sélectionné sur quantité de commande, historique daté et changement de ciblage, état récent sans coût, priorité des sources, consigne immuable, futur envoi, propagation à deux projets, exclusion des consignes existantes et projets nouveaux, unité/devise/rattachement incompatible, conflits séquentiels, rollback complet, replay sans doublon, couverture partielle puis totale par facture, provenance conservée et cohérence des sorties.

## Instance et limites

L’instance `develop.lesmetiersdubatiment.fr` a été consultée après connexion fournie par l’utilisateur : Dolibarr **24.0.1**, PHP **8.3.33**, Multicompany présent (entité affichée « TEST 1 »). Au premier contrôle, l’administration affichait Advanced Project **1.4.0**, puis **1.4.1** après intervention de l’utilisateur. Le README servi restait cependant celui de 1.4.0 : les derniers compléments publiés au commit `d2acee1` n’étaient pas encore démontrés sur cette instance. Aucun fichier serveur n’a été déployé par l’agent.

Première recette réelle : réactivation native réussie, disponibilité des tables complémentaires confirmée par la page Compatibilité, module laissé actif. Réglages identiques avant/après : éclatements client/fournisseur et périmètre partagé désactivés, coûts d’expédition activés, méthode « Dernier tarif fournisseur net HT ». Aucune consigne ni donnée métier n’a été créée. La modale s’ouvre sur un produit admissible ; Select2 propose coût natif, PMP nul connu et deux prix fournisseurs, avec case de propagation décochée. Choisir une source désactive la saisie libre. Le POST avec token natif refuse un prix négatif, conserve la saisie et ne valorise pas le produit. Le filtre sans résultat affiche la ligne native ; sa remise à zéro fonctionne. La limite 20 → 50 → 20 soumet le formulaire natif ; aucun champ caché `limit` concurrent. Filtres et limite ont été restaurés. Les fournisseurs optionnels sont indisponibles sur cette instance et n’ont pas été activés.

La recette a révélé des libellés d’unités non traduits et des clés brutes dans Compatibilité : correctifs locaux appliqués aux rendus écran/PDF/classeurs et aux traductions natives. À propos expose désormais les métadonnées du descripteur et des liens documentaires dans la présentation native inspirée de Diffusion. Leur vérification distante attend la mise à jour de la branche sur l’instance.

Il reste à vérifier sur l’instance servant le dernier commit : enregistrement réussi et propagation, provenance et exports, rejeu des migrations et conservation d’une consigne, verrous simultanés, CSRF absent/erroné, droits partiels et utilisateurs externes, deux entités Multicompany et intégrations optionnelles. Le moteur et la version MySQL/MariaDB n’ont pas été relevés ; la réactivation native seule ne valide pas la concurrence. PHP 8.0 est une cible d’analyse statique ; aucun exécutable PHP 8.0 n’a été utilisé. Les sources des versions 21–23 sont lues, leurs suites complètes ne sont pas exécutées.

La fixture rejoue les créations de tables et index avec un préfixe long. Elle ne remplace ni le chargeur d’installation natif MySQL/MariaDB ni une preuve de verrouillage réel. Agenda, Notifications, nouveaux objets CRUD, API REST, import, cron et numérotation : non applicables à cette évolution ; le listener natif existant est réutilisé.

Commandes reproductibles : voir [test/README.md](../test/README.md). Le guide fonctionnel décrit les [limites historiques et la réactivation](COST_VALUATION.md).
