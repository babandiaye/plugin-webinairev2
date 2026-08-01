<?php
require_once('../../config.php');
defined('MOODLE_INTERNAL') || die();
require_once('lib.php');
require_once('classes/api.php');

$id       = optional_param('id', 0, PARAM_INT);
$page     = optional_param('page', 0, PARAM_INT); // 0 pour la première page (convention Moodle)
$cm       = get_coursemodule_from_id('webinairev2', $id, 0, false, MUST_EXIST);
$course   = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$instance = $DB->get_record('webinairev2', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/webinairev2:view', $context);

$PAGE->set_url('/mod/webinairev2/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($instance);

// Modération : liste de rôles configurée sur l'activité, avec repli sur la
// capacité pour les activités antérieures — voir lib.php.
$isModerator = webinairev2_is_moderator($context, $instance);
// La suppression, elle, ne suit PAS la modération : elle efface le fichier dans
// le stockage objet, sans retour possible. Administrateurs du site seulement.
$canDelete   = webinairev2_can_delete_recording();
$perpage     = webinairev2_recordings_per_page();
$action      = optional_param('action', '', PARAM_ALPHA);
$deleteError = '';

// Petites icônes SVG inline, aucune dépendance externe (parité mod_livestream V14).
function webinairev2_icon(string $name, int $size = 18): string {
    $paths = [
        'play'     => '<circle cx="12" cy="12" r="10"></circle><polygon points="10 8 16 12 10 16 10 8"></polygon>',
        'eye'      => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>',
        'check'    => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>',
        'clock'    => '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>',
        'video'    => '<polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>',
        'trash'    => '<polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>',
    ];
    if (!isset($paths[$name])) {
        return '';
    }
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" ' .
        'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ' .
        'style="flex-shrink:0;">' . $paths[$name] . '</svg>';
}

// Rate limiting léger sur la suppression d'enregistrement (seule action encore
// state-changing appelée depuis cette page ; rejoindre/démarrer ne sont plus que
// des liens vers webinairev2, qui gère lui-même ses propres abus/limites).
function webinairev2_check_ratelimit(string $actionKey): void {
    global $USER;
    $cache    = cache::make('mod_webinairev2', 'ratelimit');
    $cacheKey = $actionKey . '_' . $USER->id;
    $count    = (int)($cache->get($cacheKey) ?: 0);
    if ($count >= 5) {
        throw new moodle_exception('apierror', 'mod_webinairev2', '',
            'Trop de tentatives. Veuillez patienter une minute.');
    }
    $cache->set($cacheKey, $count + 1);
}

// ── DELETE RECORDING ─────────────────────────────────────────────────────────
if ($action === 'deleterecording' && $canDelete && !empty($instance->roomid)) {
    require_sesskey();
    $recordingId = required_param('recordingid', PARAM_ALPHANUMEXT);
    try {
        webinairev2_check_ratelimit('deleterecording');
        $api = new mod_webinairev2_api();
        $api->deleteRecording($instance->roomid, $recordingId);

        \mod_webinairev2\event\recording_deleted::create([
            'context'  => $context,
            'objectid' => $instance->id,
            'other'    => ['recordingid' => $recordingId],
        ])->trigger();

        // Retour sur la même page de la liste, pas sur la première.
        redirect(new moodle_url('/mod/webinairev2/view.php', ['id' => $cm->id, 'page' => $page]));
    } catch (Exception $e) {
        $deleteError = $e->getMessage();
        debugging('webinairev2 delete error', DEBUG_DEVELOPER);
    }
}

// ── API ──────────────────────────────────────────────────────────────────────
$status         = null;
$recordings     = [];
$recordingcount = 0;
$api            = null;
$apiError       = false;
$apiErrorMsg    = '';

if (!empty($instance->roomid)) {
    $api = new mod_webinairev2_api();

    // Synchronise l'inscription de l'utilisateur courant à ce cours côté
    // webinairev2 (Enrollment) — condition d'accès à la salle depuis que les
    // salles Moodle ne sont plus exemptées de la restriction "enrôlés
    // uniquement". Échec silencieux, dans son propre try/catch : une synchro
    // ratée ne doit jamais empêcher l'affichage du statut/des enregistrements
    // ci-dessous (l'utilisateur pourra être inscrit manuellement en attendant).
    try {
        // Transmet aussi l'URL de CETTE page : c'est là que webinairev2 renverra
        // l'utilisateur en fin de séance. Réémise à chaque affichage, elle
        // rattrape les salles créées avant l'introduction du champ et suit un
        // déplacement de l'activité, sans appel HTTP supplémentaire.
        $api->syncUser($instance->roomid, $USER->email, fullname($USER), $isModerator,
            mod_webinairev2_api::buildReturnUrl((int)$cm->id));
    } catch (Exception $e) {
        debugging('webinairev2 sync error: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }

    try {
        $status = $api->getRoomStatus($instance->roomid);

        $result         = $api->getRecordings($instance->roomid, $page + 1, $perpage);
        $recordings     = $result['recordings'];
        $recordingcount = $result['total'];

        // Page devenue hors bornes (dernier enregistrement d'une page supprimé,
        // lien mis en favori) : on retombe sur la dernière page réelle plutôt
        // que d'afficher une liste vide sous une barre de pagination peuplée.
        if (empty($recordings) && $recordingcount > 0 && $page > 0) {
            $page           = (int)ceil($recordingcount / $perpage) - 1;
            $result         = $api->getRecordings($instance->roomid, $page + 1, $perpage);
            $recordings     = $result['recordings'];
            $recordingcount = $result['total'];
        }
    } catch (Exception $e) {
        $apiError    = true;
        $apiErrorMsg = $e->getMessage();
        debugging('webinairev2 API error', DEBUG_DEVELOPER);
    }
}

// ── OUTPUT ───────────────────────────────────────────────────────────────────
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($instance->name));

if (!empty($instance->intro)) {
    echo $OUTPUT->box(format_module_intro('webinairev2', $instance, $cm->id), 'generalbox', 'intro');
}

if ($deleteError) {
    echo $OUTPUT->notification(get_string('error') . ': ' . s($deleteError), 'error');
}

// ── Carte de statut ──────────────────────────────────────────────────────────
echo html_writer::start_div('', ['style' =>
    'margin:16px 0;padding:24px;background:#f8fafd;border-radius:12px;border:1px solid #e2e8f0;'
]);

if ($apiError) {
    echo $OUTPUT->notification(s($apiErrorMsg), 'warning');
} elseif (!empty($instance->roomid) && $status) {
    $roomStatus = $status['status'] ?? 'SCHEDULED';

    $badges = [
        'LIVE'      => ['bg' => '#dcfce7', 'fg' => '#16a34a', 'icon' => null,
            'title' => get_string('statuslive', 'mod_webinairev2'),
            'subtitle' => get_string('statuslive_desc', 'mod_webinairev2')],
        'ENDED'     => ['bg' => '#e5e7eb', 'fg' => '#4b5563', 'icon' => 'check',
            'title' => get_string('statusended', 'mod_webinairev2'),
            'subtitle' => get_string('statusended_desc', 'mod_webinairev2')],
        'SCHEDULED' => ['bg' => '#dbeafe', 'fg' => '#0065b1', 'icon' => 'clock',
            'title' => get_string('statusscheduled', 'mod_webinairev2'),
            'subtitle' => get_string('statusscheduled_desc', 'mod_webinairev2')],
    ];
    $badge = $badges[$roomStatus] ?? $badges['SCHEDULED'];

    if ($roomStatus === 'LIVE') {
        $badgeInner = '<span style="width:10px;height:10px;border-radius:50%;background:' . $badge['fg'] .
            ';display:inline-block;animation:wv2-blink 1.2s ease-in-out infinite;"></span>';
    } else {
        $badgeInner = webinairev2_icon($badge['icon'], 20);
    }

    echo html_writer::start_div('', ['style' => 'display:flex;align-items:center;gap:20px;flex-wrap:wrap;']);

    echo html_writer::start_div('', ['style' => 'display:flex;align-items:center;gap:14px;flex:1;min-width:220px;']);
    echo html_writer::div($badgeInner, '', ['style' =>
        'width:40px;height:40px;border-radius:50%;background:' . $badge['bg'] . ';color:' . $badge['fg'] . ';' .
        'display:flex;align-items:center;justify-content:center;flex-shrink:0;'
    ]);
    echo html_writer::start_div();
    echo html_writer::div(s($badge['title']), '', ['style' => 'font-weight:700;color:#111827;']);
    echo html_writer::div(s($badge['subtitle']), '', ['style' => 'color:#6b7280;font-size:0.85rem;']);
    echo html_writer::end_div();
    echo html_writer::end_div();

    $launchUrl = new moodle_url('/mod/webinairev2/launch.php', ['id' => $cm->id, 'sesskey' => sesskey()]);
    $hasAction = $isModerator || $roomStatus === 'LIVE';
    if ($hasAction) {
        echo html_writer::div('', '', ['style' => 'width:1px;align-self:stretch;background:#e2e8f0;']);
    }

    echo html_writer::start_div('', ['style' => 'display:flex;flex-direction:column;align-items:flex-start;gap:4px;']);

    // Modérateur : toujours le droit de (re)lancer sa salle, quel que soit son
    // statut — c'est webinairev2/RoomsService.join() qui gère le redémarrage.
    if ($isModerator) {
        echo html_writer::link($launchUrl,
            webinairev2_icon('play', 16) . ' ' . get_string('startsession', 'mod_webinairev2'),
            ['style' => 'display:inline-flex;align-items:center;gap:8px;padding:10px 24px;background:#0065b1;' .
                'color:white;border-radius:8px;text-decoration:none;font-weight:600;']
        );
        echo html_writer::div(
            $roomStatus === 'LIVE'
                ? get_string('startsession_relaunch', 'mod_webinairev2')
                : get_string('startsession_desc', 'mod_webinairev2'),
            '', ['style' => 'color:#9ca3af;font-size:0.8rem;']
        );
    } elseif ($roomStatus === 'LIVE') {
        echo html_writer::link($launchUrl,
            webinairev2_icon('eye', 16) . ' ' . get_string('joinsession', 'mod_webinairev2'),
            ['style' => 'display:inline-flex;align-items:center;gap:8px;padding:10px 24px;background:#fff;' .
                'color:#0065b1;border:1.5px solid #0065b1;border-radius:8px;text-decoration:none;font-weight:600;']
        );
        echo html_writer::div(get_string('joinsession_desc', 'mod_webinairev2'),
            '', ['style' => 'color:#9ca3af;font-size:0.8rem;']
        );
    } else {
        echo html_writer::div(get_string('notliveyet', 'mod_webinairev2'),
            '', ['style' => 'color:#9ca3af;font-size:0.85rem;']
        );
    }

    echo html_writer::end_div();
    echo html_writer::end_div();
} else {
    echo $OUTPUT->notification(get_string('noroom', 'mod_webinairev2'), 'warning');
}

echo html_writer::end_div();

// ── Enregistrements ──────────────────────────────────────────────────────────
echo html_writer::start_div('', ['style' => 'display:flex;align-items:center;gap:10px;margin:24px 0 12px;']);
echo html_writer::div(webinairev2_icon('video', 20), '', ['style' => 'color:#0065b1;']);
echo html_writer::tag('h3', s(get_string('recordings', 'mod_webinairev2')), ['style' => 'margin:0;']);
if ($recordingcount > 0) {
    echo html_writer::div(get_string('recordingcount', 'mod_webinairev2', $recordingcount),
        '', ['style' => 'color:#9ca3af;font-size:0.85rem;']
    );
}
echo html_writer::end_div();

if (empty($recordings)) {
    echo html_writer::div(get_string('norecordings', 'mod_webinairev2'),
        '', ['style' => 'color:#9ca3af;padding:8px 0;font-size:0.9rem;']
    );
} else {
    $playerData = [];

    // Mise en page reprise telle quelle de mod_livestream (V14/V16) : mêmes
    // colonnes, mêmes boutons-icônes, même comportement de lecture en place.
    $table        = new html_table();
    $table->head  = [
        get_string('view'),
        get_string('recordingname', 'mod_webinairev2'),
        get_string('date'),
        get_string('duration', 'mod_webinairev2'),
    ];
    $table->align = ['left', 'left', 'left', 'left'];
    // La colonne « Actions » ne contient que la corbeille : inutile de la
    // laisser, vide, à ceux qui n'ont pas le droit de supprimer.
    if ($canDelete) {
        $table->head[]  = get_string('actions');
        $table->align[] = 'center';
    }

    foreach ($recordings as $rec) {
        $safeId   = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$rec['id']);
        $playerId = 'wv2-player-' . $safeId;

        $playUrl = (string)($rec['playUrl'] ?? '');
        $canPlay = $playUrl !== '' && $api->isSafeMediaUrl($playUrl);

        if ($canPlay) {
            $playerData[$playerId] = $playUrl;
            $viewBtn = html_writer::tag('button', webinairev2_icon('eye', 16), [
                'data-player' => $playerId,
                'class'       => 'wv2-play-btn',
                'title'       => get_string('viewrecording', 'mod_webinairev2'),
                'style'       => 'display:flex;align-items:center;justify-content:center;width:34px;height:34px;' .
                    'color:#0065b1;background:#eaf3fb;border:none;border-radius:8px;cursor:pointer;',
            ]);
        } else {
            $viewBtn = html_writer::span('—', '', ['title' => get_string('unavailablemedia', 'mod_webinairev2')]);
        }

        $playerDiv = html_writer::div('', '', [
            'id'    => $playerId,
            'style' => 'display:none;margin-top:10px;',
        ]);

        // Aucun lien de téléchargement séparé : les contrôles natifs du lecteur
        // <video> déplié par le bouton « Voir » l'offrent déjà, et l'ajouter
        // ici alourdissait la ligne pour rien. Mise en page identique à
        // mod_livestream.
        $duration = !empty($rec['duration'])
            ? round((int)$rec['duration'] / 60) . ' min'
            : '—';
        $date = userdate(strtotime((string)$rec['date']), get_string('strftimedatefullshort', 'langconfig'));

        // Petite icône vidéo devant le nom du fichier (mod_livestream V14).
        $namecell = html_writer::span(webinairev2_icon('video', 15), '', ['style' => 'color:#9ca3af;margin-right:8px;'])
            . format_string((string)$rec['name'])
            . $playerDiv;

        $row = [$viewBtn, $namecell, $date, $duration];

        if ($canDelete) {
            $deleteUrl = new moodle_url('/mod/webinairev2/view.php', [
                'id'          => $cm->id,
                'page'        => $page,
                'action'      => 'deleterecording',
                'recordingid' => $rec['id'],
                'sesskey'     => sesskey(),
            ]);
            // Bouton-icône corbeille, même habillage que mod_livestream (V14).
            $row[] = html_writer::link($deleteUrl, webinairev2_icon('trash', 15), [
                'title'   => get_string('deleterecording', 'mod_webinairev2'),
                'style'   => 'display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;' .
                    'color:#e53e3e;background:#fff;border:1.5px solid #fecaca;border-radius:8px;text-decoration:none;',
                'onclick' => 'return confirm(' . json_encode(
                        get_string('confirmdeleterecording', 'mod_webinairev2'),
                        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                    ) . ')',
            ]);
        }

        $table->data[] = $row;
    }

    echo html_writer::table($table);

    echo $OUTPUT->paging_bar(
        $recordingcount,
        $page,
        $perpage,
        new moodle_url('/mod/webinairev2/view.php', ['id' => $cm->id])
    );

    if (!empty($playerData)) {
        $jsonData = json_encode($playerData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        echo html_writer::tag('script', "
(function() {
    var players = " . $jsonData . ";
    document.querySelectorAll('.wv2-play-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id  = btn.getAttribute('data-player');
            var url = players[id];
            if (!url) return;
            var div = document.getElementById(id);
            if (!div) return;
            if (div.style.display === 'none' || div.style.display === '') {
                var video  = document.createElement('video');
                video.controls = true;
                video.autoplay = true;
                video.style.cssText = 'width:100%;max-height:420px;border-radius:8px;background:#000;display:block;margin-top:8px;';
                var source = document.createElement('source');
                source.setAttribute('src', url);
                source.setAttribute('type', 'video/mp4');
                video.appendChild(source);
                div.innerHTML = '';
                div.appendChild(video);
                div.style.display = 'block';
            } else {
                div.innerHTML = '';
                div.style.display = 'none';
            }
        });
    });
})();
");
    }
}

echo html_writer::tag('style', "
@keyframes wv2-blink { 0%,100%{opacity:1} 50%{opacity:0.3} }
.wv2-play-btn:hover { background:#d7e9f7 !important; }
");

echo $OUTPUT->footer();
