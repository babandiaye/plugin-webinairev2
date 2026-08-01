<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Normalise la valeur du champ « rôles modérateurs » telle que soumise par le
 * formulaire (tableau de shortnames) vers sa représentation en base (CSV).
 *
 * Toujours une chaîne après passage ici, jamais NULL : NULL est réservé aux
 * activités créées avant l'introduction du réglage (voir db/upgrade.php).
 */
function webinairev2_pack_moderator_roles($value): string {
    if (is_array($value)) {
        $clean = [];
        foreach ($value as $shortname) {
            $shortname = clean_param((string)$shortname, PARAM_ALPHANUMEXT);
            if ($shortname !== '' && !in_array($shortname, $clean, true)) {
                $clean[] = $shortname;
            }
        }
        return implode(',', $clean);
    }
    return (string)($value ?? '');
}

/**
 * Rôles (shortnames) désignés modérateurs pour cette activité.
 *
 * @return string[]|null null quand l'activité n'a jamais été configurée
 *                       (colonne NULL) — l'appelant retombe alors sur la
 *                       capacité mod/webinairev2:moderate.
 */
function webinairev2_moderator_roles(stdClass $instance): ?array {
    if (!property_exists($instance, 'moderatorroles') || $instance->moderatorroles === null) {
        return null;
    }
    $raw = trim((string)$instance->moderatorroles);
    return $raw === '' ? [] : array_map('trim', explode(',', $raw));
}

/**
 * Rôles modérateurs par défaut, définis au niveau du site
 * (Administration du site → Plugins → Modules d'activité → Webinaire v2).
 *
 * @return string[]
 */
function webinairev2_default_moderator_roles(): array {
    $raw = trim((string)get_config('mod_webinairev2', 'defaultmoderatorroles'));
    if ($raw === '') {
        // Miroir des archetypes de mod/webinairev2:moderate dans db/access.php :
        // un site qui n'a jamais touché au réglage garde le comportement
        // historique du plugin.
        return ['editingteacher', 'teacher', 'manager'];
    }
    return array_map('trim', explode(',', $raw));
}

/**
 * Rôles proposables comme modérateurs, pour les listes de sélection.
 *
 * @return array shortname => nom localisé du rôle
 */
function webinairev2_role_options(context $context): array {
    // Restreint aux rôles réellement attribuables dans un cours ou une activité :
    // proposer « Utilisateur authentifié » ou « Invité » n'aurait aucun sens ici.
    $assignable = array_merge(
        array_values(get_roles_for_contextlevels(CONTEXT_COURSE)),
        array_values(get_roles_for_contextlevels(CONTEXT_MODULE))
    );

    $options = [];
    foreach (role_get_names($context, ROLENAME_ALIAS) as $role) {
        if (!empty($assignable) && !in_array($role->id, $assignable)) {
            continue;
        }
        $options[$role->shortname] = $role->localname;
    }
    return $options;
}

/**
 * L'utilisateur courant modère-t-il cette session ?
 *
 * Sémantique reprise de mod_bigbluebuttonbn : la liste de rôles configurée sur
 * l'activité FAIT AUTORITÉ, elle ne s'ajoute pas à la capacité. Elle peut donc
 * ACCORDER la modération à un rôle qui n'a pas mod/webinairev2:moderate (un
 * rôle « tuteur » dérivé d'étudiant, par exemple) comme la RETIRER à un rôle
 * qui l'a (un enseignant non éditeur qu'on veut simple participant).
 *
 * Deux garde-fous :
 *  - administrateur du site : toujours modérateur ;
 *  - quiconque peut modifier l'activité : toujours modérateur, sans quoi un
 *    enseignant qui retire son propre rôle de la liste se priverait du bouton
 *    « Démarrer la session » en pleine séance, sans recours immédiat.
 */
function webinairev2_is_moderator(context $context, stdClass $instance): bool {
    global $USER;

    if (is_siteadmin()) {
        return true;
    }
    if (has_capability('moodle/course:manageactivities', $context)) {
        return true;
    }

    $shortnames = webinairev2_moderator_roles($instance);
    if ($shortnames === null) {
        return has_capability('mod/webinairev2:moderate', $context);
    }
    if (empty($shortnames)) {
        return false;
    }

    // Résolution shortname → id avant comparaison : get_user_roles() renvoie des
    // lignes de role_assignments, dont seul roleid est garanti.
    $wanted = [];
    foreach (role_get_names($context, ROLENAME_ALIAS) as $role) {
        if (in_array($role->shortname, $shortnames, true)) {
            $wanted[(int)$role->id] = true;
        }
    }
    if (empty($wanted)) {
        // Rôles configurés mais aucun ne correspond plus à un rôle existant
        // (rôle supprimé, ou activité restaurée depuis un autre site).
        return false;
    }

    // true = remonte aux contextes parents, pour prendre en compte un rôle
    // attribué au niveau du cours, de la catégorie ou du système — et pas
    // seulement une attribution locale à ce module.
    foreach (get_user_roles($context, $USER->id, true) as $assignment) {
        if (isset($wanted[(int)$assignment->roleid])) {
            return true;
        }
    }
    return false;
}

/**
 * La suppression d'un enregistrement est réservée aux ADMINISTRATEURS DU SITE.
 *
 * Elle efface le fichier dans le stockage objet, pas seulement la ligne en
 * base : c'est irréversible et hors de portée d'une restauration de cours.
 * is_siteadmin() est délibérément préféré à une capacité dédiée — il n'est pas
 * délégable par attribution d'un rôle. Même arbitrage que mod_livestream (V16).
 */
function webinairev2_can_delete_recording(): bool {
    return is_siteadmin();
}

/** Nombre d'enregistrements affichés par page (réglage de site). */
function webinairev2_recordings_per_page(): int {
    $perpage = (int)get_config('mod_webinairev2', 'recordingsperpage');
    if ($perpage < 1 || $perpage > 100) {
        $perpage = 10;
    }
    return $perpage;
}

function webinairev2_add_instance(stdClass $data, mod_webinairev2_mod_form $mform = null): int {
    global $DB, $USER;

    $data->timecreated    = time();
    $data->timemodified   = time();
    $data->moderatorroles = webinairev2_pack_moderator_roles($data->moderatorroles ?? []);

    $transaction = $DB->start_delegated_transaction();
    try {
        $id = $DB->insert_record('webinairev2', $data);

        // SEUL appelant de createRoom, et il ne s'exécute qu'à la création de
        // l'activité : c'est ce qui garantit qu'une activité n'a qu'une salle.
        // Le backend, lui, ne peut plus dédupliquer — deux plateformes Moodle
        // sur un même webinairev2 produisent les mêmes identifiants d'activité
        // (voir le commentaire de mod_webinairev2_api::createRoom).
        // $data->coursemodule : cmid attribué avant l'appel à add_instance, donc
        // disponible ici pour construire l'URL de retour définitive.
        $returnUrl = !empty($data->coursemodule)
            ? mod_webinairev2_api::buildReturnUrl((int)$data->coursemodule)
            : '';

        $api    = new mod_webinairev2_api();
        $result = $api->createRoom(
            (string)$data->course,
            (string)$id,
            $data->name,
            $USER->email,
            fullname($USER),
            $data->intro ?? '',
            $returnUrl
        );

        $DB->set_field('webinairev2', 'roomid',   $result['roomId'],   ['id' => $id]);
        $DB->set_field('webinairev2', 'roomname', $result['roomName'], ['id' => $id]);

        // Contexte du cours (le contexte du module n'existe pas encore à ce stade).
        $coursecontext = context_course::instance($data->course);
        \mod_webinairev2\event\room_created::create([
            'context'  => $coursecontext,
            'objectid' => $id,
        ])->trigger();

        $transaction->allow_commit();
        return $id;

    } catch (Exception $e) {
        $transaction->rollback($e);
        throw $e;
    }
}

function webinairev2_update_instance(stdClass $data): bool {
    global $DB;
    $data->timemodified   = time();
    $data->id             = $data->instance;
    $data->moderatorroles = webinairev2_pack_moderator_roles($data->moderatorroles ?? []);
    return $DB->update_record('webinairev2', $data);
}

function webinairev2_delete_instance(int $id): bool {
    global $DB;
    $instance = $DB->get_record('webinairev2', ['id' => $id]);
    if (!$instance) {
        return false;
    }
    // La salle et ses enregistrements restent gérés côté webinairev2 (pas de
    // suppression en cascade depuis Moodle) : un enseignant peut retirer
    // l'activité du cours sans perdre l'historique des enregistrements.
    $DB->delete_records('webinairev2', ['id' => $id]);
    return true;
}

function webinairev2_supports(string $feature): ?bool {
    switch ($feature) {
        case FEATURE_MOD_INTRO:        return true;
        case FEATURE_BACKUP_MOODLE2:   return true;
        case FEATURE_SHOW_DESCRIPTION: return true;
        default:                       return null;
    }
}
