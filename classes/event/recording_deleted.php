<?php
namespace mod_webinairev2\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when a moderator deletes a webinairev2 recording from Moodle.
 */
class recording_deleted extends \core\event\base {

    protected function init() {
        $this->data['crud']        = 'd';
        $this->data['edulevel']    = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'webinairev2';
    }

    public static function get_name(): string {
        return get_string('event_recording_deleted', 'mod_webinairev2');
    }

    public function get_description(): string {
        $recid = $this->other['recordingid'] ?? '?';
        return "The user with id '{$this->userid}' deleted recording '{$recid}' " .
               "from the webinairev2 session with course module id '{$this->contextinstanceid}'.";
    }

    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/webinairev2/view.php', ['id' => $this->contextinstanceid]);
    }

    public static function get_objectid_mapping(): array {
        return ['db' => 'webinairev2', 'restore' => 'webinairev2'];
    }
}
