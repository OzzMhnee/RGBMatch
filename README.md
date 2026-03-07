# RGBMatch

Projet effectué par **Margot Hourdillé** (OzzMhnee), en réponse à un challenge de **Firstruner**.

> Architecture SOLID • Design Patterns • Injection de dépendances • Gestion mémoire RAM

---

## Objectif du projet

**RGBMatch** compare une image de référence (_origin_) à un ensemble d'images de test en analysant la répartition des couleurs **RGB** (rouge, vert, bleu). Un score de similarité (0–100 %) est calculé pour chaque image ; seul le **Top 3** est conservé en mémoire.

Le projet a une **double vocation** :

1. **Fonctionnelle** — comparer des images par composantes couleur.
2. **Pédagogique** — rendre visible la gestion mémoire de PHP/GD et reprise des principes SOLID.

---

## Prérequis

| Élément          | Version minimale                             |
| ---------------- | -------------------------------------------- |
| PHP              | ≥ 7.4                                        |
| Extension `gd`   | activée                                      |
| Extension `curl` | activée                                      |
| Serveur web      | WAMP / Apache / Nginx                        |
| Clé API          | Unsplash (`UNSPLASH_ACCESS_KEY` dans `.env`) |

---

## Installation

```bash
# 1. Cloner le projet dans le répertoire web
git clone <url> /chemin/vers/www/RGBMatch

# 2. Copier la configuration d'environnement
cp .env.example .env

# 3. Renseigner UNSPLASH_ACCESS_KEY dans le .env

# 4. Vérifier les prérequis
php check_requierements.php

# 5. Télécharger les images (origin + tests)
php setup.php

# 6. Lancer l'analyse CLI
php index.php

```

---

## Utilisation

### CLI — Analyse éducative

```bash
php index.php
```

Le script affiche un **cours interactif** : chaque étape (chargement GD, analyse RGB, libération mémoire…) est mesurée et commentée. Les deltas RAM (_used_, _alloc_, _pic_) sont visibles étape par étape.

### Web — Page interactive

```
http://localhost/RGBMatch/public/index.php
```

Interface visuelle avec animations : l'image courante est analysée en direct, les métriques RAM évoluent en barres animées, et le Top 3 se construit visuellement avec des transitions fly/drop.

### Web — Résultats statiques

```
http://localhost/RGBMatch/results.php
```

Page simple affichant l'image d'origine (avec ses barres RGB) et les cartes des 3 images les plus similaires.

---

## Architecture

### Structure des fichiers

```
RGBMatch/
├── index.php                    # Point d'entrée CLI (cours éducatif)
├── setup.php                    # Téléchargement Unsplash
├── results.php                  # Page résultats (web)
├── check_requierements.php      # Vérification prérequis
├── loader.php                   # Autoloader (classmap)
├── .env                         # Configuration (non versionné)
│
├── app/
│   ├── bootstrap.php            # Helpers partagés (base URL, factory wrapper)
│   └── measurement_worker.php   # Worker PHP isolé pour produire les mesures pipeline
│
├── public/
│   ├── index.php                # Endpoint web interactif (JSON + HTML shell)
│   └── results.php              # Wrapper → results.php racine
│
├── assets/
│   ├── shared/
│   │   ├── tokens.css           # :root variables + reset (source unique)
│   │   ├── layout.css           # body, container, nav, sections, reveal
│   │   ├── origin.css           # Section image de référence (partagée)
│   │   ├── bars.css             # Barres RGB génériques
│   │   ├── buttons.css          # Boutons + indicateur de statut
│   │   └── footer.css           # Pied de page
│   ├── index/
│   │   ├── setup.css            # Formulaire d'initialisation
│   │   ├── tri.css              # Animation Top3 + @keyframes
│   │   └── details.css          # Tableau RAM + accordéon détails
│   └── results/
│       └── winners.css          # Cartes Top3 + diff RGB
│
├── docs/
│   └── SITOGRAPHIE.md           # Ressources utiles : PHP, RAM, SOLID, patterns, web
│
├── Classes/
│   ├── Api/                        # Couche infrastructure
│   │   ├── GdImageLoader.php       # Charge les images via GD (Strategy)
│   │   ├── RgbImageAnalyzer.php    # Analyse RGB + instrumentation
│   │   └── UnsplashApiClient.php
│   │
│   ├── Application/
│   │   ├── AnalysisOrchestrator.php                # Workflow complet
│   │   ├── IsolatedMeasurementPayloadProvider.php  # Payload commun CLI/web + cache
│   │   ├── InstrumentedStep.php                    # Helper historique
│   │   ├── ServiceFactory.php                      # Composition root (Factory)
│   │   ├── ComparisonResultRanker.php              # Top-N streaming
│   │   ├── ConsoleRenderer.php                     # Rendu CLI
│   │   ├── IndexVisualPageRenderer.php             # Rendu HTML interactif
│   │   └── ResultsPageRenderer.php                 # Rendu HTML résultats
│   │
│   ├── Builders/
│   │   ├── ImageDataBuilder.php
│   │   └── ComparisonResultBuilder.php
│   │
│   ├── Config/
│   │   ├── EnvFileLoader.php
│   │   └── DirectoryInitializer.php
│   │
│   ├── Metier/
│   │   ├── CRgbPercentage.php          # VO : pourcentages RGB
│   │   ├── CImageData.php              # VO : données d'une image
│   │   ├── CComparisonResult.php       # VO : résultat de comparaison
│   │   ├── PerformanceMonitor.php      # Monitoring temps/checkpoints
│   │   ├── RamStepMeter.php            # Mesure delta RAM d'une sous-étape
│   │   ├── RamDeltaPresenter.php       # Formatage deltas RAM
│   │   └── BytesFormatter.php          # Formatage octets → humain
│   │
│   └── Singletons/
│       └── ConfigurationManager.php     # Singleton avec DI
│
├── Interfaces/
│   ├── IImageAnalyzer.php
│   ├── IInstrumentedImageAnalyzer.php   # Étend IImageAnalyzer (ISP)
│   ├── IImageLoader.php
│   ├── IApiClient.php
│   ├── IImageDataBuilder.php
│   ├── IComparisonResultBuilder.php
│   └── IComparisonResultRanker.php
│
├── Enumerations/
│   └── EImageType.php           # Implémentation compatible PHP 7.4+
│
└── storage/                     # Données générées (non versionné)
    ├── images/origin.jpg
    ├── images/test/*.jpg
    └── results/pipeline-measurements.json # Cache du payload de mesures partagé
```

---

## Organisation du CSS

Le CSS est découpé en fichiers courts et spécialisés selon leur **portée** :

| Fichier               | Contenu                                  | Utilisé par    |
| --------------------- | ---------------------------------------- | -------------- |
| `shared/tokens.css`   | Variables `:root` + reset `*`            | Les deux pages |
| `shared/layout.css`   | body, container, nav, sections, reveal   | Les deux pages |
| `shared/origin.css`   | Carte image de référence                 | Les deux pages |
| `shared/bars.css`     | Barres RGB génériques                    | Les deux pages |
| `shared/buttons.css`  | Boutons + indicateur de statut           | Page index     |
| `shared/footer.css`   | Pied de page                             | Les deux pages |
| `index/setup.css`     | Formulaire d'initialisation              | Page index     |
| `index/tri.css`       | Animation Top3, scan, badges, @keyframes | Page index     |
| `index/details.css`   | Tableau RAM, accordéon, barre de progrès | Page index     |
| `results/winners.css` | Cartes Top3, RGB values, différences     | Page résultats |

---

## Principes SOLID appliqués

### S — Single Responsibility Principle

Chaque classe a **une seule raison de changer** :

| Classe                    | Responsabilité unique                 |
| ------------------------- | ------------------------------------- |
| `GdImageLoader`           | Charger une image GD depuis le disque |
| `RgbImageAnalyzer`        | Calculer les pourcentages RGB         |
| `ImageDataBuilder`        | Construire un `CImageData`            |
| `ComparisonResultBuilder` | Construire un `CComparisonResult`     |
| `ComparisonResultRanker`  | Maintenir un Top-N en streaming       |
| `ConsoleRenderer`         | Afficher du texte formaté en CLI      |
| `PerformanceMonitor`      | Mesurer les temps d'exécution         |
| `AnalysisOrchestrator`    | Orchestrer le workflow complet        |

### O — Open/Closed Principle

**`GdImageLoader`** utilise le **pattern Strategy** : un tableau associatif `$loaders` mappe chaque `IMAGETYPE_*` vers sa fonction GD. Pour supporter un nouveau format (ex. WebP) : **ajouter une entrée**, sans modifier le code existant.

### L — Liskov Substitution Principle

`IInstrumentedImageAnalyzer` **étend** `IImageAnalyzer` sans casser le contrat : tout code typé `IImageAnalyzer` fonctionne avec un `RgbImageAnalyzer`, et le code éducatif peut taper `IInstrumentedImageAnalyzer` pour accéder aux stats détaillées.

### I — Interface Segregation Principle

Les interfaces sont **petites et ciblées** :

- `IImageLoader` : `load()` uniquement
- `IImageAnalyzer` : `analyze()` uniquement
- `IInstrumentedImageAnalyzer` : ajoute `analyzeWithStats()` pour le code éducatif
- `IImageDataBuilder` / `IComparisonResultBuilder` : `build()` uniquement
- `IComparisonResultRanker` : `pushTopN()` + `sortBySimilarityDesc()`

### D — Dependency Inversion Principle

Toutes les dépendances pointent vers des **abstractions** (interfaces), jamais vers des classes concrètes :

```
AnalysisOrchestrator → IInstrumentedImageAnalyzer (pas RgbImageAnalyzer)
ImageDataBuilder     → IImageAnalyzer             (pas RgbImageAnalyzer)
RgbImageAnalyzer     → IImageLoader               (pas GdImageLoader)
```

---

## Gestion mémoire (RAM)

### Le problème pédagogique

Une image JPEG de 200 Ko sur disque peut occuper **12 Mo** en RAM une fois décompressée par GD (bitmap truecolor = 4 octets/pixel). Ce projet rend visible cet écart.

### Trois mesures affichées

| Mesure    | Signification                                                            |
| --------- | ------------------------------------------------------------------------ |
| **used**  | Mémoire réellement occupée par les variables/objets PHP                  |
| **alloc** | Mémoire réservée par l'allocateur PHP (souvent > used, c'est la réserve) |
| **pic**   | Plus haut niveau d'allocation atteint durant toute l'exécution           |

### Cohérence CLI / web

Les mesures affichées dans la CLI et dans `public/index.php` sont produites à partir du **même payload partagé**. Cela évite les divergences causées par l'état mémoire du process hôte (buffers de sortie, rendu HTML, progression CLI, etc.).

### Stratégies de libération

1. **`imagedestroy()`** dans `GdImageLoader::free()` — libère les pixels GD
2. **`unset()`** — retire la référence PHP
3. **`gc_collect_cycles()`** — force le ramasseur de cycles
4. **`gc_mem_caches()`** (si disponible) — libère des caches internes du GC
5. **Streaming Top-N** — on ne garde que les 3 meilleurs résultats, pas tous

### Observation clé

> Après `unset()`, `used` peut baisser mais `alloc` reste souvent stable.
> Ce n'est **pas** une fuite : PHP garde une réserve pour les allocations futures.

---

## Notes techniques

- Les images proviennent de l'API **Unsplash** et sont téléchargées aléatoirement avec `count=N`.
- L'interface web demande au minimum **6 images de test** pour reproduire les 6 permutations pédagogiques du chapitre 9.
- Les mesures détaillées sont mises en cache dans `storage/results/pipeline-measurements.json` et régénérées automatiquement si les images, options ou fichiers de calcul changent.
- Le projet est compatible **PHP 7.4 → 8.4**.
- Les fichiers `.env`, `storage/` et `*.bak` ne doivent **pas** être versionnés.
