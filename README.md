# mod_webinairev2 — plugin Moodle pour webinairev2

Active une activité Moodle "Webinaire UN-CHK v2" qui crée et pilote une salle sur
la plateforme [webinairev2](https://preprod-webinairev2.unchk.sn) (LiveKit,
modérateur/participants, tableau blanc, sondages, présentations, sous-groupes).

## Différence avec mod_livestream (v1)

Contrairement au plugin `mod_livestream` (livestreamv3), ce plugin **n'émet
jamais de jeton d'accès LiveKit et ne transmet jamais d'identité par email pour
rejoindre une session**. Il ne fait que deux choses :

1. Appelle l'API serveur-à-serveur de webinairev2 (`X-Api-Key`) pour des actions
   purement administratives : créer la salle liée à l'activité, lire son statut,
   lister/supprimer les enregistrements, synchroniser l'inscription au cours et
   l'URL de retour.
2. Renvoie l'utilisateur vers un lien direct `https://.../rooms/{roomId}` sur
   webinairev2, qui gère lui-même l'authentification (SSO Keycloak partagé avec
   Moodle) et l'autorisation (modérateur = créateur de la salle ou rôle global
   promu). Si l'utilisateur est déjà connecté à Keycloak via Moodle, la
   connexion à webinairev2 est silencieuse.

Ce modèle suppose que **Moodle et webinairev2 authentifient via le même realm
Keycloak** (`auth=oidc` côté Moodle). C'est le cas à l'UN-CHK.

## Rôles modérateurs

Comme `mod_bigbluebuttonbn`, chaque activité porte sa propre liste de **rôles
modérateurs** (formulaire de l'activité → *Modération*). Les titulaires d'un de
ces rôles dans le cours démarrent, enregistrent et modèrent la session ; les
autres la rejoignent en participants.

**La liste fait autorité, elle ne s'ajoute pas à la capacité
`mod/webinairev2:moderate`.** Elle peut donc accorder la modération à un rôle
qui ne l'a pas (un rôle « tuteur » dérivé d'étudiant) comme la retirer à un rôle
qui l'a (un enseignant non éditeur qu'on veut simple participant).

Deux exceptions permanentes, non désactivables — sans quoi un enseignant
pourrait se priver du bouton « Démarrer la session » en pleine séance :

- les **administrateurs du site** ;
- quiconque peut **modifier l'activité** (`moodle/course:manageactivities`).

Une activité créée avant l'introduction du réglage garde sa colonne à `NULL`,
que le plugin distingue de la liste vide : elle continue de suivre la capacité
`mod/webinairev2:moderate`, exactement comme avant. La liste vide, elle, est un
choix explicite (« seuls les deux cas ci-dessus modèrent »).

La sélection proposée à la création d'une nouvelle activité vient du réglage de
site *Rôles modérateurs par défaut*.

## Plusieurs plateformes Moodle sur un même backend

Ce plugin peut être installé sur **plusieurs Moodle pointant tous vers le même
webinairev2**. Deux conséquences, réglées dans le code :

**Aucune configuration propre à une plateforme n'existe côté webinairev2.** Ni
liste blanche de domaines, ni variable de build : tout ce qui est spécifique à
un Moodle voyage avec la salle, transmis serveur-à-serveur par le plugin.

**L'identifiant d'activité n'est pas une clé de salle.** `meetingId` est l'id de
la ligne `webinairev2` : il n'est unique qu'au sein d'*une* plateforme, et
l'activité n°1 de chaque Moodle porte la même valeur. `POST /moodle/rooms` crée
donc **toujours une salle neuve**, identifiée par un cuid possédé par le
backend ; `moodleCourseId`/`moodleMeetingId` ne sont conservés que comme
métadonnées de provenance. C'est le plugin qui garantit qu'une activité n'a
qu'une salle, en n'appelant `createRoom` que depuis `webinairev2_add_instance`.

> `mod_livestream` a vécu l'incident correspondant le 27/07/2026 — « TEST
> Integration DISIDEV » rattachée à « Introduction au droit » — et a retiré la
> même logique de réutilisation.

## Retour vers Moodle en fin de séance

En quittant la séance, l'utilisateur revient sur la page de l'activité d'où il
est parti, et non sur l'accueil de webinairev2.

L'URL de retour est transmise **par l'API serveur-à-serveur** (`createRoom`,
puis `syncUser` à chaque affichage de l'activité) et mémorisée sur la salle.
Elle ne transite jamais par la barre d'adresse : un paramètre `?returnUrl=`
serait modifiable par n'importe qui et ferait de webinairev2 le tremplin d'une
redirection vers un site tiers. Le backend n'accepte par ailleurs que du
`http(s)`.

Sa réémission à chaque affichage sert de rattrapage : les salles créées avant
l'introduction du champ se remplissent seules à la première visite, et un
déplacement de l'activité (le cmid change) est suivi sans intervention.

## Enregistrements

La page de l'activité liste les enregistrements prêts de la salle, **paginés
côté serveur** (réglage de site *Enregistrements par page*, 10 par défaut) :
chaque ligne affichée coûte un jeton HMAC signé, inutile d'en émettre pour des
lignes jamais vues.

Mise en page reprise de `mod_livestream` : `Voir | Nom | Date | Durée`, plus une
colonne `Actions` visible des seuls administrateurs du site.

| Action | Qui |
|---|---|
| Lire dans la page (lecteur `<video>` déplié par le bouton « Voir ») | tout utilisateur qui voit l'activité |
| Supprimer | **administrateurs du site uniquement** (`is_siteadmin()`) |

Pas de lien de téléchargement séparé : les contrôles natifs du lecteur `<video>`
l'offrent déjà, le lien étant servi en `Content-Disposition: inline`.

La suppression efface le fichier dans le stockage objet, pas seulement la ligne
en base : c'est irréversible et hors de portée d'une restauration de cours.
`is_siteadmin()` est délibérément préféré à une capacité dédiée — il n'est pas
délégable par attribution d'un rôle. Même arbitrage que `mod_livestream` (V16).

Les liens de lecture sont des URL signées et expirant au bout de 30 minutes,
émises par webinairev2 : aucune clé de stockage ni URL permanente n'est exposée.
Le plugin refuse d'afficher une URL qui ne serait pas en HTTPS sur le domaine
configuré (ou l'un de ses sous-domaines).

## Installation

1. Copier ce dossier dans `<moodle>/mod/webinairev2` (renommer le dossier en
   `webinairev2`, sans le préfixe `plugin`).
2. Administration du site → Notifications → laisser Moodle installer la table
   `webinairev2` (voir `db/install.xml`).
3. Administration du site → Plugins → Modules d'activité → Webinaire UN-CHK v2 :
   - **URL de la plateforme** : `https://preprod-webinairev2.unchk.sn`
   - **Clé API** : valeur de `MOODLE_API_KEY` dans `/var/www/html/webinairev2/.env`
   - **Timeout** : 30s par défaut
   - **Rôles modérateurs par défaut** : `editingteacher`, `teacher`, `manager`
   - **Enregistrements par page** : 10
4. Ajouter l'activité "Webinaire UN-CHK v2" dans un cours — la salle est créée
   automatiquement côté webinairev2 à ce moment (l'enseignant devient
   créateur/modérateur de cette salle, même s'il ne s'est encore jamais connecté
   à webinairev2 : son compte y est provisionné par email et fusionné avec son
   identité Keycloak réelle à sa première connexion).

## Fichiers clés

- `lib.php` — cycle de vie de l'instance **et** les règles d'autorisation :
  `webinairev2_is_moderator()`, `webinairev2_can_delete_recording()`.
- `classes/api.php` — client HTTP vers `/api/moodle/*` (création de salle,
  statut, enregistrements paginés, garde d'URL média, URL de retour).
- `mod_form.php` — formulaire de l'activité, dont la section *Modération*.
- `launch.php` — point de passage journalisant l'événement (démarré/rejoint)
  puis redirection immédiate vers webinairev2, sans jeton.
- `view.php` — page de l'activité (statut en direct, liste paginée des
  enregistrements avec lecture en place et suppression).
- `db/install.xml` / `db/upgrade.php` — table Moodle `webinairev2` (id, course,
  name, intro, roomid, roomname, moderatorroles, timestamps).
- `pix/icon.png` — icône de l'activité, 256×256 avec transparence, dérivée du
  favicon de webinairev2 (`apps/frontend/public/favicon.png`, la marque sans le
  mot « WEBINAIRE », illisible à la taille d'une icône). Pour la régénérer :
  `convert favicon.png -resize 256x256 -strip pix/icon.png`.

## Dépendance de version avec le backend

Deux changements de contrat côté webinairev2 accompagnent ce plugin :

- `GET /api/moodle/rooms/{id}/recordings` renvoie une réponse paginée
  `{ recordings, total, page, perPage }` au lieu d'un tableau nu.
  `classes/api.php` accepte **les deux formes** : mettre à jour le plugin avant
  le backend dégrade la page à « tout sur une seule page », sans erreur.
  L'inverse (backend à jour, plugin ancien) casserait la liste.
- `POST /api/moodle/rooms` ne déduplique plus par `meetingId` et accepte
  `returnUrl`. Un plugin ancien continue de fonctionner, sans retour vers
  Moodle — mais il perd l'idempotence côté serveur, sur laquelle il ne
  s'appuyait de toute façon que via `add_instance`.

Déployer le backend en dernier, ou les deux ensemble.

## Non repris de mod_livestream

- Pas d'enrôlement automatique par email (`/api/moodle/enroll`) : webinairev2
  n'a pas de liste blanche par salle, tout utilisateur avec une session
  Keycloak valide peut rejoindre une fois qu'un modérateur est présent.
- Pas de pages séparées host/viewer : une seule UI de salle côté webinairev2,
  avec des droits modérateur/participant déterminés par le token LiveKit émis.
# plugin-webinairev2
