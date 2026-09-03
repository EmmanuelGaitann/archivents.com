/**
 * Configuration Tailwind (CSS pré-compilé, production).
 *
 * On NE charge plus le CDN "Play" (cdn.tailwindcss.com) en prod : il exécute
 * un compilateur dans le navigateur de chaque visiteur (lent, FOUC, non
 * recommandé). À la place, on génère un CSS statique minifié dans
 * assets/css/tailwind.css.
 *
 * Pour recompiler après avoir ajouté/retiré des classes : voir build-css.md
 *
 * Tailwind ne détecte que les classes écrites EN CLAIR dans les fichiers
 * ci-dessous (y compris les littéraux JS comme `cell.className = '...'`).
 * Si tu construis un nom de classe dynamiquement (ex. "text-" + couleur),
 * il ne sera pas généré — utilise plutôt des variables CSS (déjà le cas
 * pour les couleurs de thème par mariage).
 */
module.exports = {
  content: [
    './application/views/**/*.php',
    './assets/js/**/*.js',
  ],
  theme: {
    extend: {},
  },
  plugins: [],
};
