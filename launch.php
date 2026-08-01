<?php
require_once('../../config.php');
defined('MOODLE_INTERNAL') || die();
require_once('lib.php');
require_once('classes/api.php');

// Point de passage unique avant d'envoyer l'utilisateur vers webinairev2 — sert
// uniquement à journaliser l'événement (V09, parité avec mod_livestream) et à
// vérifier les capacités Moodle. Aucune décision d'autorisation n'est prise ici :
// webinairev2 authentifie et autorise lui-même via son propre SSO Keycloak
// (partagé avec Moodle) une fois le redirect suivi. Pas de jeton transmis.
$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('webinairev2', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$instance = $DB->get_record('webinairev2', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
require_sesskey();

$context = context_module::instance($cm->id);
require_capability('mod/webinairev2:view', $context);

if (empty($instance->roomid)) {
    redirect(new moodle_url('/mod/webinairev2/view.php', ['id' => $cm->id]),
        get_string('noroom', 'mod_webinairev2'), null, \core\output\notification::NOTIFY_ERROR);
}

// Même règle qu'en page d'activité (rôles configurés sur l'instance, repli sur
// la capacité) — sans quoi l'événement journalisé contredirait ce que
// l'utilisateur a réellement vu et pu faire.
$isModerator = webinairev2_is_moderator($context, $instance);

if ($isModerator) {
    \mod_webinairev2\event\session_started::create(['context' => $context, 'objectid' => $instance->id])->trigger();
} else {
    \mod_webinairev2\event\session_joined::create(['context' => $context, 'objectid' => $instance->id])->trigger();
}

// L'URL de retour n'est PAS passée ici : elle a déjà été transmise
// serveur-à-serveur (syncUser, à chaque affichage de view.php) et mémorisée sur
// la salle côté webinairev2, qui y renverra l'utilisateur en fin de séance.
$api = new mod_webinairev2_api();
redirect($api->buildJoinUrl($instance->roomid));
