# Valorisation analytique des expéditions

Fonctionnalité optionnelle de la branche de développement d’Advanced Project, compatible avec le socle Dolibarr 20 / PHP 8.0. Aucun mouvement de stock, facture ou écriture comptable n’est créé par ce calcul.

## Activation

Mettre à jour le module puis le désactiver/réactiver pour installer les deux tables historiques et déclarer le listener des événements natifs. Les réglages existants sont conservés. Dans **Configuration → Advanced Project**, activer **Intégrer les expéditions aux coûts** dans l’entité concernée. L’option est désactivée par défaut. Le sélecteur propose le dernier tarif fournisseur net HT (par défaut) ou le PMP à la validation. L’onglet Compatibilité indique les dépendances et le nom des événements utilisés selon la version.

Les deux méthodes sont mémorisées à chaque validation observée après activation de l’option. Changer la méthode recalcule les rapports depuis les instantanés. Désactiver l’option restitue les calculs historiques du module ; désactiver le module conserve ses réglages et les instantanés. Les changements intervenus pendant l’arrêt du listener ne peuvent pas reconstituer un PMP passé.

## Documents, quantités et coûts

Le service commun lit les commandes clients validées/en cours/clôturées, les commandes fournisseurs validées, approuvées, commandées ou reçues, les expéditions validées/en cours/clôturées et les factures fournisseurs valides. Il exclut brouillons et documents annulés. Chaque produit ou service référencé reste identifiable par sa clé native ; aucune correspondance par libellé n’est créée. Les lignes libres conservent leur traitement antérieur.

Le rapprochement est effectué par **projet + produit + unité**, avec départage stable par date métier puis identifiant de ligne. Les quantités facturées déjà disponibles couvrent les expéditions suivantes. Une facture ultérieure couvre d’abord les expéditions les plus anciennes encore provisoires. Une quantité ne couvre jamais deux expéditions.

Les répartitions de factures activées dans le module remplacent la ligne source intégrale. Une quantité attribuée à un autre projet ne couvre pas les expéditions du projet d’origine. Lorsqu’un lien natif commande fournisseur/facture identifie un seul projet fournisseur, cette quantité peut néanmoins libérer son engagement fournisseur : elle est déjà facturée ailleurs. Sans lien précis, la couverture reste limitée au projet et au produit ; aucun libellé n’est utilisé pour deviner le lien.

Les commandes fournisseurs validées/approuvées mais pas encore passées apparaissent en quantité, sans engagement financier, comme dans le calcul budgétaire antérieur. Les engagements couvrent uniquement les quantités commandées non représentées par les achats facturés ou les expéditions. Exemple : **12 commandées, 10 expédiées, 4 facturées → 4 au coût facturé + 6 au coût d’expédition + 2 au coût de commande**. À respectivement 12, 10 et 9 par unité, le coût retenu est **48 + 60 + 18 = 126**.

L’expédition porte le projet prioritaire ; à défaut, la commande client source le fournit. Si les projets se contredisent, le produit et la quantité documentaire restent consultables en infobulle, avec anomalie, sans contribution financière ni couverture. Une commande source absente ou inaccessible est également signalée. Les quantités de ces documents ne sont pas ajoutées aux colonnes contributrices.

## Périodes et régularisations

L’historique antérieur au début de période est chargé pour connaître les couvertures. Seuls les **mouvements financiers datés de la période** entrent dans les coûts affichés. Une facture fournisseur utilise sa date native `datef`, y compris lorsqu’elle est répartie. Les quantités de la liste sont des cumuls arrêtés à la fin de période.

Exemple sans engagement préalable : expédition de **100 en mars**, facture de **120 en avril** → **100 en mars**, **+20 en avril**, **120 sur l’ensemble**. Une facture de 80 produit **−20 en avril**. Si une commande fournisseur de 90 avait déjà créé un engagement en février, les mouvements seraient 90 en février, +10 en mars, +20 en avril. Les graphiques représentent les reprises négatives avec des barres ou des courbes signées.

**Valorisation provisoire restante** est le solde des expéditions non couvertes à la fin de période. **Expéditions et régularisations**, **coûts facturés**, **engagements nets** et **coût retenu** représentent les mouvements de la période : ces colonnes ont volontairement des horizons différents, rappelés dans l’interface et les exports.

Les documents actuellement valides sont la source de vérité. Une correction antidatée, une annulation ou une modification de répartition peut changer un rapport historique. Il s’agit d’un calcul analytique reconstitué, pas d’un journal comptable immuable.

## Prix historiques

- **Dernier tarif fournisseur net HT** : dernier tarif enregistré au plus tard à la date d’expédition, tous fournisseurs appartenant au périmètre de partage autorisé. Ce n’est pas le moins cher. La quantité minimale du conditionnement ne multiplie pas le prix unitaire natif ; la remise en pourcentage et la remise unitaire sont déduites, puis `MU` est appliqué. Les montants utilisent `MT`.
- **PMP** : valeur native retournée par `Product::fetch()` dans le contexte d’entité de la validation. Stock doit être actif. Un PMP explicitement nul est valide.
- Sans date d’expédition, la date de validation est utilisée et signalée. Une date future est plafonnée à la validation : un tarif découvert plus tard ne peut pas modifier l’instantané.
- Les changements de tarifs observés par les événements natifs complètent l’historique net, avec date effective, date d’observation, devise, fournisseur, tarif source et état de fiabilité. L’historique natif seul ne prouve pas les remises anciennes : ces valeurs restent inconnues. Les expressions dynamiques de prix ne sont pas évaluées rétroactivement.
- Les instantanés d’expédition conservent les deux valeurs, leurs devises et sources. Le rejeu d’une même validation ne crée pas de doublon. Annulation, retour en brouillon et suppression désactivent la version ; une nouvelle validation crée une version supplémentaire. Les écritures se font dans la transaction native.
- Une modification de quantité, produit, unité ou date après validation invalide l’utilisation de l’instantané correspondant. Aucun prix actuel ne le remplace silencieusement.

Les prix des factures et commandes sont leurs montants HT en devise de base native. Les tarifs fournisseurs utilisent également la valeur native en devise de base, même si le fournisseur présente un prix dans une autre devise. Une devise de base différente entre entités, ou impossible à établir, produit une anomalie et un montant non valorisé ; aucun taux de change actuel n’est appliqué rétroactivement.

## Valeurs manquantes et limites

Une quantité reste visible même si son coût est inconnu. L’écran et les exports portent **Valorisation incomplète** ; les totaux contiennent alors uniquement les contributions connues. Une quantité inconnue couverte entièrement par une facture n’empêche pas de connaître le total de toutes les périodes, mais les périodes séparées restent incomplètes si la reprise du coût ancien est inconnue.

Les unités différentes ne sont pas converties arbitrairement. Les avoirs restent des contributions financières signées et ne créent aucune couverture de quantité ni retour physique supposé. Les écritures directes en base, imports sans événement natif, suppressions groupées de tarifs sans événement et anciens changements de remise ne fournissent pas de preuve complète. Un tarif supprimé dont le contexte natif ne donne pas l’identifiant ne permet pas de dater sa disparition avec certitude : le recalcul historique sans instantané le signale comme non valorisé. Les instantanés déjà enregistrés restent conservés.

Les instantanés suivent l’entité propriétaire des objets natifs ; ils ne constituent pas un nouvel objet partageable à configurer dans Multicompany. Les partages sont ceux des projets, documents, produits, tiers et tarifs fournisseurs natifs. Le droit de lecture du projet et celui du rapport sont exigés directement avec `hasRight()`. Les liens vers les documents sources respectent séparément leurs droits natifs.

## Liste et exports

L’onglet projet **Liste des produits** utilise les filtres, dates, tri, pagination et colonnes masquables natifs. Les filtres de référence, libellé, type et environnement sélectionnent les sources en SQL. Le tri des quantités et montants calculés, puis la pagination, interviennent après rapprochement pour conserver des résultats corrects. Le filtre Environnement sélectionne les produits concernés mais conserve leurs autres sources autorisées : masquer une facture couvrante ne doit pas créer un faux coût provisoire.

Rapports projet/global, catégories, courbes et exports utilisent le même service. XLSX/ODS ajoutent une liste des produits et une feuille de contributions datées, avec document d’origine de chaque reprise, source du prix, date et anomalies. Le PDF projet reprend le total, les catégories et une synthèse des produits. Les PDF conservent le répertoire documentaire de l’entité du projet ; une configuration documentaire absente provoque un refus.

## Preuves de compatibilité

Lecture de sources effectuée le 2026-09-08. Les tags sont résolus vers les commits suivants ; cette lecture ne constitue pas un test d’instance complète.

| Version | Commit |
|---|---|
| 20.0.0 | `697bf01970740a3339cd99cf055b4428fc5e051c` |
| 21.0.0 | `fd970b582a4d8c5779a2958a4e9f4fce225cf085` |
| 22.0.0 | `49b9a6d19f3deb6d410c0e9b3310e95be4ea7710` |
| 23.0.0 | `57a1f05d490a7a80944a8232e9c613e6556d2704` |
| 24.0.0 | `769c7db907099643558e77d7002c109cfda919e5` |

- [Expedition::valid(), setDraft() et événements natifs v20](https://github.com/Dolibarr/dolibarr/blob/697bf01970740a3339cd99cf055b4428fc5e051c/htdocs/expedition/class/expedition.class.php) : `SHIPPING_VALIDATE` dans la transaction après persistance ; retour en brouillon `SHIPMENT_UNVALIDATE`, annulation `SHIPPING_CANCEL`.
- [Tarifs fournisseurs v20](https://github.com/Dolibarr/dolibarr/blob/697bf01970740a3339cd99cf055b4428fc5e051c/htdocs/fourn/class/fournisseur.product.class.php) : `SUPPLIER_PRODUCT_BUYPRICE_*`, prix unitaire, remises et contexte parfois incomplet de suppression.
- [Tarifs fournisseurs v23](https://github.com/Dolibarr/dolibarr/blob/57a1f05d490a7a80944a8232e9c613e6556d2704/htdocs/fourn/class/fournisseur.product.class.php) : renommage `PRODUCT_BUYPRICE_*`, sélection conditionnelle dans le listener.
- [Historique natif v20](https://github.com/Dolibarr/dolibarr/blob/697bf01970740a3339cd99cf055b4428fc5e051c/htdocs/install/mysql/tables/llx_product_fournisseur_price_log.sql) : absence de remises, d’où la table complémentaire.
- [PMP natif v20](https://github.com/Dolibarr/dolibarr/blob/697bf01970740a3339cd99cf055b4428fc5e051c/htdocs/product/class/product.class.php) : lecture de `pmp` par `Product::fetch()` ; ses options Multicompany doivent être validées sur le module tiers installé.

Les tests et contrôles reproductibles, ainsi que la recette d’instance encore nécessaire, sont décrits dans [test/README.md](../test/README.md).
