<?php
namespace mod_webinairev2\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when a participant follows the link to a webinairev2 session.
 */
class session_joined extends \core\event\base {

    protected function init() {
        $this->data['crud']        = 'r';
        $this->data['edulevel']    = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'webinairev2';
    }

    public static function get_name(): string {
        return get_string('event_session_joined', 'mod_webinairev2');
    }

    public function get_description(): string {
        return "The user with id '{$this->userid}' followed the link to the webinairev2 session " .
               "with course module id '{$this->contextinstanceid}'.";
    }

    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/webinairev2/view.php', ['id' => $this->contextinstanceid]);
    }

    public static function get_objectid_mapping(): array {
        return ['db' => 'webinairev2', 'restore' => 'webinairev2'];
    }
}
