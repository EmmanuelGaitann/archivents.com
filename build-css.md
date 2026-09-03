# Recompiler le CSS Tailwind

Le site **n'utilise plus le CDN Tailwind** (`cdn.tailwindcss.com`). Le CSS est
pré-compilé en un fichier statique minifié : `assets/css/tailwind.css`
(chargé par les vues via `<link rel="stylesheet" ...>` avec cache-busting `?v=`).

À refaire **uniquement** quand tu ajoutes/retires des classes Tailwind dans
les vues (`application/views/**/*.php`) ou le JS (`assets/js/**/*.js`).

## Sans Node (recommandé ici — binaire autonome)

1. Télécharger une fois le binaire officiel (Windows) :
   https://github.com/tailwindlabs/tailwindcss/releases
   fichier `tailwindcss-windows-x64.exe` (renommé `tailwindcss.exe`).

2. Depuis la racine du projet, lancer :

   ```
   tailwindcss.exe -c tailwind.config.js -i assets/css/tailwind.input.css -o assets/css/tailwind.css --minify
   ```

   Ou en mode "watch" pendant le développement (recompile à chaque sauvegarde) :

   ```
   tailwindcss.exe -c tailwind.config.js -i assets/css/tailwind.input.css -o assets/css/tailwind.css --watch
   ```

## Avec Node (si disponible)

```
npx tailwindcss@3 -c tailwind.config.js -i assets/css/tailwind.input.css -o assets/css/tailwind.css --minify
```

## Notes

- Le cache-busting (`?v=` basé sur la date du fichier) est automatique : dès que
  `tailwind.css` change, les navigateurs rechargent la nouvelle version.
- Le binaire (~40 Mo) n'a PAS besoin d'être déployé en ligne : seul
  `assets/css/tailwind.css` doit être présent sur le serveur.
- Version Tailwind utilisée : v3.4.x (identique au CDN Play d'origine).
