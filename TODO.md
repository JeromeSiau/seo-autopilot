# TODO

## Fonctionnalités futures

### Édition d'articles + Sync CMS

**Contexte :** Le backend supporte déjà l'édition d'articles (`ArticleController::edit()` et `update()`), mais la page frontend `Articles/Edit.tsx` n'existe pas.

**Scope à implémenter :**
1. Créer `resources/js/Pages/Articles/Edit.tsx` avec :
   - Éditeur de contenu (WYSIWYG ou Markdown)
   - Modification du titre, meta title, meta description
   - Changement de statut (draft, review, approved)
   - Prévisualisation

2. Synchronisation CMS :
   - Après sauvegarde, mettre à jour l'article sur le CMS connecté (WordPress, Webflow, Shopify)
   - Gérer les conflits si l'article a été modifié côté CMS
   - Option : sync automatique vs. sync manuelle

**Priorité :** Moyenne
**Complexité :** Élevée (surtout la sync CMS bidirectionnelle)

---

### Autres idées

- [ ] Régénération d'article avec nouveau prompt
- [ ] Historique des versions d'articles
- [ ] Comparaison avant/après optimisation
- [ ] Suggestions d'amélioration automatiques pour articles en déclin
