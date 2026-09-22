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
| Moteur chronologique | 635 assertions |
| Chaîne complète et fournisseurs optionnels | 785 assertions cumulées |
| Instantanés de validation | 692 assertions cumulées |
| Réglages conservés | 680 assertions cumulées |
| Rapports, XLSX/ODS et génération PDF | 703 assertions cumulées |
| Lint PHP | Tous les fichiers PHP du module hors cache, aucune erreur |
| Contrats natifs | Lecture automatisée des tags 20, 21, 22, 23 et 24 |
| PHPStan | Aucune erreur avec les cores 20 et 24, niveau 5, cible PHP 8.0, sans baseline ni suppression d’erreur |

Les comptes cumulent les prérequis et ne s’additionnent pas. Les tests utilisent les vraies classes Product, Project, ProductFournisseur, DynamicPricesCostService et PriceList ; les permissions et entités sont des fixtures. Les tests d’exports sérialisent et relisent réellement XLSX/ODS. Le PDF est généré avec TCPDF natif ; son tableau de provenance a été rendu en PNG et inspecté, avec un pied long fourni par hook.

Scénarios vérifiés : zéro et texte invalide, arrondis et saisie française, native/PMP/fournisseur net, DynamicPrices actif/réussi/devise/date/droits, choix explicite d’un coût récent, palier PriceList sélectionné sur quantité de commande, historique daté et changement de ciblage, état récent sans coût, priorité des sources, consigne immuable, futur envoi, propagation à deux projets, exclusion des consignes existantes et projets nouveaux, unité/devise/rattachement incompatible, conflits séquentiels, rollback complet, replay sans doublon, couverture partielle puis totale par facture, provenance conservée et cohérence des sorties.

## Instance et limites

L’instance `develop.lesmetiersdubatiment.fr` a été consultée en lecture après connexion fournie par l’utilisateur : Dolibarr **24.0.1**, Multicompany présent. Au premier contrôle, l’administration affichait Advanced Project **1.4.0**. Cela ne valide pas le nouveau code local. Aucun déploiement n’a été effectué par l’agent. Le résultat de la recette sur la branche publiée doit être ajouté seulement après confirmation du code servi.

Il reste à vérifier sur l’instance servant 1.4.1 : installation/réactivation MySQL ou MariaDB, verrous simultanés, modale réelle/Select2, contrôles CSRF, droits partiels et utilisateurs externes, deux entités Multicompany, pagination et exports associés. PHP 8.0 est une cible d’analyse statique ; aucun exécutable PHP 8.0 n’a été utilisé. Les sources des versions 21–23 sont lues, leurs suites complètes ne sont pas exécutées.

La fixture rejoue les créations de tables et index avec un préfixe long. Elle ne remplace ni le chargeur d’installation natif MySQL/MariaDB ni une preuve de verrouillage réel. Agenda, Notifications, nouveaux objets CRUD, API REST, import, cron et numérotation : non applicables à cette évolution ; le listener natif existant est réutilisé.

Commandes reproductibles : voir [test/README.md](../test/README.md). Le guide fonctionnel décrit les [limites historiques et la réactivation](COST_VALUATION.md).
