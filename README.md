# Speed Optimizer – Module PrestaShop

Module PrestaShop d'optimisation de la vitesse de la boutique en ligne, compatible des versions 1.7 à 9.

## Fonctionnalités

- **Diagnostic automatique** via l'API Google PageSpeed Insights
- **Recommandations personnalisées** générées par l'API Claude (Anthropic), avec sélection manuelle des optimisations à appliquer
- **Conversion des images en WebP** (conversion en masse du catalogue, régénération depuis le dossier `img`)
- **Activation du cache navigateur** (règles `.htaccess`)
- **Activation du cache Smarty et compression CSS/JS/HTML**
- **Optimisation de la base de données** (nettoyage des tables de logs + `OPTIMIZE TABLE`)
- **Détection des modules désactivés** (information seulement, aucune suppression automatique)

## Installation

1. Copier le dossier `speedoptimizer` dans `modules/` de votre installation PrestaShop
2. Installer le module depuis le Gestionnaire de modules du back-office
3. Configurer les clés API (Google PageSpeed Insights et Claude) dans la page de configuration du module

## Utilisation

Depuis la page de configuration du module :
1. Cliquer sur **Analyser** pour lancer le diagnostic PageSpeed Insights
2. Cliquer sur **Obtenir des recommandations IA** pour générer des suggestions personnalisées
3. Cocher les recommandations souhaitées et cliquer sur **Appliquer les recommandations sélectionnées**

## Auteur

Rania — Stage d'été 2026
