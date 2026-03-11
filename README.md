# RGBMatch

Projet effectué par **Margot Hourdillé** (OzzMhnee), en réponse à un challenge de [firstruner](https://github.com/firstruner).

> Architecture SOLID • Design Patterns • Injection de dépendances • Gestion mémoire RAM

> Projet conçu pour un usage **local sous WAMP/localhost**. Il n'a pas vocation à être déployé en production.

> Sécurité locale : le setup web filtre strictement le thème de recherche, limite les relances trop rapprochées et nettoie les téléchargements partiels/corrompus. Cela reste une protection d'application locale, pas un durcissement de production.

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

Note ^rojet local : le worker de mesures isolé passe par un endpoint HTTP local partagé entre le web et la CLI. Pour conserver cette isolation mémoire en CLI, le serveur local doit être démarré.
Si vous utilisez un VirtualHost plutôt que http://localhost/RGBMatch, vous pouvez définir `RGBMATCH_LOCAL_BASE_URL` dans le `.env` pour indiquer l'URL locale de base à utiliser côté CLI.

---

## Installation

```bash
# 1. Cloner le projet dans le répertoire web
git clone https://github.com/OzzMhnee/RGBMatch.git

# 2. Copier la configuration d'environnement
cp .env.example .env

# 3. Renseigner UNSPLASH_ACCESS_KEY dans le .env

# 4. Vérifier les prérequis
php check_requierements.php

# 5. Télécharger les images (origin + tests)
php setup.php

# 6. Démarrer WAMP / Apache local

# 7. Lancer l'analyse CLI
php index.php

```

---

## Utilisation

### CLI — Analyse éducative

```bash
php index.php
```

Le script affiche un **cours interactif** : chaque étape (chargement GD, analyse RGB, libération mémoire…) est mesurée et commentée. Les deltas RAM (_used_, _alloc_, _pic_) sont visibles étape par étape.

Si le serveur local n'est pas disponible pour le worker HTTP partagé, le calcul peut retomber sur un traitement in-process côté CLI.

### Web — Initialisation

```
http://localhost/RGBMatch/public/setup
```

La page de setup télécharge l'image d'origine et les images de test depuis Unsplash. Le flux protège désormais les cas de dossier supprimé/renommé, téléchargement partiel, fichier corrompu, réponse non JSON, timeout réseau et relances trop fréquentes, et les cas de "Christophe qui débranche la box pour voir ce que ça donne".

### Web — Page interactive

```
http://localhost/RGBMatch/public/analyse
```

Interface visuelle avec animations : l'image courante est analysée en direct, les métriques RAM évoluent en barres animées, et le Top 3 se construit visuellement avec des transitions fly/drop.

### Web — Résultats statiques

```
http://localhost/RGBMatch/public/results
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
│   ├── CSS_loader.php           # Génération centralisée des balises <link>
│   ├── JS_loader.php            # Génération centralisée des balises <script>
│   ├── nav.php                  # Partial : barre de navigation (onglets Setup / Analyse / Résultats)
│   └── footer.php               # Partial : pied de page commun
│
├── public/
│   ├── index.php                # Front controller (aiguille vers Setup / Analyse / Résultats)
│   ├── analyse.php              # Page d'analyse interactive (run + visualisation)
│   ├── setup.php                # Page d'initialisation (téléchargement Unsplash + aperçu)
│   ├── results.php              # Wrapper → results.php racine
│   └── measurement_worker.php   # Worker HTTP local partagé (web + CLI)
│
├── assets/
│   ├── shared/
│   │   ├── tokens.css           # :root variables + reset (source unique)
│   │   ├── layout.css           # body, container, nav, sections, reveal
│   │   ├── origin.css           # Section image de référence (partagée)
│   │   ├── bars.css             # Barres RGB génériques
│   │   ├── buttons.css          # Boutons + indicateur de statut
│   │   ├── footer.css           # Pied de page
│   │   ├── reveal.js            # IntersectionObserver partagé
│   │   ├── setup.js             # Formulaire setup (partagé Setup + Analyse)
│   │   └── utils.js             # Utilitaires JS partagés
│   ├── index/
│   │   ├── setup.css            # Formulaire d'initialisation
│   │   ├── tri.css              # Animation Top3 + @keyframes
│   │   ├── details.css          # Tableau RAM + accordéon détails
│   │   ├── app.js               # Logique JS de la page analyse
│   │   └── animation.js         # Animations visuelles (fly-to, destroy, transitions)
│   └── results/
│       └── winners.css          # Cartes Top3 + diff RGB
│
├── docs/
│   └── SITOGRAPHIE.md           # Ressources utiles : PHP, RAM, SOLID, patterns, web
│
├── Classes/
│   ├── Api/
│   │   ├── GdImageReaderRepository.php     # Registre des readers GD (RepositoryReader)
│   │   ├── GdImageLoader.php               # Charge les images via GD (RepositoryReader + Strategy)
│   │   ├── RgbImageAnalyzer.php            # Analyse RGB + instrumentation
│   │   └── UnsplashApiClient.php
│   │
│   ├── Application/
│   │   ├── AnalysisOrchestrator.php                # Workflow complet
│   │   ├── IsolatedMeasurementPayloadProvider.php  # Payload commun CLI/web + cache
│   │   ├── InstrumentedStep.php                    # Helper historique
│   │   ├── LocalHttpMeasurementWorkerRunner.php    # Appel HTTP local vers le worker partagé
│   │   ├── SetupService.php                        # Workflow robuste du setup (clean + download + validation)
│   │   ├── ServiceFactory.php                      # Composition root (Factory)
│   │   ├── ComparisonResultRanker.php              # Top-N streaming
│   │   ├── ConsoleRenderer.php                     # Rendu CLI
│   │   ├── PublicPageLayoutRenderer.php            # Layout HTML commun aux pages web
│   │   ├── SetupPageRenderer.php                   # Rendu HTML page d'initialisation
│   │   ├── IndexVisualPageRenderer.php             # Rendu HTML page analyse interactive
│   │   └── ResultsPageRenderer.php                 # Rendu HTML page résultats
│   │
│   ├── Builders/
│   │   ├── ImageDataBuilder.php
│   │   └── ComparisonResultBuilder.php
│   │
│   ├── Config/
│   │   ├── EnvFileLoader.php
│   │   └── DirectoryInitializer.php
│   │
│   ├── IO/
│   │   ├── LockedFileSession.php      # Cycle open/lock/process/close reutilisable
│   │   └── LockedJsonFileStore.php    # Lecture/ecriture JSON verrouillee
│   │
│   ├── Security/
│   │   └── RequestThrottle.php        # Limitation simple des relances setup
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
│   ├── IImageReaderRepository.php       # Contrat du registre de readers GD
│   ├── IApiClient.php
│   ├── IImageDataBuilder.php
│   ├── IComparisonResultBuilder.php
│   ├── IComparisonResultRanker.php
│   ├── IJsonFileStore.php               # Contrat lecture/écriture JSON verrouillée
│   └── IMeasurementWorkerRunner.php     # Contrat du worker HTTP de mesures
│
├── Enumerations/
│   └── EImageType.php           # Implémentation compatible PHP 7.4+
│
└── storage/        # Données générées (non versionné)
    ├── images/origin.jpg
    ├── images/test/*.jpg
    └── results/pipeline-measurements.json  # Cache du payload de mesures partagé
```

---

## Organisation du CSS

Le CSS est découpé en fichiers courts et spécialisés selon leur **portée** :

| Fichier               | Contenu                                  | Utilisé par         |
| --------------------- | ---------------------------------------- | ------------------- |
| `shared/tokens.css`   | Variables `:root` + reset `*`            | Toutes les pages    |
| `shared/layout.css`   | body, container, nav, sections, reveal   | Toutes les pages    |
| `shared/origin.css`   | Carte image de référence                 | Analyse + Résultats |
| `shared/bars.css`     | Barres RGB génériques                    | Analyse + Résultats |
| `shared/buttons.css`  | Boutons + indicateur de statut           | Setup + Analyse     |
| `shared/footer.css`   | Pied de page                             | Toutes les pages    |
| `index/setup.css`     | Formulaire d'initialisation              | Setup + Analyse     |
| `index/tri.css`       | Animation Top3, scan, badges, @keyframes | Page analyse        |
| `index/details.css`   | Tableau RAM, accordéon, barre de progrès | Page analyse        |
| `results/winners.css` | Cartes Top3, RGB values, différences     | Page résultats      |

## Organisation du JS

Le JavaScript est externalisé dans `assets/` et chargé via `JS_loader.php` :

| Fichier              | Contenu                                         | Utilisé par      |
| -------------------- | ----------------------------------------------- | ---------------- |
| `shared/reveal.js`   | Révélation progressive via IntersectionObserver | Toutes les pages |
| `shared/utils.js`    | Utilitaires partagés (helpers communs)          | Toutes les pages |
| `shared/setup.js`    | Formulaire setup (relance, progression, aperçu) | Setup + Analyse  |
| `index/app.js`       | Orchestration du run, progression, détails      | Page analyse     |
| `index/animation.js` | Animations visuelles (fly-to, destroy, Top3)    | Page analyse     |

---

## Principes SOLID appliqués

### S — Single Responsibility Principle

Chaque classe a **une seule raison de changer** :

| Classe                     | Responsabilité unique                       |
| -------------------------- | ------------------------------------------- |
| `GdImageLoader`            | Charger une image GD depuis le disque       |
| `RgbImageAnalyzer`         | Calculer les pourcentages RGB               |
| `ImageDataBuilder`         | Construire un `CImageData`                  |
| `ComparisonResultBuilder`  | Construire un `CComparisonResult`           |
| `ComparisonResultRanker`   | Maintenir un Top-N en streaming             |
| `ConsoleRenderer`          | Afficher du texte formaté en CLI            |
| `PerformanceMonitor`       | Mesurer les temps d'exécution               |
| `AnalysisOrchestrator`     | Orchestrer le workflow complet              |
| `SetupService`             | Piloter un setup robuste de bout en bout    |
| `PublicPageLayoutRenderer` | Générer le layout HTML commun des pages web |
| `SetupPageRenderer`        | Rendu HTML de la page d'initialisation      |
| `LockedFileSession`        | Encapsuler le cycle fichier verrouillé      |
| `LockedJsonFileStore`      | Centraliser le cache JSON verrouillé        |
| `RequestThrottle`          | Limiter les relances coûteuses              |

### O — Open/Closed Principle

**`GdImageLoader`** s'appuie sur un **RepositoryReader** (`GdImageReaderRepository`) qui expose le reader GD à utiliser pour chaque type d'image. Le repository centralise le mapping `IMAGETYPE_*` → fonction GD, tandis que le loader orchestre la lecture et la libération des ressources. Pour supporter un nouveau format (ex. WebP), il suffit d'ajouter une entrée dans ce repository.

### L — Liskov Substitution Principle

`IInstrumentedImageAnalyzer` **étend** `IImageAnalyzer` sans casser le contrat : tout code typé `IImageAnalyzer` fonctionne avec un `RgbImageAnalyzer`, et le code éducatif peut taper `IInstrumentedImageAnalyzer` pour accéder aux stats détaillées.

### I — Interface Segregation Principle

Les interfaces sont **petites et ciblées** :

- `IImageLoader` : `load()` uniquement
- `IImageAnalyzer` : `analyze()` uniquement
- `IInstrumentedImageAnalyzer` : ajoute `analyzeWithStats()` pour le code éducatif
- `IImageDataBuilder` / `IComparisonResultBuilder` : `build()` uniquement
- `IComparisonResultRanker` : `pushTopN()` + `sortBySimilarityDesc()`
- `IImageReaderRepository` : `getReader()` uniquement
- `IJsonFileStore` : `read()` + `write()` (pas de lock exposé)
- `IMeasurementWorkerRunner` : `run()` uniquement

### DRY et maintenabilité des accès disque

Les accès fichiers avec verrou (`fopen` + `flock` + `fclose`) sont externalisés dans `LockedFileSession`. Les fichiers JSON structurés passent en plus par `LockedJsonFileStore`, ce qui évite de dupliquer à la fois le verrouillage et la sérialisation. Le cache de mesures et les écritures de fichiers téléchargés reposent ainsi sur des briques réutilisables, plus simples à maintenir et à tester.

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
> Ce n'est **pas** une fuite : PHP garde une réserve pour les besoins futurs.

---

## Notes techniques

- Les images proviennent de l'API **Unsplash** et sont téléchargées aléatoirement avec `count=N`.
- Les téléchargements passent par des fichiers temporaires `.part`, puis sont validés (`getimagesize`, taille minimale, move atomique) avant d'être rendus visibles.
- En cas d'échec de setup, le service nettoie les téléchargements partiels pour éviter un état incohérent entre origine et images de test.
- L'interface web demande au minimum **6 images de test** pour reproduire les 6 permutations pédagogiques du chapitre 9.
- Les mesures détaillées sont mises en cache dans `storage/results/pipeline-measurements.json` et régénérées automatiquement si les images, options ou fichiers de calcul changent.
- Le projet est compatible **PHP 7.4 → 8.4**.
- Les fichiers `.env` et `storage/` ne doivent **pas** être versionnés.
