# Validation de la branche expéditions — 2026-09-08

## Périmètre

Évolution fonctionnelle du module externe Advanced Project, depuis `b6dbc4b2b68ab81fac081361768efac2a60b7df7` (version publiée 1.3.0). Branche dédiée : `codex/expeditions-rapport-budgetaire`. Aucun fichier core modifié ; aucun déploiement, aucune release ni PR. L’identifiant existant du module, **450021**, et sa famille **Les Métiers du Bâtiment** sont conservés ; aucun nouvel identifiant n’est attribué.

Les modifications portent sur le rapprochement et les instantanés, le descripteur et son listener, les réglages, les accès, l’onglet produits, les rapports et exports, les traductions et la documentation. La version du descripteur reste 1.3.0 ; les ajouts sont documentés comme non publiés en release dans `ChangeLog.md`. L’onglet À propos continue de lire les métadonnées du descripteur. Compatibilité utilise la liste centralisée enrichie.

## Contrôles exécutés

Environnement : PHP CLI **8.5.7**, base SQLite en mémoire pour les fixtures ; classes et bibliothèques natives extraites des tags **20.0.0** et **24.0.0**. Le paramètre `DOLIBARR_VERSION` est aligné sur le checkout lors du test des événements natifs.

| Contrôle | Résultat |
|---|---|
| Syntaxe des 20 fichiers PHP ajoutés/modifiés | Aucune erreur |
| Moteur chronologique | 635 assertions, dont 200 séries déterministes |
| Sources et instantanés avec core 20 / 24 | 689 assertions cumulées par exécution |
| Réglages et constantes natives avec core 20 / 24 | 677 assertions cumulées par exécution |
| Rapports, catégories, XLSX/ODS et PDF avec core 20 / 24 | 699 assertions cumulées par exécution |
| PHPStan 2.1.29 | Niveau 5, cible PHP 8.0 : aucune erreur avec les définitions natives 20 et 24 |
| Option désactivée | JSON complet normalisé identique au résultat de la bibliothèque initiale sur les fixtures |
| Contrats natifs 20, 21, 22, 23, 24 | Événements, colonnes et composants utilisés présents dans les cinq tags ; SHAs dans le guide fonctionnel |
| SQL | Tables et index séparés, rejoués dans la fixture avec préfixe long ; noms d’index inférieurs à 64 caractères |
| Traductions / diff | Aucune clé dupliquée FR/EN ; contrôle de whitespace passé |

Les comptes des suites sont cumulés, car elles incluent leurs prérequis : ne pas les additionner. Le script historique initial signale une variable `$langs` absente de sa déclaration globale ; le code modifié corrige cette anomalie. Les anciennes classes natives 20 émettent des dépréciations de signatures sous PHP 8.5, sans erreur ou avertissement dans les suites finales du patch.

Les tests sérialisent réellement les classeurs XLSX et ODS puis relisent les montants. L’exemple 12/10/4 retourne 126 ; avril retourne 8 pour 48 facturés moins 40 repris. Le PDF est généré par TCPDF/TCPDI natif ; le cas négatif affiche 32 facturés, −40 repris et −8 retenus. Des pages ont été inspectées visuellement, notamment le tableau produits et le pied HTML long. Les pieds vides, longs en texte/HTML et fournis par hook utilisent une mesure native avant rendu ; un pied exceptionnellement haut est refusé explicitement. Les pieds intermédiaires et final suivent leur cycle natif.

## Limites et recette restante

Aucune instance opérationnelle Dolibarr/Multicompany ni serveur MySQL/MariaDB n’était disponible. Les fixtures relationnelles vérifient les règles, les filtres d’entité et le refus de droits, mais ne certifient pas les partages du module tiers ni les transactions concurrentes d’une installation réelle. PHP 8.0 n’a pas été exécuté ; la cible est vérifiée statiquement par PHPStan. Les versions 21–23 sont contrôlées par lecture du code, pas par exécution des suites complètes.

Le navigateur n’a pas été testé sur une instance servant le patch. La recette des formulaires, tokens CSRF, pagination, Select2, colonnes masquables, utilisateurs externes, permissions partielles, Multicompany et répertoires documentaires partagés reste celle de [test/README.md](../test/README.md). L’installation/réactivation native complète et les migrations MySQL/MariaDB restent à vérifier avant déploiement.

Les suppressions natives de tarifs sans identifiant de ligne exploitable ne permettent pas de dater sûrement une suppression historique : un tarif disparu sans preuve est signalé non valorisé pour la reconstitution. Les instantanés d’expéditions déjà enregistrés demeurent conservés. Les devises de base incompatibles, unités incompatibles et avoirs ambigus sont signalés ; aucun taux actuel ni retour physique n’est inventé.

Agenda, Notifications, numérotation, nouveaux objets CRUD, import et cron : non applicables, aucun mécanisme ajouté. Les catégories commerciales existantes et les partages des objets natifs sont réutilisés ; aucun partage autonome d’instantanés n’est créé. Les documents sont générés dans le répertoire du projet propriétaire, avec refus si sa configuration est absente.
