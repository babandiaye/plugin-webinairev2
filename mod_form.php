<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/webinairev2/lib.php');

class mod_webinairev2_mod_form extends moodleform_mod {

    public function definition(): void {
        global $COURSE;

        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('sessionname', 'mod_webinairev2'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements();

        // ── Modération ───────────────────────────────────────────────────────
        // Contexte du COURS et non du module : pour une activité en cours de
        // création, le contexte de module n'existe pas encore, et les rôles à
        // proposer sont de toute façon ceux attribuables dans le cours.
        $mform->addElement('header', 'webinairev2moderation', get_string('moderationheader', 'mod_webinairev2'));
        $mform->setExpanded('webinairev2moderation');

        $mform->addElement(
            'autocomplete',
            'moderatorroles',
            get_string('moderatorroles', 'mod_webinairev2'),
            webinairev2_role_options(context_course::instance($COURSE->id)),
            ['multiple' => true]
        );
        $mform->addHelpButton('moderatorroles', 'moderatorroles', 'mod_webinairev2');
        $mform->setDefault('moderatorroles', webinairev2_default_moderator_roles());

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * La colonne moderatorroles est stockée en CSV ; l'élément autocomplete
     * attend un tableau.
     *
     * NULL (activité créée avant l'introduction du réglage) est distinct de la
     * chaîne vide : on présélectionne alors les rôles par défaut du site, qui
     * reproduisent les archetypes de mod/webinairev2:moderate — enregistrer le
     * formulaire sans y toucher ne change donc pas qui modère.
     */
    public function data_preprocessing(&$default_values): void {
        if (!array_key_exists('moderatorroles', $default_values)) {
            return;
        }
        if ($default_values['moderatorroles'] === null) {
            $default_values['moderatorroles'] = webinairev2_default_moderator_roles();
            return;
        }
        $raw = trim((string)$default_values['moderatorroles']);
        $default_values['moderatorroles'] = $raw === '' ? [] : explode(',', $raw);
    }
}
