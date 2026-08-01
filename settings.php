<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/mod/webinairev2/lib.php');

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_configtext(
        'mod_webinairev2/apiurl',
        get_string('apiurl', 'mod_webinairev2'),
        get_string('apiurl_desc', 'mod_webinairev2'),
        'https://preprod-webinairev2.unchk.sn',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'mod_webinairev2/apikey',
        get_string('apikey', 'mod_webinairev2'),
        get_string('apikey_desc', 'mod_webinairev2'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'mod_webinairev2/apitimeout',
        get_string('apitimeout', 'mod_webinairev2'),
        get_string('apitimeout_desc', 'mod_webinairev2'),
        '30',
        PARAM_INT
    ));

    $settings->add(new admin_setting_heading(
        'mod_webinairev2/sessionheading',
        get_string('moderationheader', 'mod_webinairev2'),
        ''
    ));

    // Présélection du champ « Rôles modérateurs » à la création d'une activité.
    // Ne modifie JAMAIS les activités existantes : chacune porte sa propre
    // valeur en base (voir lib.php, webinairev2_moderator_roles).
    // Rôles listés au niveau système, faute de cours de référence ici — ce sont
    // les shortnames qui sont stockés, identiques dans tous les contextes.
    $settings->add(new admin_setting_configmultiselect(
        'mod_webinairev2/defaultmoderatorroles',
        get_string('defaultmoderatorroles', 'mod_webinairev2'),
        get_string('defaultmoderatorroles_desc', 'mod_webinairev2'),
        ['editingteacher', 'teacher', 'manager'],
        webinairev2_role_options(context_system::instance())
    ));

    $settings->add(new admin_setting_configtext(
        'mod_webinairev2/recordingsperpage',
        get_string('recordingsperpage', 'mod_webinairev2'),
        get_string('recordingsperpage_desc', 'mod_webinairev2'),
        '10',
        PARAM_INT
    ));
}
