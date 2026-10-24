# Immollia — Parcours « Zone de Génie »

Application web autonome (PHP + MariaDB, un seul fichier d'interface) qui fait suivre aux adhérentes
de la communauté Immollia le parcours de 4 semaines « Zone de Génie », en autonomie, avec binômes ou
trinômes en option.

## Ce que contient le dossier

| Fichier | Rôle |
|---|---|
| `index.html` | Toute l'interface (accueil, 4 semaines, roue de l'intelligence émotionnelle, binôme, guide visio, profil, impression PDF). |
| `zg-api.php` | Le serveur : comptes, carnet de bord, groupes, notes de binôme. Crée ses tables tout seul au premier appel. |
| `config.example.php` | Modèle de configuration. À copier en `config.php` et à remplir (jamais committé). |
| `logo-immollia.webp` | Logo (recadré). |

## Ce que fait l'application

- **Compte par adhérente**, création protégée par un **code communauté** (remis lors de l'adhésion).
- **Carnet de bord sur 4 semaines** : toutes les questions du carnet papier, enregistrement automatique
  à chaque frappe, barre de progression, case « semaine terminée ».
- **Roue EQ-i 2.0** interactive : 5 familles, 15 compétences avec leur définition ; on en choisit 5, qui
  deviennent les « compétences clés » reprises en semaine 2 (un exemple terrain par compétence).
- **Binôme / trinôme (optionnel, 4 personnes max)** : un code à 6 caractères, un jour et un lien de visio,
  la **grille d'audit côte à côte** (reproduit le livret du coach), et des **notes de binôme** par semaine.
- **Partage choisi** : chacune active, semaine par semaine, la visibilité de ses réponses pour son groupe.
  Sans activation, tout reste privé.
- **Guide visio** : le déroulé minuté des 4 séances d'une heure, avec la boîte à outils (à valoriser / à recadrer).
- **Impression / PDF** du carnet complet.
- **Animatrices** (adresses listées dans `ZG_ADMINS`) : voient tous les groupes et peuvent écrire des notes partout ;
  l'action `admin_adherentes` liste toutes les adhérentes et leur avancement.
- **Mode démo** : si `zg-api.php` ne répond pas (par ex. ouverture du fichier sans serveur), l'application
  bascule sur le stockage du navigateur avec un binôme fictif « Camille » pour illustrer la vue groupe.

## Installation sur l'hébergement (o2switch ou autre PHP 8 + MariaDB)

1. Dans cPanel, créer une base MariaDB + un utilisateur (droits complets).
2. Déposer les 4 fichiers dans le dossier cible (voir « Où l'intégrer » ci-dessous).
3. Copier `config.example.php` en `config.php`, renseigner la base, choisir le **code communauté** et les
   adresses des animatrices.
4. Ouvrir l'URL : la première adhérente crée son accès, les tables sont créées automatiquement.
5. Vérifier que `config.php` n'est pas lisible depuis le web (c'est du PHP, il ne renvoie rien) et que
   le dossier n'est **pas** protégé par mot de passe cPanel (cela casserait l'en-tête `Authorization`).

## Où l'intégrer par rapport à immollia.fr — 3 options

1. **Sous-domaine dédié (recommandé)** : `parcours.immollia.fr` (ou `espace.immollia.fr`, `membres.immollia.fr`).
   Créé en 2 minutes dans cPanel (Sous-domaines → dossier `parcours.immollia.fr/`). Avantages : indépendant
   du site vitrine (WordPress, Wix, Webflow… peu importe), pas de conflit de thème ni de cache, URL claire à
   mettre dans le menu « Espace membres » du site.
2. **Sous-dossier du site** : `immollia.fr/parcours/`. Ne marche bien que si le site vitrine est hébergé sur le
   même compte PHP ; avec WordPress il faut exclure le dossier des règles de réécriture.
3. **Site vitrine ailleurs (Wix, Squarespace…)** : option 1 obligatoire, avec un simple bouton « Mon parcours »
   qui pointe vers le sous-domaine. Un `iframe` est déconseillé (cookies tiers, mobile).

Pour un vrai **extranet Immollia** par la suite, le sous-domaine est aussi la bonne base : le même compte
adhérente peut servir à d'autres outils (annuaire des membres, replays des visios, ressources à télécharger,
autres parcours), chacun ajouté comme un onglet dans `index.html`.

## Idées pour la suite (non faites)

- Rappel automatique par e-mail la veille de la visio du groupe (cron cPanel + `mail()`).
- Tableau de bord animatrice dans l'interface (l'API `admin_adherentes` existe déjà).
- Plusieurs « promotions » (sessions datées du 15/09 au 15/10, etc.) avec un calendrier commun.
- Export CSV des verbatims de semaine 4 pour nourrir la proposition de valeur.
- Mot de passe oublié par e-mail.
