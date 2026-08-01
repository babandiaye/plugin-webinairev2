<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Mises à niveau du schéma de mod_webinairev2.
 *
 * @param int $oldversion version installée avant cette mise à niveau
 * @return bool
 */
function xmldb_webinairev2_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // Colonne moderatorroles : shortnames des rôles du cours dont les titulaires
    // modèrent la session, séparés par des virgules.
    // Délibérément NULLable et SANS valeur par défaut : les activités déjà
    // créées restent à NULL, ce que webinairev2_moderator_roles() distingue de
    // la chaîne vide. NULL = « jamais configuré » → repli sur la capacité
    // mod/webinairev2:moderate, soit exactement le comportement d'avant cette
    // version. Chaîne vide = « configuré, aucun rôle » → seuls les
    // administrateurs du site et ceux qui peuvent modifier l'activité modèrent.
    if ($oldversion < 2026080100) {
        $table = new xmldb_table('webinairev2');
        $field = new xmldb_field('moderatorroles', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'roomname');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026080100, 'webinairev2');
    }

    return true;
}
