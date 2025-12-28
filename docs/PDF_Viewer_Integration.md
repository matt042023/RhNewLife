# Intégration PDF.js - Documentation

## Vue d'ensemble

L'intégration de PDF.js permet la visualisation de documents PDF directement dans le navigateur avec des contrôles de navigation, zoom et téléchargement.

## Installation

### 1. Installer les dépendances

```bash
npm install
```

Cette commande installera `pdfjs-dist` version 4.0.0 qui a été ajouté dans `package.json`.

### 2. Builder les assets

```bash
npm run build
# ou pour le développement
npm run watch
```

Webpack copiera automatiquement le worker PDF.js dans `public/build/pdfjs/`.

## Architecture

### Fichiers créés

1. **`assets/controllers/pdf_viewer_controller.js`**
   - Controller Stimulus pour gérer l'affichage PDF
   - Gère la navigation (pages), zoom, erreurs
   - Lazy loading et cache du PDF

2. **`templates/components/documents/_pdf_viewer_placeholder.html.twig`**
   - Template Twig du viewer avec contrôles
   - Intègre Stimulus via data-attributes
   - Support dark mode et responsive

3. **`docs/PDF_Viewer_Integration.md`**
   - Ce fichier de documentation

### Fichiers modifiés

1. **`package.json`**
   - Ajout de `pdfjs-dist: ^4.0.0`

2. **`webpack.config.js`**
   - Configuration `.copyFiles()` pour copier le worker

3. **`templates/admin/documents/view.html.twig`**
   - Remplacement du placeholder par le viewer actif

## Utilisation

### Dans un template Twig

```twig
{% include 'components/documents/_pdf_viewer_placeholder.html.twig' with {
    'document': documentObject,
    'documentUrl': path('document_download', {id: document.id}),
    'downloadRoute': path('document_download', {id: document.id}),
    'height': '600px'  {# Optionnel, default: 600px #}
} %}
```

### Paramètres

- **document** : L'objet Document (pour métadonnées)
- **documentUrl** : URL pour charger le PDF (doit retourner un fichier PDF)
- **downloadRoute** : URL pour le bouton de téléchargement
- **height** : Hauteur du viewer (optionnel, défaut: 600px)

## Fonctionnalités

### Contrôles de navigation

- **Page précédente** : Bouton flèche gauche
- **Page suivante** : Bouton flèche droite
- **Affichage page** : "X / Y" pages

### Contrôles de zoom

- **Zoom in** : Bouton loupe +
- **Zoom out** : Bouton loupe -
- **Reset** : Bouton "100%" (desktop uniquement)
- **Fit to width** : Bouton ajustement largeur
- **Range** : 50% à 300% par pas de 25%

### Gestion d'erreurs

Si le PDF ne peut pas être chargé :
1. Message d'erreur affiché en haut
2. Fallback avec bouton de téléchargement
3. Tous les contrôles désactivés
4. Event `pdf-viewer:error` dispatché

### Performance

- **Lazy loading** : PDF chargé uniquement à la connexion du controller
- **Cache** : Document PDF gardé en mémoire pendant la session
- **Debounce** : Re-render avec délai de 300ms sur resize
- **Cleanup** : Destruction du PDF à la déconnexion (libération mémoire)

## Configuration avancée

### Personnaliser le scale par défaut

```twig
<div data-controller="pdf-viewer"
     data-pdf-viewer-url-value="{{ pdfUrl }}"
     data-pdf-viewer-scale-value="1.0">  {# 100% au lieu de 150% #}
    ...
</div>
```

### Personnaliser les limites de zoom

```twig
<div data-controller="pdf-viewer"
     data-pdf-viewer-min-scale-value="0.25"
     data-pdf-viewer-max-scale-value="4.0"
     data-pdf-viewer-scale-step-value="0.5">
    ...
</div>
```

### Écouter les événements

```javascript
document.addEventListener('pdf-viewer:error', (event) => {
    console.error('PDF Error:', event.detail);
    // Votre logique de gestion d'erreur
});
```

## Compatibilité

### Navigateurs supportés

- Chrome/Edge 90+
- Firefox 88+
- Safari 14+
- Opera 76+

### Formats supportés

- PDF 1.0 à 2.0
- PDF/A (archivage)
- PDF avec annotations
- PDF protégés (lecture seule)

### Limitations

- **Taille max recommandée** : 50 MB
- **Pages max recommandées** : 500 pages
- **CORS** : Le PDF doit être sur le même domaine ou CORS activé
- **Worker** : Nécessite que `pdf.worker.mjs` soit accessible

## Troubleshooting

### Le PDF ne se charge pas

1. **Vérifier la console** : Y a-t-il des erreurs ?
2. **Vérifier l'URL** : Le `documentUrl` retourne-t-il bien un PDF ?
3. **Vérifier CORS** : Si le PDF est sur un autre domaine, CORS est-il configuré ?
4. **Vérifier le worker** : `/build/pdfjs/pdf.worker.mjs` est-il accessible ?

### Le worker n'est pas trouvé

```bash
# Rebuilder les assets
npm run build

# Vérifier que le fichier existe
ls public/build/pdfjs/pdf.worker.mjs
```

### Les contrôles ne fonctionnent pas

1. Vérifier que Stimulus est bien chargé
2. Vérifier la console pour les erreurs JavaScript
3. Vérifier que les `data-action` et `data-target` sont corrects

### Performance lente

- Réduire la taille du PDF
- Compresser le PDF
- Utiliser un scale plus faible par défaut
- Implémenter un système de pagination côté serveur

## Maintenance

### Mettre à jour PDF.js

```bash
npm update pdfjs-dist
npm run build
```

**⚠️ Important** : Tester après chaque mise à jour majeure.

### Monitoring

Events dispatchés:
- `pdf-viewer:error` : Erreur de chargement
- `pdf-viewer:completionUpdated` : Progression (pas implémenté, réservé)

## Ressources

- [Documentation PDF.js](https://mozilla.github.io/pdf.js/)
- [GitHub PDF.js](https://github.com/mozilla/pdf.js)
- [Documentation Stimulus](https://stimulus.hotwired.dev/)

## Support

Pour toute question ou problème, contacter l'équipe de développement.

---

**Version** : 1.0.0
**Date** : 2025-10-28
**Phase** : EP-03 Phase 8
