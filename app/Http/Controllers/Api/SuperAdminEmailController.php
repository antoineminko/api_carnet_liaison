<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SuperAdminEmailService;
use Illuminate\Http\Request;

class SuperAdminEmailController extends Controller
{
    private const ADMIN_USER = 'admin';
    private const ADMIN_PASS = 'admin££';

    private function expectedToken(): string
    {
        return hash_hmac('sha256', self::ADMIN_USER . ':' . self::ADMIN_PASS, env('APP_KEY', 'carnet_liaison_v2_key'));
    }

    private function isAuthorized(Request $request): bool
    {
        $headerToken = $request->header('X-SuperAdmin-Token') ?: $request->bearerToken();
        if (!$headerToken) {
            return false;
        }
        return hash_equals($this->expectedToken(), (string) $headerToken);
    }

    public function login(Request $request)
    {
        $username = trim((string) $request->input('username', ''));
        $password = (string) $request->input('password', '');

        if ($username === self::ADMIN_USER && $password === self::ADMIN_PASS) {
            return response()->json([
                'success' => true,
                'token'   => $this->expectedToken(),
                'message' => 'Authentification Super-Admin réussie.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Identifiants administrateur invalides.',
        ], 401);
    }

    public function getConfig(Request $request)
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 401);
        }

        $config = SuperAdminEmailService::getConfig();
        $hasPassword = !empty($config['smtp_password']);
        $config['has_password'] = $hasPassword;
        $config['smtp_password'] = $hasPassword ? '••••••••' : '';

        $preview = SuperAdminEmailService::buildStyledEmail([
            'nom'         => 'Collège Saint-Exupéry',
            'code'        => 'STEX2026',
            'email_admin' => 'direction@saint-exupery.com',
        ], $config);

        return response()->json([
            'success'      => true,
            'config'       => $config,
            'preview_html' => $preview['html'],
        ]);
    }

    public function saveConfig(Request $request)
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 401);
        }

        $allowed = $request->only([
            'provider',
            'smtp_host',
            'smtp_port',
            'smtp_encryption',
            'smtp_username',
            'smtp_password',
            'from_email',
            'from_name',
            'frontend_url',
            'subject',
            'greeting',
            'intro_message',
            'cta_label',
            'footer_note',
        ]);

        if (isset($allowed['smtp_port'])) {
            $allowed['smtp_port'] = (int) $allowed['smtp_port'];
        }

        $saved = SuperAdminEmailService::saveConfig($allowed);
        $hasPassword = !empty($saved['smtp_password']);
        $saved['has_password'] = $hasPassword;
        $saved['smtp_password'] = $hasPassword ? '••••••••' : '';

        $preview = SuperAdminEmailService::buildStyledEmail([
            'nom'         => 'Collège Saint-Exupéry',
            'code'        => 'STEX2026',
            'email_admin' => 'direction@saint-exupery.com',
        ], $saved);

        return response()->json([
            'success'      => true,
            'message'      => 'Configuration email enregistrée avec succès.',
            'config'       => $saved,
            'preview_html' => $preview['html'],
        ]);
    }

    public function sendTestEmail(Request $request)
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 401);
        }

        $request->validate([
            'test_email' => 'required|email',
        ]);

        // Sauvegarder d'abord les éventuels changements en cours si transmis
        $override = $request->only([
            'provider',
            'smtp_host',
            'smtp_port',
            'smtp_encryption',
            'smtp_username',
            'smtp_password',
            'from_email',
            'from_name',
            'frontend_url',
            'subject',
            'greeting',
            'intro_message',
            'cta_label',
            'footer_note',
        ]);

        if (!empty($override)) {
            SuperAdminEmailService::saveConfig($override);
        }

        $result = SuperAdminEmailService::sendWelcomeEmail([
            'nom'          => $request->input('school_name', 'Collège Saint-Exupéry'),
            'code'         => $request->input('school_code', 'STEX2026'),
            'email_admin'  => $request->input('test_email'),
            'mot_de_passe' => $request->input('school_password', 'MotDePasseDemo'),
        ]);

        if ($result['sent']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'],
        ], 422);
    }
}
