<?php
defined('MOODLE_INTERNAL') || die();

// Client de l'API serveur-à-serveur webinairev2 (/api/moodle/*), authentifiée par
// clé statique (X-Api-Key) — jamais de session ni de jeton LiveKit émis ici.
// Contrairement à mod_livestream (v1), ce plugin ne fait QUE des opérations
// administratives (créer/retrouver la salle, lire son statut, lister/supprimer
// les enregistrements, synchroniser l'inscription au cours) : rejoindre ou
// démarrer la session se fait par un simple lien vers webinairev2, qui gère sa
// propre authentification Keycloak (SSO partagé avec Moodle) — voir view.php.
// syncUser() transmet l'email/nom de l'utilisateur courant à chaque affichage
// de l'activité, pour que webinairev2 sache qui est inscrit à quel cours Moodle
// (condition d'accès à la salle depuis que celle-ci n'est plus exemptée de la
// restriction "enrôlés uniquement", voir EnrollmentsService côté webinairev2).
class mod_webinairev2_api {
    private string $baseUrl;
    private string $apiKey;
    private int    $timeout;

    public function __construct() {
        $this->baseUrl = rtrim(get_config('mod_webinairev2', 'apiurl'), '/');
        $this->apiKey  = get_config('mod_webinairev2', 'apikey');
        $this->timeout = (int)(get_config('mod_webinairev2', 'apitimeout') ?: 30);

        if (empty($this->baseUrl) || empty($this->apiKey)) {
            throw new moodle_exception('apinotconfigured', 'mod_webinairev2');
        }
        if (!filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
            throw new moodle_exception('invalidapiurl', 'mod_webinairev2');
        }
    }

    private function validateId(string $id, string $param = 'id'): string {
        if (empty($id) || !preg_match('/^[a-zA-Z0-9_-]+$/', $id)) {
            throw new moodle_exception('invalidparameter', 'mod_webinairev2', '', $param);
        }
        return $id;
    }

    private function validateEmail(string $email): string {
        if (!validate_email($email)) {
            throw new moodle_exception('invalidparameter', 'mod_webinairev2', '', 'email');
        }
        return $email;
    }

    private function sanitizeName(string $name): string {
        $name = clean_param($name, PARAM_TEXT);
        $name = mb_substr(trim($name), 0, 100);
        return $name !== '' ? $name : 'Utilisateur';
    }

    private function request(string $method, string $path, array $data = []): array {
        $url  = $this->baseUrl . '/api' . $path;
        $curl = curl_init();

        $headers = [
            'Content-Type: application/json',
            'X-Api-Key: ' . $this->apiKey,
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($method === 'POST') {
            $payload = json_encode($data);
            if ($payload === false) {
                throw new moodle_exception('jsonencodeerror', 'mod_webinairev2');
            }
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        } elseif ($method === 'DELETE') {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response  = curl_exec($curl);
        $httpCode  = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($curlError) {
            debugging('webinairev2 cURL error: ' . $curlError, DEBUG_DEVELOPER);
            throw new moodle_exception('apierror', 'mod_webinairev2', '',
                'Erreur de communication avec le serveur webinairev2');
        }

        if ($response === false || $response === '') {
            throw new moodle_exception('apierror', 'mod_webinairev2', '', 'Réponse vide du serveur');
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new moodle_exception('apierror', 'mod_webinairev2', '', 'Réponse invalide du serveur');
        }

        if ($httpCode >= 400) {
            $msg = $decoded['message'] ?? $decoded['error'] ?? 'Erreur inconnue (HTTP ' . $httpCode . ')';
            throw new moodle_exception('apierror', 'mod_webinairev2', '', $msg);
        }

        return is_array($decoded) ? $decoded : [];
    }

    // Idempotent : rappelé plusieurs fois avec le même meetingId, ne crée la
    // salle qu'une seule fois côté webinairev2 (voir MoodleService.createOrGetRoom).
    public function createRoom(string $courseId, string $meetingId, string $title, string $teacherEmail, string $teacherName, string $description = ''): array {
        return $this->request('POST', '/moodle/rooms', [
            'courseId'     => $courseId,
            'meetingId'    => $meetingId,
            'title'        => $title,
            'description'  => $description,
            'teacherEmail' => $this->validateEmail($teacherEmail),
            'teacherName'  => $this->sanitizeName($teacherName),
        ]);
    }

    public function getRoomStatus(string $roomId): array {
        $roomId = $this->validateId($roomId, 'roomId');
        return $this->request('GET', '/moodle/rooms/' . $roomId . '/status');
    }

    /**
     * Enregistrements prêts de la salle, paginés côté serveur.
     *
     * @param int $page    numéro de page, 1 pour la première
     * @param int $perPage taille de page (le backend replafonne à 100)
     * @return array{recordings: array, total: int, page: int, perPage: int}
     */
    public function getRecordings(string $roomId, int $page = 1, int $perPage = 10): array {
        $roomId  = $this->validateId($roomId, 'roomId');
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $decoded = $this->request('GET', '/moodle/rooms/' . $roomId . '/recordings'
            . '?page=' . $page . '&perPage=' . $perPage);

        // Tolère l'ancienne forme « tableau nu » : si le backend webinairev2
        // n'a pas encore été redéployé au moment où ce plugin est mis à jour,
        // la page reste utilisable — simplement sans pagination.
        if (!array_key_exists('recordings', $decoded)) {
            $items = array_values($decoded);
            return ['recordings' => $items, 'total' => count($items), 'page' => 1, 'perPage' => $perPage];
        }

        return [
            'recordings' => is_array($decoded['recordings']) ? $decoded['recordings'] : [],
            'total'      => (int)($decoded['total'] ?? 0),
            'page'       => (int)($decoded['page'] ?? $page),
            'perPage'    => (int)($decoded['perPage'] ?? $perPage),
        ];
    }

    /**
     * Une URL renvoyée par le backend est-elle sûre à injecter dans un href ou
     * un <video src> ?
     *
     * Le backend est de confiance, mais ces URL traversent une configuration
     * (apiurl/FRONTEND_URL) qui peut diverger : on refuse tout ce qui n'est pas
     * du HTTPS sur le domaine configuré ou l'un de ses sous-domaines, ce qui
     * écarte au passage un href javascript: si la réponse était altérée.
     * Même garde que mod_livestream (V01/V06).
     */
    public function isSafeMediaUrl(string $url): bool {
        $parts = parse_url($url);
        if (empty($parts['scheme']) || empty($parts['host']) || $parts['scheme'] !== 'https') {
            return false;
        }
        $allowedHost = parse_url($this->baseUrl, PHP_URL_HOST);
        if (empty($allowedHost)) {
            return false;
        }
        return $parts['host'] === $allowedHost || str_ends_with($parts['host'], '.' . $allowedHost);
    }

    public function deleteRecording(string $roomId, string $recordingId): array {
        $roomId      = $this->validateId($roomId, 'roomId');
        $recordingId = $this->validateId($recordingId, 'recordingId');
        return $this->request('DELETE', '/moodle/rooms/' . $roomId . '/recordings/' . $recordingId);
    }

    // Inscrit (ou promeut) l'utilisateur courant sur ce cours côté webinairev2 —
    // appelé à chaque affichage de l'activité (voir view.php). isTeacher reflète
    // la capacité Moodle mod/webinairev2:moderate, pas un champ de la table
    // Moodle : webinairev2 applique lui-même la règle "promotion jamais
    // rétrogradation" (voir MoodleService.syncUser côté backend).
    public function syncUser(string $roomId, string $email, string $name, bool $isTeacher): array {
        return $this->request('POST', '/moodle/users/sync', [
            'roomId'    => $this->validateId($roomId, 'roomId'),
            'email'     => $this->validateEmail($email),
            'name'      => $this->sanitizeName($name),
            'isTeacher' => $isTeacher,
        ]);
    }

    // Construit le lien vers la salle webinairev2 — aucun jeton, aucune identité
    // transmise : webinairev2 authentifie lui-même l'utilisateur via son propre
    // SSO Keycloak (partagé avec Moodle) quand il ouvre ce lien.
    public function buildJoinUrl(string $roomId): string {
        return $this->baseUrl . '/rooms/' . $this->validateId($roomId, 'roomId');
    }
}
