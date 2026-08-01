<?php
namespace mod_webinairev2\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when a new webinairev2 room is created.
 */
class room_created extends \core\event\base {

    protected function init() {
        $this->data['crud']        = 'c';
        $this->data['edulevel']    = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'webinairev2';
    }

    public static function get_name(): string {
        return get_string('event_room_created', 'mod_webinairev2');
    }

    public function get_description(): string {
        return "The user with id '{$this->userid}' created a new webinairev2 room " .
               "with id '{$this->objectid}'.";
    }

    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/webinairev2/view.php', ['id' => $this->contextinstanceid]);
    }

    public static function get_objectid_mapping(): array {
        return ['db' => 'webinairev2', 'restore' => 'webinairev2'];
    }
}
