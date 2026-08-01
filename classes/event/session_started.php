<?php
namespace mod_webinairev2\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when a moderator launches (or resumes) a webinairev2 session
 * from Moodle. Purement déclaratif côté Moodle : la vraie autorisation/démarrage
 * se fait sur webinairev2 lui-même après le redirect (voir launch.php).
 */
class session_started extends \core\event\base {

    protected function init() {
        $this->data['crud']        = 'u';
        $this->data['edulevel']    = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'webinairev2';
    }

    public static function get_name(): string {
        return get_string('event_session_started', 'mod_webinairev2');
    }

    public function get_description(): string {
        return "The user with id '{$this->userid}' launched the webinairev2 session " .
               "with course module id '{$this->contextinstanceid}'.";
    }

    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/webinairev2/view.php', ['id' => $this->contextinstanceid]);
    }

    public static function get_objectid_mapping(): array {
        return ['db' => 'webinairev2', 'restore' => 'webinairev2'];
    }
}
