# IMMOLLIA — Contexte projet

> Site de **l'association IMMOLLIA** (communauté des femmes de l'immobilier) : https://www.immollia.fr
> Projet **séparé de l'agence ADINA Immobilier** (outil de prospection, dossier `~/Documents/adressefinder`).
> Ne jamais mélanger les deux : rien d'IMMOLLIA dans adressefinder, rien de l'agence ici.

---

## ⚠️ Dépôt PUBLIC + dossier servi tel quel

- Le dépôt GitHub `adinalungu2011-cloud/immollia-site` est **public**.
- Le serveur sert ce dossier tel quel : **tout fichier committé est lisible sur internet**
  (y compris ce `CLAUDE.md`, `README.md`, `A-VALIDER.md` → `https://www.immollia.fr/CLAUDE.md`).
- Donc : **jamais** de mot de passe, de jeton, de clé, d'adresse personnelle ou de donnée de membre
  dans un fichier committé. Le jeton de mise en ligne n'est volontairement **pas** écrit ici.
- Les données (inscriptions) vivent **hors du dossier web**, dans `~/immollia-data/` sur le serveur.

## Ce que contient le site

Site statique (HTML, CSS, JavaScript, sans outil de construction) + quelques petits fichiers PHP.

- `index.html` : accueil (promesse, compteur de membres, piliers, Notre histoire, Agenda, presse, partenaires).
- `zone-de-genie.html` : présentation du parcours Zone de Génie (4 semaines).
- `annuaire.html` + `membre.html` : annuaire des membres (cartes compactes) et fiche détaillée par membre.
- `rejoindre.html` : page d'inscription (adresses courtes `/rejoindre` et `/rent` pour le QR code du salon RENT).
- `inscription.php` : enregistre une inscription dans `~/immollia-data/inscriptions.csv` + e-mail à contact@immollia.fr.
- `membres.php` / `membres-compte.php` : compteur de membres affiché sur le site.
- `assets/` : `site.css`, `site.js`, `membres.js`, logos, portraits, visuels RENT.
- `A-VALIDER.md` : points restant à valider (à relire avant toute nouvelle page).

Charte : fond blanc, framboise du logo `#B31B64` en accent, violet `#8D2A87`, prune `#7B1D79` ;
titres Urbanist, texte DM Sans.

## Mise en ligne (l'utilisatrice ne fait jamais de git ni de cPanel)

Hébergement : compte o2switch **de l'agence**, dossier `~/immollia.fr` (domaine supplémentaire).
Le serveur ne fait que récupérer la dernière version de ce dépôt.

À la fin de chaque modification, sans attendre qu'on le demande (sauf si l'utilisatrice a dit « ne mets rien en ligne ») :
1. Tester : `php -l` sur tout `.php` modifié ; vérifier la page modifiée.
2. `git add -A && git commit -m "..." && git pull --rebase origin main && git push origin main`
3. Lancer l'action **`immollia_update`** du site de l'agence :
   `https://adina-immobilier.fr/api.php?action=immollia_update&t=<jeton>`
   (le **jeton** est celui de « selfupdate », écrit dans le `CLAUDE.md` d'adressefinder — dépôt privé —,
   jamais ici). La réponse doit contenir `"ok":true` et `head` = le commit qu'on vient de pousser.
4. Vérifier : `curl -s -o /dev/null -w "%{http_code}" https://www.immollia.fr/` doit renvoyer `200`.
5. Dire à l'utilisatrice, en français simple, « ✅ en ligne » (ou ce qui a échoué).

Autres actions (même jeton) : `immollia_status` (commit en ligne, sans rien changer) et `immollia_setup`
(création initiale du domaine, déjà faite).
Ces actions vivent dans `api.php` du dépôt adressefinder : les modifier = déployer le site de l'agence.

## Zone de Génie

- `zone-de-genie.html` : la **page de présentation** du parcours.
- `parcours/` : l'**application** du parcours (carnet de bord 4 semaines, roue EQ-i, binômes) →
  `www.immollia.fr/parcours/`. `index.html` (interface) + `zg-api.php` (serveur, crée ses tables tout seul)
  + `config.example.php` (modèle) ; détails dans `parcours/LISEZMOI.md`.
  Rapatriée le 24/10/2026 depuis la branche `claude/zealous-goldberg-iih3sm` d'adressefinder (branche gardée).
- **Pas encore installée sur le serveur** : il manque la base MariaDB et `parcours/config.php` (jamais committé,
  bloqué par `.gitignore` et `.htaccess`). Tant qu'il manque, `zg-api.php` répond « pas encore configuré » et
  l'interface passe en **mode démo** (réponses gardées dans le navigateur seulement) → ne pas la faire connaître
  avant l'installation. Pour l'installer sans cPanel : ajouter une action dans `api.php` d'adressefinder
  (comme `immollia_update`) qui crée la base et écrit `config.php` hors dépôt — avec l'accord d'Adina.
