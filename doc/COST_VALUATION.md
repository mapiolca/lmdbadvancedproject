# Compléter les valorisations — 1.4.1

Après mise à jour, **désactiver puis réactiver Advanced Project** pour créer `lmdbap_cost_instruction` et `lmdbap_cost_fallback`. Les constantes existantes et les données sont conservées. L’option des coûts d’expédition doit être active. Avant réactivation, le rapport historique reste utilisable et l’actualisation est indisponible avec un message explicite.

Installer tous les fichiers du même commit avant la réactivation. Le descripteur déclare directement ses versions minimales pour rester chargeable même si une ancienne classe de compatibilité subsiste pendant la mise à jour. Si PHP conserve encore des fichiers anciens après leur remplacement, faire renouveler le cache OPcache de l’application par l’hébergeur ; une réactivation Dolibarr ne remplace pas les fichiers ni ce cache.

## Résolution commune

Le service de coûts est utilisé par les listes, rapports projet et global, catégories, graphiques et exports. Il conserve d’abord les prix connus de la méthode historique 1.4.0 et les instantanés complémentaires déjà figés, y compris zéro. Pour les coûts manquants, il applique la consigne projet/produit, puis le coût historique PriceList admissible, puis DynamicPrices compatible avec la date. Les coûts non démontrables restent inconnus.

Les consignes sont appliquées avant le rapprochement chronologique. Une facture fournisseur reprend toujours la quantité provisoire correspondante ; les répartitions, régularisations et arrondis natifs `MU`/`MT` sont conservés. Une consigne ne résout pas une anomalie de devise, d’unité ou de rattachement. Si l’unité du produit change, l’ancienne consigne n’est pas convertie arbitrairement.

Les expéditions et leurs régularisations utilisent la catégorie commerciale du produit réellement expédié, y compris sans ligne de commande liée. Cette catégorie est prioritaire sur celle de la ligne ; en son absence, la catégorie de ligne reste le repli existant. Les catégories sont résolues dans l’entité propriétaire du produit ou de la ligne. Elles sont lues depuis les données courantes : changer la catégorie du produit reclasse également ses coûts historiques dans les rapports, sans changer les montants ni les instantanés de prix.

Le badge gris **Aucun coût à valoriser** signifie qu’aucune expédition, facture d’achat ou commande fournisseur engagée ne contribue aux coûts jusqu’à la fin de période. Une commande client ou fournisseur en attente ne prouve pas l’existence d’un prix de revient. Le badge vert **Valorisation complète** reste utilisé pour des coûts connus, y compris zéro ; les anomalies conservent le badge orange **Valorisation incomplète**. Les exports reprennent ces états.

## Actualiser

Le bouton est proposé pour un produit ayant encore des coûts historiques manquants admissibles, avec les droits nécessaires. Une facture couvrant aujourd’hui toute l’expédition n’empêche pas la correction d’une ancienne période restée inconnue.

La modale native propose le prix unitaire HT libre (zéro accepté), le coût Dolibarr et le PMP disponibles, DynamicPrices, les tarifs dégressifs sélectionnés pour les commandes liées et leurs historiques réellement enregistrés, ainsi que les prix fournisseurs nets. Les choix indiquent devise, unité, fournisseur/référence/quantité minimale ou commande/palier/date selon la source. Plusieurs commandes peuvent proposer des prix différents.

La case **« Appliquer cette consigne à tous les projets où ce produit n’est pas valorisé »** est décochée. La recherche n’est pas limitée par les filtres ou la pagination de la liste. Seuls les projets existants, accessibles en lecture et modification, présentant un coût manquant après résolution automatique, sans consigne, et dans la même unité/devise, sont proposés. Un projet créé après l’ouverture n’est pas ajouté. Une future expédition d’un projet traité utilise sa consigne si son coût manque.

Le montant est figé : modifier ou désactiver la source ne le change plus. Il ne modifie aucune fiche produit, aucun tarif fournisseur ni aucune facture. Aucun écran de modification ou de suppression de consigne ni API REST n’est ajouté en 1.4.1.

## Intégrations optionnelles et limites historiques

- **PriceList** : contrats lus dans la version 2.3.0 (`get_price`, `getEffectiveCostPriceForRow`, `getHistory`). La sélection utilise la commande client et la quantité totale de sa ligne, pas la quantité d’une expédition partielle. L’historique automatique retient uniquement le dernier état au plus tard à la date d’expédition, plafonnée à sa validation, du tarif actuellement sélectionné. Produit, entité, ciblage tiers/catégories et palier doivent correspondre. Un changement non vérifiable laisse le coût inconnu. L’historique ne permet pas de reconstruire les anciens rattachements aux catégories, les tarifs supprimés, ni les montants autrefois dérivés du coût Dolibarr ou DynamicPrices. Un état récent sans coût stocké interdit de reprendre arbitrairement une valeur plus ancienne. La sélection manuelle d’un ancien état exploitable est explicite.
- **DynamicPrices** : contrat lu dans la version 3.0.1, classe `DynamicPricesCostService`, clé technique `dynamicsprices`. Ne pas confondre avec le module core `dynamicprices` d’expressions. Le coût doit être actif, calculé avec succès, HT, strictement positif, dans l’entité et la devise de base appropriées. Sa date de calcul doit précéder ou égaler l’expédition pour l’automatisme. Un coût plus récent reste sélectionnable manuellement. Aucun calcul DynamicPrices n’est lancé.
- Les modules doivent être actifs et les contrats accessibles. Une intégration absente reste indisponible sans bloquer le prix libre ni les consignes enregistrées. Un coût complémentaire nul n’est pas proposé ; un zéro connu par les sources historiques ou saisi explicitement reste valide.
- Les nouvelles validations d’expédition enregistrent les coûts complémentaires admissibles et leur provenance dans un instantané lié à la révision native du module. Annulation/revalidation conserve l’ancien historique. Un coût complémentaire déjà figé ne peut pas être remplacé par une consigne ajoutée pour d’autres expéditions du produit.
- **Fournisseurs** : `ProductFournisseur::list_product_fournisseur_price()` fournit les prix accessibles. Les remises en pourcentage et unitaires sont déduites du prix unitaire natif ; le conditionnement n’est pas multiplié une seconde fois. Les expressions de prix fournisseur sont exclues lorsqu’un montant fiable n’est pas démontrable. Aucune conversion à un taux actuel n’est inventée.

Les autorisations de consultation des sources peuvent modifier les compléments automatiques visibles tant qu’ils ne sont pas figés. Un instantané ou une consigne déjà enregistrée constitue ensuite une preuve analytique du projet, indépendante de la disponibilité actuelle du fournisseur de prix.

## Sécurité, stockage et concurrence

Les appels directs à `hasRight()` composent lecture du rapport, lecture/modification native du projet, lecture du produit/service et prix avancés, ainsi que les permissions de DynamicPrices, des commandes et des fournisseurs consultés. Les restrictions natives de projet, tiers, entité et partage restent contrôlées séparément. Aucune nouvelle permission ni élévation administrateur n’est ajoutée.

La proposition est conservée côté session pendant 30 minutes ; le navigateur ne fournit aucun montant source fiable. L’enregistrement est un POST protégé par le token Dolibarr, suivi d’une redirection conservant les filtres. Les projets et les prix sources sélectionnés sont verrouillés, relus et comparés à la proposition. Un changement de montant, de couverture ou de projet provoque un conflit explicite et annule l’ensemble de l’opération. L’unicité `(entity, fk_project, fk_product)` et la clé de requête empêchent les doublons et les écrasements. Une proposition périmée se renouvelle en fermant puis rouvrant la modale.

`entity` est l’entité propriétaire du projet, même consulté par partage. Les données de consigne sont un instantané analytique explicite : montant, unité, devise, source, identifiants, date source, auteur et date de saisie. Elles ne sont pas un second référentiel tarifaire. Aucun objet autonome, partage Multicompany supplémentaire, événement Agenda, notification ou trigger métier n’est créé ; les validations d’expédition utilisent le listener natif existant.

La provenance apparaît dans les infobulles, les contributions XLSX/ODS et un tableau PDF. Les classeurs conservent aussi les identifiants de source, d’historique et de consigne pour audit.

La recette concurrente sur MySQL/MariaDB, les partages Multicompany réels et les formulaires CSRF d’une instance sont nécessaires avant livraison opérationnelle. Voir le [compte rendu des vérifications](VALIDATION_COST_VALUATION.md).
