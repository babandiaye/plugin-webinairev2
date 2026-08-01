<?php
defined('MOODLE_INTERNAL') || die();

$string['modulename']              = 'UN-CHK Webinar v2';
$string['modulenameplural']        = 'UN-CHK Webinars v2';
$string['modulename_help']         = 'The Webinar v2 module lets you create video-conference sessions embedded in Moodle via the webinairev2 platform (moderator/participants, whiteboard, polls, presentations, breakout rooms).';
$string['pluginname']              = 'UN-CHK Webinar v2';
$string['pluginadministration']    = 'Webinar v2 administration';
$string['webinairev2:addinstance'] = 'Add a webinar v2';
$string['webinairev2:view']        = 'View a webinar v2';
$string['webinairev2:moderate']    = 'Moderate a webinar v2';
$string['apiurl']                  = 'webinairev2 platform URL';
$string['apiurl_desc']             = 'Full URL of your webinairev2 instance (e.g. https://preprod-webinairev2.unchk.sn) — used both for API calls and to build session links.';
$string['apikey']                  = 'API key';
$string['apikey_desc']             = 'Secret key shared between Moodle and webinairev2 (MOODLE_API_KEY in webinairev2 .env)';
$string['apitimeout']              = 'Timeout (seconds)';
$string['apitimeout_desc']         = 'Maximum delay for API calls to webinairev2';
$string['sessionname']             = 'Session name';

// Moderation
$string['moderationheader']        = 'Moderation';
$string['moderatorroles']          = 'Moderator roles';
$string['moderatorroles_help']     = 'Participants holding any of these roles in the course join the session as moderators: they can start it, record it, mute microphones, manage the whiteboard and the breakout rooms.

This list is authoritative: it can grant moderation to a role that does not have it by default (a student-derived "tutor" role, for instance) as well as take it away from a role that does.

Two permanent exceptions that cannot be disabled: site administrators, and users who can edit this activity — otherwise a teacher could lock themselves out of the "Start session" button.';
$string['defaultmoderatorroles']      = 'Default moderator roles';
$string['defaultmoderatorroles_desc'] = 'Selection pre-filled in the form when a Webinar v2 activity is created. Does not change any existing activity: each one keeps the list saved when it was configured.';
$string['recordingsperpage']          = 'Recordings per page';
$string['recordingsperpage_desc']     = 'Number of recordings shown per page in the activity list (between 1 and 100, 10 by default).';

// Session status
$string['statuslive']              = 'Live';
$string['statuslive_desc']         = 'A session is in progress.';
$string['statusended']             = 'Session ended';
$string['statusended_desc']        = 'This session has ended.';
$string['statusscheduled']         = 'Session scheduled';
$string['statusscheduled_desc']    = 'No session in progress right now.';
$string['startsession']            = 'Start session';
$string['startsession_desc']       = 'Start a new webinar session.';
$string['startsession_relaunch']   = 'Restart it if needed.';
$string['joinsession']             = 'Join session';
$string['joinsession_desc']        = 'Join the session in progress.';
$string['notliveyet']              = 'The session is not live yet.';

// Recordings
$string['recordings']              = 'Recordings';
$string['recordingname']           = 'Recording name';
$string['recordingcount']          = '{$a} recording(s)';
$string['norecordings']            = 'No recording available.';
$string['duration']                = 'Duration';
$string['size']                    = 'Size';
$string['viewrecording']           = 'View recording';
$string['downloadrecording']       = 'Download';
$string['deleterecording']         = 'Delete recording';
$string['confirmdeleterecording']  = 'Permanently delete this recording? The video file will be erased from storage; this cannot be undone.';
$string['unavailablemedia']        = 'Playback link unavailable: check the platform URL in the plugin settings.';

$string['noroom']                  = 'No room associated with this activity.';
$string['noinstances']             = 'No webinar v2 in this course.';
$string['apierror']                = 'Connection error to the webinairev2 platform. Check your configuration.';
$string['apinotconfigured']        = 'The webinairev2 URL and API key are not configured (Site administration > Plugins > Activity modules > UN-CHK Webinar v2).';
$string['invalidapiurl']           = 'The configured webinairev2 platform URL is not valid.';
$string['invalidparameter']        = 'Invalid parameter: {$a}';
$string['jsonencodeerror']         = 'Request encoding error.';

$string['event_session_joined']    = 'webinairev2 session joined';
$string['event_session_started']   = 'webinairev2 session started';
$string['event_recording_deleted'] = 'webinairev2 recording deleted';
$string['event_room_created']      = 'webinairev2 room created';
