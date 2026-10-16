# Clôture automatique des projets — proposition non publiée

Cette évolution est préparée depuis `main` (`b226642f817adc8b081bd782cb90f998fca59428`), indépendamment de la PR de marge brute. Le descripteur reste en version `1.5.0` : aucune release, fusion, activation sur instance ni modification de la configuration réelle n’est réalisée.

## Règles et activation

Dans les réglages Advanced Project, sélectionner la condition, le délai d’inactivité Agenda en jours et l’utilisateur d’exécution. Le délai initial est de 30 jours, compris entre 1 et 36 500 jours. La règle et le travail planifié sont désactivés par défaut.

| Condition | Projet éligible après le délai Agenda |
|---|---|
| Factures | Au moins une facture client concernée et toutes payées |
| Commandes | Au moins une commande client non annulée et toutes livrées |
| ET | Les deux ensembles présents et les deux conditions satisfaites |
| OU | Au moins un ensemble présent et complet ; une donnée inaccessible rend néanmoins la décision impossible |

La condition d’inactivité est obligatoire dans les quatre modes. **Un jour représente 24 heures écoulées**, et non une journée calendaire pouvant durer 23 heures au passage à l’heure d’été. Le seuil utilise le helper natif en heures ; il est vérifié à la seconde, sans arrondi à minuit. La date de décision est fixée au début du traitement du projet.

L’activité prend la date la plus récente parmi début, fin, création et modification (`tms`) des événements reliés par `actioncomm.fk_project`, par le couple `elementtype = project/project@projet` et `fk_element`, ou par une tâche du projet. Pour les tâches, le lien `project_task/project_task@projet` fonctionne dès v20 ; `actioncomm.fk_task` est utilisé uniquement à partir de v21. Une saisie ou modification récente d’événement historique reporte donc la clôture.

Seuls les événements d’historique natifs (`percent = -1`) et les actions terminées (`percent = 100`) peuvent appartenir à une période inactive. Une action à faire ou partiellement réalisée, même en retard, bloque la clôture. Le début doit être connu ; une fin antérieure au début, une journée entière sans fin, un statut inconnu ou un événement inaccessible empêchent la décision. Une récurrence sans borne de fin connue bloque la clôture ; sinon sa borne entre dans le calcul du délai. Les dates de création, début et dernière modification du projet constituent aussi un minimum d’observation : une réouverture ou modification récente repart pour un délai complet, même sans événement Agenda enregistré.

La facture doit avoir `paye = 1` **et** le statut natif payé. Un solde abandonné ou un classement sans paiement n’est pas assimilé à un règlement. Les factures remplacées sont ignorées ; les autres brouillons, impayés et abandons restent bloquants pour la condition factures. Tous les types de facture client sont concernés, y compris avoirs et acomptes. Le rattachement direct d’en-tête est conservé ; les ventilations client enregistrées ajoutent leurs factures porteuses de montants ou quantités non nuls, sans doubler une facture, **même si l’éditeur de ventilation est désactivé**. Une facture/ligne ventilée absente ou une entité incohérente rend le contrôle impossible. Le paiement s’apprécie sur la facture entière, car les règlements ne sont pas affectés séparément aux ventilations.

Une commande doit être au statut natif `Delivered`/`Livrée` (`Commande::STATUS_CLOSED`). Le module ne recalcule pas les expéditions et ne marque aucune commande livrée. Les commandes annulées sont ignorées ; les brouillons et livraisons partielles restent bloquants. Le mode factures seul peut clôturer malgré des commandes ouvertes, et le mode commandes seul malgré des impayés : c’est le sens explicite de ces modes. Choisir ET pour exiger les deux.

Après installation de tous les fichiers, réactiver Advanced Project pour enregistrer le droit et le travail natif. Attribuer à l’utilisateur choisi :

- `lmdbadvancedproject.autoclose.run` (nouvel identifiant `45002104`, sans renuméroter les droits existants) ;
- lecture et modification des projets ;
- lecture Agenda, avec accès effectif à chaque événement examiné ;
- lecture des factures et/ou commandes selon le mode ; lecture des ventilations lorsqu’elles sont activées ou qu’une ventilation enregistrée concerne le projet.

Activer ensuite **Clôture automatique des projets — Advanced Project** dans les Travaux planifiés et adapter la fréquence, initialement une heure. Le compte d’exécution explicite est rechargé avec ses droits de l’entité à chaque passage. Le résultat natif indique le nombre de projets examinés et clôturés ; un utilisateur absent ou insuffisamment autorisé, une dépendance désactivée ou une erreur SQL produit un échec visible. Le contrôle ne dépend pas d’une consultation de fiche. Il traite aussi les projets déjà éligibles. Un projet fermé ne sera jamais rouvert automatiquement.

## Mécanismes natifs et intégrité

Le traitement utilise `Project::fetch()`, `restrictedProjectArea()`, `checkUserAccessToObject()`, `getEntity()` et des appels directs à `hasRight()`. L’ID du module reste `450021`, sa famille et les droits existants sont conservés. Le marqueur `modulebuilder.txt` existe déjà. Aucune table, dépendance, API, menu, fichier documentaire métier ni règle de numérotation n’est ajouté.

Le point d’entrée planifié ouvre pour chaque projet une transaction `SERIALIZABLE`, avec `SET TRANSACTION` sans portée session/globale : le réglage concerne seulement cette transaction. Le verrou sur la ligne propriétaire évite deux clôtures, les documents restent verrouillés pendant le contrôle, puis l’Agenda est relu en dernier, après une éventuelle attente sur ces documents. Cette isolation protège aussi les plages vides contre une insertion concurrente, contrairement aux seuls verrous de lignes sous `READ COMMITTED` ; le contrat est décrit par [MySQL — niveaux d’isolation](https://dev.mysql.com/doc/refman/8.0/en/innodb-transaction-isolation-levels.html) et [MariaDB — SET TRANSACTION](https://mariadb.com/docs/server/reference/sql-statements/administrative-sql-statements/set-commands/set-transaction). Un refus de l’instruction d’isolation fait échouer le job avant toute clôture. Les réutilisations internes de `closeProject()` conservent l’imbrication native et doivent porter leur propre garantie d’isolation.

Les projets partagés ne sont clôturés que par le travail de leur entité propriétaire ; ni session ni contexte d’entité n’est modifié par la classe. Une facture, commande, relation tiers ou activité hors périmètre ne disparaît pas du test de complétude : la clôture est refusée. La sélection et les compteurs utilisent le périmètre natif des projets lisibles ; chaque projet exige ensuite les accès natifs de lecture et d’écriture. Le seul droit de modification globale ne remplace pas l’accès à un projet privé. L’accès au tiers du projet n’est pas artificiellement imposé lorsqu’il ne conditionne pas l’accès natif au projet.

La clôture passe exclusivement par `Project::setClose($actor)` et son `PROJECT_CLOSE` natif, avec `oldcopy` et le contexte `trigger_reason = lmdbadvancedproject_auto_close`, `auto_close_rule = invoices/orders/both/either`. Aucun code trigger custom, second événement Agenda ou envoi email n’est ajouté. Les éventuels effets Agenda/Notifications sont ceux déjà configurés pour l’événement natif. Le commit externe est contrôlé ; un échec de clôture annule le projet courant. Les projets précédemment validés restent acquis, avec un résultat d’échec explicite. Le verrou et la relecture du statut empêchent deux passages de clôturer deux fois le même projet.

La pagination avance par identifiant, par lots de 100, sans décalage quand les projets quittent la sélection des ouverts. La fréquence, l’activation, le compte, le délai et la règle sont conservés à la désactivation/réactivation par les mécanismes natifs. Les réglages sont enregistrés ensemble dans l’entité courante, avec CSRF et redirection après succès. L’onglet Compatibilité utilise le même prédicat de dépendances que le traitement serveur. Le sélecteur d’utilisateur emploie son filtre SQL natif en v20 et le contrat USF à partir de v21, avec un contrôle `DOL_VERSION` explicite ; sa requête native est exécutée dans les tests de chaque version.

## Vérifications du 16 octobre 2026

Les résultats ci-dessous portent sur les fichiers corrigés pendant la revue de la PR #10, avant commit ; le SHA final est indiqué dans la PR. Les SHA du tableau désignent les sources core utilisées. PHP CLI : **8.4.22**. Les tests emploient les classes natives Project, User, Interfaces et les transactions natives, avec une base SQLite en mémoire. Le test supprime `FOR UPDATE`, acquitte l’instruction MySQL d’isolation sans en simuler les verrous, fournit un utilisateur de fixture pour les refus individuels et ajoute un listener de preuve temporaire sous `test/.cache/`. Il ne redéfinit pas les méthodes métier natives. Les périmètres Multicompany sont simulés ; aucune instance Multicompany réelle n’est certifiée.

| Core | Révision | Contrats lus | Tests de clôture |
|---|---|---|---|
| 20.0.0 | `697bf01970740a3339cd99cf055b4428fc5e051c` | Oui | 132 assertions cumulées, dont les 89 initiales |
| 21.0.0 | `fd970b582a4d8c5779a2958a4e9f4fce225cf085` | Oui | 133 assertions cumulées |
| 22.0.0 | `49b9a6d19f3deb6d410c0e9b3310e95be4ea7710` | Oui | 133 assertions cumulées |
| 23.0.0 | `57a1f05d490a7a80944a8232e9c613e6556d2704` | Oui | 133 assertions cumulées |
| 24.0.0 | `769c7db907099643558e77d7002c109cfda919e5` | Oui | 133 assertions cumulées |
| 25 développement | `6805f682cb72a0a7e519e0c79f0de5f667eb45e4` | Oui | 133 assertions cumulées ; aucune validation d’une future version finale |

Couverture : modes et absence de documents, Agenda ancien/récent/futur/en cours ou relié par élément, événement non affecté invisible avec le seul droit Agenda personnel, date de saisie récente, délai personnalisé ou nul, absence d’âge connu, brouillons, abandons, facture remplacée, commande annulée, ventilation payée/impayée, refus de droits même administrateur, projet privé, utilisateur externe, tiers introuvable, restriction commerciale, autre entité, clôture dans l’entité propriétaire 2 avec paramètres partagés simulés, activité/document caché, rejeu, événement natif unique, échec de trigger/SQL/début/commit/rollback, transaction imbriquée, identité du compte chargé par le job et 121 projets sur plusieurs pages. Le sélecteur natif exclut les comptes externes et désactivés.

`projectautoclose_review_test.php` reproduit d’abord huit clôtures prématurées sur le code initial `8c45dcb` : modification d’événement ignorée, action à faire/en cours/statut inconnu, événement sans date de début, projet récemment modifié, récurrence sans fin et activité liée seulement à une tâche. Deux autres reproductions montrent l’oubli d’une ventilation quand son éditeur est désactivé et d’une liaison vers une facture absente. Un test supplémentaire échoue avec le calcul calendaire : une inactivité de seulement **23 h 30** traverse à tort un seuil d’un jour au passage à l’heure d’été. Toutes ces reproductions passent après correction.

Les non-régressions ajoutent le seuil à −1/0/+1 seconde sous UTC, Europe/Paris et America/New_York, les récurrences bornées, les dates incohérentes, les journées entières, le maximum de plusieurs événements, le lien `fk_task` conditionné par version, les ventilations orphelines et leurs droits. Une autre reproduction détecte la clôture d’un projet privé avec le droit de modification globale mais sans accès de lecture ; le correctif est contrôlé aussi dans la sélection et les compteurs du job. Un callback de fixture insère une activité entre les lectures documentaires et la décision : ce test vérifie l’ordre de relecture, pas une concurrence entre deux connexions MySQL. Les refus d’isolation et l’échec du commit final du job sont également contrôlés.

`costsettings_test.php` : **710 assertions cumulées** avec ses fixtures prérequises sous core v20, dont l’enregistrement natif du travail, sa conservation sans doublon et la persistance de la règle, du délai et de l’utilisateur. Le constructeur reste autonome avant activation, sans accès à la classe de compatibilité.

PHPStan : niveau 5, cible PHP 8.0, configuration `phpstan.neon` existante avec la nouvelle classe ajoutée, sans baseline ni suppression ajoutée. Les commandes reproductibles et le résultat final figurent dans la PR. Les contrats de sources sont contrôlés par `test/check_projectautoclose_contracts.py` ; ces recherches ne remplacent pas des essais d’instance.

## Recette d’instance restante

1. Sur une instance de test servant ce commit, vérifier la page de réglages, ses Select2, les valeurs saisies, les refus CSRF et la redirection après succès. Tester avec Agenda, Factures, Commandes et Travaux planifiés désactivés séparément. Vérifier le droit et le travail après réactivation.
2. Choisir un délai court et tester les quatre modes avec un événement ancien terminé, récemment modifié, à faire, en cours, futur, récurrent ou sans date, puis sans événement. Vérifier une réouverture, les liens par tâche, les dates exactes au seuil et le changement d’heure. Confirmer le résultat natif et les éventuels Agenda/Notifications déjà configurés.
3. Tester deux entités Multicompany réelles, des projets et documents partagés, les modes utilisateurs/groupes transverses, un compte privé/commercialement restreint et un événement non accessible. Le travail d’une entité ne doit pas clôturer un projet propriétaire d’une autre.
4. Avec MySQL/MariaDB, tester deux exécutions simultanées et une création/modification d’événement, de tâche, de facture ou de ventilation pendant le contrôle. Partir aussi d’une session `READ COMMITTED`, puis confirmer l’isolation ponctuelle `SERIALIZABLE`, les verrous de plage et le retour au réglage de session après commit/rollback. La fixture SQLite ne valide pas cet ordonnancement ni DebugBar ; ces verrous plus forts peuvent entraîner des attentes ou deadlocks, qui doivent se solder par un échec et un rollback du projet courant.
5. Contrôler désactivation/réactivation avec règle, délai, utilisateur et fréquence personnalisés. Tester un compte désactivé ou privé de droits et une erreur au milieu d’un lot. Confirmer le résultat des projets déjà validés et celui du projet annulé.

Aucun déploiement ni test navigateur sur une instance exécutant ces fichiers n’a été réalisé. PHP 8.0 réel, MySQL/MariaDB, concurrence et effets externes configurés restent à valider avant activation en production.
