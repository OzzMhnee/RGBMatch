# Sitographie - RGBMatch

Liste de ressources utiles, utilisées  pour le projet, ou trouvée pendant, et qui serviront pour plus tard

---

## 1. PHP officiel

### PHP - site officiel

- URL : <https://www.php.net/>
- A quoi ca sert : point d'entree officiel pour la documentation, les releases, les notes de migration et les extensions.

### PHP Manual - Language Reference

- URL : <https://www.php.net/manual/en/langref.php>
- A quoi ca sert : revoir la syntaxe, les types, les portees, les fonctions anonymes, les exceptions et les comportements du langage.

### PHP Manual - Migration Guides

- URL : <https://www.php.net/manual/en/appendices.php>
- A quoi ca sert : verifier la compatibilite entre versions, notamment pour rester "PHP 7.4 friendly".

### PHP Manual - GD

- URL : <https://www.php.net/manual/en/book.image.php>
- A quoi ca sert : reference officielle pour `imagecreatefromjpeg`, `imagecolorat`, `imagescale`, `imagedestroy` et toutes les fonctions de traitement d'image utilisees par RGBMatch.

### PHP Manual - cURL

- URL : <https://www.php.net/manual/en/book.curl.php>
- A quoi ca sert : comprendre les options `CURLOPT_*`, les timeouts, le telechargement HTTP et la gestion des erreurs reseau.

### PHP Manual - Memory Functions

- URL : <https://www.php.net/manual/en/ref.info.php>
- A quoi ca sert : reference pour `memory_get_usage`, `memory_get_peak_usage`, `phpversion`, `ini_get`.

### PHP Manual - Garbage Collection

- URL : <https://www.php.net/manual/en/features.gc.php>
- A quoi ca sert : comprendre `gc_collect_cycles`, les references circulaires et le comportement du ramasse-miettes PHP.

---

## 2. Images, API et web

### Unsplash for Developers

- URL : <https://unsplash.com/developers>
- A quoi ca sert : documentation officielle de l'API utilisee pour telecharger les images de test et l'image d'origine.

### MDN Web Docs

- URL : <https://developer.mozilla.org/>
- A quoi ca sert : reference HTML, CSS et JavaScript pour les pages `public/index.php`, `results.php`, les animations, `fetch`, `IntersectionObserver` et le responsive.

### web.dev

- URL : <https://web.dev/>
- A quoi ca sert : bonnes pratiques performance, accessibilite, responsive design et qualite front-end.

### Can I Use

- URL : <https://caniuse.com/>
- A quoi ca sert : verifier la compatibilite navigateur de fonctionnalites front-end si RGBMatch evolue vers une cible plus large.

---

## 3. Memoire, RAM et performance

### PHP Manual - `memory_get_usage`

- URL : <https://www.php.net/manual/en/function.memory-get-usage.php>
- A quoi ca sert : comprendre la difference entre la memoire utilisee (`used`) et allouee (`alloc`).

### PHP Manual - `memory_get_peak_usage`

- URL : <https://www.php.net/manual/en/function.memory-get-peak-usage.php>
- A quoi ca sert : suivre le pic memoire d'un script d'analyse d'images.

### PHP Manual - `gc_collect_cycles`

- URL : <https://www.php.net/manual/en/function.gc-collect-cycles.php>
- A quoi ca sert : comprendre quand et pourquoi forcer le GC.

### PHP Manual - `gc_mem_caches`

- URL : <https://www.php.net/manual/en/function.gc-mem-caches.php>
- A quoi ca sert : completer les observations pedagogiques sur la liberation memoire.

### Red Hat - What is virtual memory?

- URL : <https://docs.redhat.com/en/documentation/red_hat_enterprise_linux/10/html/managing_storage_devices/getting-started-with-swap>
- A quoi ca sert : comprendre swap, pagination et pression memoire a un niveau systeme.

### Microsoft Learn - Memory performance and diagnostics

- URL : <https://learn.microsoft.com/>
- A quoi ca sert : Recherches cote Windows.

---

## 4. Architecture, SOLID et design patterns

### Refactoring.Guru - Design Patterns

- URL : <https://refactoring.guru/design-patterns>
- A quoi ca sert : explications claires sur Factory, Builder, Singleton, Strategy, Observer et autres patterns utilises ou cites dans RGBMatch.

### Refactoring.Guru - SOLID

- URL : <https://refactoring.guru/refactoring>
- A quoi ca sert : revoir chaque principe SOLID avec des exemples concrets.


### PHP The Right Way

- URL : <https://phptherightway.com/>
- A quoi ca sert : bonnes pratiques PHP modernes, structure de projet, erreurs courantes, securite, style et outillage.

### PSR Standards - PHP-FIG

- URL : <https://www.php-fig.org/psr/>
- A quoi ca sert : conventions de style, autoloading, interfaces communes et pratiques d'interoperabilite.


## 5. Securite et qualite logicielle

### OWASP Top 10

- URL : <https://owasp.org/www-project-top-ten/>
- A quoi ca sert : garder en tete les grands risques web (XSS, injections, gestion des secrets, etc.).

### OWASP Cheat Sheet Series

- URL : <https://cheatsheetseries.owasp.org/>
- A quoi ca sert : references pratiques pour l'echappement HTML, la validation des entrees, la gestion des erreurs et la configuration sure.

### PHP Manual - `htmlspecialchars`

- URL : <https://www.php.net/manual/en/function.htmlspecialchars.php>
- A quoi ca sert : reference precise pour l'echappement HTML present dans les renderers du projet.

---

## 6. Ressources utiles pour faire evoluer RGBMatch

### Mermaid

- URL : <https://mermaid.js.org/>
- A quoi ca sert : generer ou maintenir rapidement des diagrammes d'architecture et de flux comme dans le README.

### Excalidraw

- URL : <https://excalidraw.com/>
- A quoi ca sert : esquisser rapidement des flux d'analyse, vues UI ou schemas de composants.

### Figma

- URL : <https://www.figma.com/>
- A quoi ca sert : concevoir une interface plus poussee pour une vraie application produit.

### Google Fonts

- URL : <https://fonts.google.com/>
- A quoi ca sert : choisir une typographie adaptee a l'identite visuelle du projet.
