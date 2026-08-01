<?php
defined('MOODLE_INTERNAL') || die();

$string['modulename']              = 'Webinaire UN-CHK v2';
$string['modulenameplural']        = 'Webinaires UN-CHK v2';
$string['modulename_help']         = 'Le module Webinaire v2 permet de créer des sessions de visioconférence intégrées à Moodle via la plateforme webinairev2 (modérateur/participants, tableau blanc, sondages, présentations, sous-groupes).';
$string['pluginname']              = 'Webinaire UN-CHK v2';
$string['pluginadministration']    = 'Administration Webinaire v2';
$string['webinairev2:addinstance'] = 'Ajouter un webinaire v2';
$string['webinairev2:view']        = 'Voir un webinaire v2';
$string['webinairev2:moderate']    = 'Modérer un webinaire v2';
$string['apiurl']                  = 'URL de la plateforme webinairev2';
$string['apiurl_desc']             = 'URL complète de votre instance webinairev2 (ex: https://preprod-webinairev2.unchk.sn) — sert à la fois pour les appels API et pour construire les liens de session.';
$string['apikey']                  = 'Clé API';
$string['apikey_desc']             = 'Clé secrète partagée entre Moodle et webinairev2 (MOODLE_API_KEY dans le .env de webinairev2)';
$string['apitimeout']              = 'Timeout (secondes)';
$string['apitimeout_desc']         = 'Délai maximum pour les appels API vers webinairev2';
$string['sessionname']             = 'Nom de la session';

// Modération
$string['moderationheader']        = 'Modération';
$string['moderatorroles']          = 'Rôles modérateurs';
$string['moderatorroles_help']     = 'Les participants qui possèdent l\'un de ces rôles dans le cours rejoignent la session en tant que modérateurs : ils peuvent la démarrer, l\'enregistrer, couper les micros, gérer le tableau blanc et les sous-groupes.

Cette liste fait autorité : elle peut accorder la modération à un rôle qui ne l\'a pas par défaut (un rôle « tuteur » dérivé d\'étudiant, par exemple) comme la retirer à un rôle qui l\'a.

Deux exceptions permanentes, non désactivables : les administrateurs du site, et les utilisateurs qui peuvent modifier cette activité — sans quoi un enseignant pourrait s\'exclure lui-même du bouton « Démarrer la session ».';
$string['defaultmoderatorroles']      = 'Rôles modérateurs par défaut';
$string['defaultmoderatorroles_desc'] = 'Sélection proposée par défaut dans le formulaire à la création d\'une activité Webinaire v2. Ne modifie aucune activité existante : chacune conserve la liste enregistrée lors de sa configuration.';
$string['recordingsperpage']          = 'Enregistrements par page';
$string['recordingsperpage_desc']     = 'Nombre d\'enregistrements affichés par page dans la liste de l\'activité (entre 1 et 100, 10 par défaut).';

// Statut de la session
$string['statuslive']              = 'En direct';
$string['statuslive_desc']         = 'Une session est en cours.';
$string['statusended']             = 'Session terminée';
$string['statusended_desc']        = 'Cette session est terminée.';
$string['statusscheduled']         = 'Session planifiée';
$string['statusscheduled_desc']    = 'Aucune session en cours pour le moment.';
$string['startsession']            = 'Démarrer la session';
$string['startsession_desc']       = 'Lancez une nouvelle session de webinaire.';
$string['startsession_relaunch']   = 'Relancez si nécessaire.';
$string['joinsession']             = 'Rejoindre la session';
$string['joinsession_desc']        = 'Rejoignez la session en cours.';
$string['notliveyet']              = 'La session n\'est pas encore en direct.';

// Enregistrements
$string['recordings']              = 'Enregistrements';
$string['recordingname']           = 'Nom de l\'enregistrement';
$string['recordingcount']          = '{$a} enregistrement(s)';
$string['norecordings']            = 'Aucun enregistrement disponible.';
$string['duration']                = 'Durée';
$string['size']                    = 'Taille';
$string['viewrecording']           = 'Voir l\'enregistrement';
$string['downloadrecording']       = 'Télécharger';
$string['deleterecording']         = 'Supprimer l\'enregistrement';
$string['confirmdeleterecording']  = 'Supprimer définitivement cet enregistrement ? Le fichier vidéo sera effacé du stockage, cette action est irréversible.';
$string['unavailablemedia']        = 'Lien de lecture indisponible : vérifiez l\'URL de la plateforme dans les réglages du plugin.';

$string['noroom']                  = 'Aucune salle associée à cette activité.';
$string['noinstances']             = 'Aucun webinaire v2 dans ce cours.';
$string['apierror']                = 'Erreur de connexion à la plateforme webinairev2. Vérifiez la configuration.';
$string['apinotconfigured']        = "L'URL et la clé API webinairev2 ne sont pas configurées (Administration du site > Plugins > Activités > Webinaire UN-CHK v2).";
$string['invalidapiurl']           = "L'URL de la plateforme webinairev2 configurée n'est pas valide.";
$string['invalidparameter']        = 'Paramètre invalide : {$a}';
$string['jsonencodeerror']         = "Erreur d'encodage de la requête.";

$string['event_session_joined']    = 'Session webinairev2 rejointe';
$string['event_session_started']   = 'Session webinairev2 démarrée';
$string['event_recording_deleted'] = 'Enregistrement webinairev2 supprimé';
$string['event_room_created']      = 'Salle webinairev2 créée';
