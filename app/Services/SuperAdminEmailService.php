<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SuperAdminEmailService
{
    private const CONFIG_FILENAME = 'superadmin_email_config.json';

    public static function getConfigPath(): string
    {
        return storage_path('app/' . self::CONFIG_FILENAME);
    }

    public static function getDefaultConfig(): array
    {
        return [
            'provider'        => 'gmail',
            'smtp_host'       => env('MAIL_HOST', 'smtp.gmail.com'),
            'smtp_port'       => (int) env('MAIL_PORT', 587),
            'smtp_encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'smtp_username'   => env('MAIL_USERNAME', ''),
            'smtp_password'   => env('MAIL_PASSWORD', ''),
            'from_email'      => env('MAIL_FROM_ADDRESS', ''),
            'from_name'       => env('MAIL_FROM_NAME', 'Carnet de Liaison 2.0 - INITIAL'),
            'frontend_url'    => env('FRONTEND_URL', 'https://carnet-liaison-three.vercel.app'),
            'subject'         => 'Bienvenue — Votre espace école {nom_ecole} est prêt',
            'greeting'        => 'Bonjour la Direction de {nom_ecole},',
            'intro_message'   => "Merci d'avoir créé votre compte sur Carnet de Liaison.\nChaque établissement dispose de son propre espace. Votre numéro d'espace est {code_ecole}. Conservez-le : il identifie votre école dans la plateforme multi-espaces.",
            'cta_label'       => 'Ouvrir mon espace école',
            'footer_note'     => 'Connexion : email {email_admin} + mot de passe ci-dessus. Le numéro d\'espace {code_ecole} désigne uniquement votre établissement.',
        ];
    }

    public static function getConfig(): array
    {
        $defaults = self::getDefaultConfig();
        $path = self::getConfigPath();

        if (!is_file($path)) {
            return $defaults;
        }

        try {
            $raw = file_get_contents($path);
            $decoded = json_decode($raw ?: '{}', true);
            if (!is_array($decoded)) {
                return $defaults;
            }
            return array_merge($defaults, $decoded);
        } catch (\Throwable $e) {
            Log::warning('[SuperAdminEmailService] Impossible de lire la configuration email: ' . $e->getMessage());
            return $defaults;
        }
    }

    public static function saveConfig(array $newConfig): array
    {
        $current = self::getConfig();

        // Si le mot de passe envoyé est masqué ou vide, on conserve l'ancien mot de passe enregistré
        if (isset($newConfig['smtp_password']) && ($newConfig['smtp_password'] === '••••••••' || $newConfig['smtp_password'] === '')) {
            unset($newConfig['smtp_password']);
        }

        $merged = array_merge($current, $newConfig);
        $path = self::getConfigPath();

        if (!is_dir(dirname($path))) {
            @mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $merged;
    }

    public static function applyRuntimeMailConfig(array $config): void
    {
        $host = trim((string) ($config['smtp_host'] ?? 'smtp.gmail.com'));
        $port = (int) ($config['smtp_port'] ?? 587);
        $encryption = strtolower(trim((string) ($config['smtp_encryption'] ?? 'tls')));
        $username = trim((string) ($config['smtp_username'] ?? ''));
        $password = (string) ($config['smtp_password'] ?? '');
        $fromEmail = trim((string) ($config['from_email'] ?: $username ?: 'noreply@carnetliaison.com'));
        $fromName = trim((string) ($config['from_name'] ?: 'Carnet de Liaison 2.0 - INITIAL'));

        $scheme = null;
        if ($encryption === 'ssl' || $port === 465) {
            $scheme = 'smtps';
        } elseif ($encryption === 'tls' || $port === 587) {
            $scheme = 'smtp';
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp', [
            'transport'    => 'smtp',
            'scheme'       => $scheme,
            'host'         => $host,
            'port'         => $port,
            'encryption'   => ($encryption === 'none' || $encryption === '') ? null : $encryption,
            'username'     => $username,
            'password'     => $password,
            'timeout'      => 20,
            'local_domain' => parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost',
        ]);
        Config::set('mail.from.address', $fromEmail);
        Config::set('mail.from.name', $fromName);

        // Forcer la réinitialisation du mailer SMTP en mémoire pour prendre en compte les nouveaux paramètres
        try {
            app('mail.manager')->purge('smtp');
        } catch (\Throwable $e) {
            // Ignorer si non supporté par la version courante
        }
    }

    public static function buildStyledEmail(array $schoolData, ?array $customConfig = null): array
    {
        $config = $customConfig ? array_merge(self::getConfig(), $customConfig) : self::getConfig();

        $nomEcole = (string) ($schoolData['nom'] ?? 'Votre Établissement');
        $codeEcole = strtoupper((string) ($schoolData['code'] ?? 'ECOLE2026'));
        $emailAdmin = (string) ($schoolData['email_admin'] ?? 'direction@ecole.com');
        $motDePasse = (string) ($schoolData['mot_de_passe'] ?? $schoolData['password_admin'] ?? '');
        if ($motDePasse === '') {
            $motDePasse = '(mot de passe défini à l\'inscription)';
        }
        $frontendUrl = rtrim((string) ($config['frontend_url'] ?: 'https://carnet-liaison-three.vercel.app'), '/');
        $lienEspace = $schoolData['lien_espace'] ?? "{$frontendUrl}/ecoles/{$codeEcole}/login";

        $replacements = [
            '{nom_ecole}'      => $nomEcole,
            '{code_ecole}'     => $codeEcole,
            '{email_admin}'    => $emailAdmin,
            '{mot_de_passe}'   => $motDePasse,
            '{lien_espace}'    => $lienEspace,
            '{lien_connexion}' => $lienEspace,
        ];

        $subject = strtr((string) $config['subject'], $replacements);
        $greeting = htmlspecialchars(strtr((string) $config['greeting'], $replacements), ENT_QUOTES, 'UTF-8');
        $introRaw = htmlspecialchars(strtr((string) $config['intro_message'], $replacements), ENT_QUOTES, 'UTF-8');
        $introHtml = nl2br($introRaw);
        $ctaLabel = htmlspecialchars(strtr((string) $config['cta_label'], $replacements), ENT_QUOTES, 'UTF-8');
        $footerNote = nl2br(htmlspecialchars(strtr((string) $config['footer_note'], $replacements), ENT_QUOTES, 'UTF-8'));

        $escapedNom = htmlspecialchars($nomEcole, ENT_QUOTES, 'UTF-8');
        $escapedCode = htmlspecialchars($codeEcole, ENT_QUOTES, 'UTF-8');
        $escapedEmail = htmlspecialchars($emailAdmin, ENT_QUOTES, 'UTF-8');
        $escapedPassword = htmlspecialchars($motDePasse, ENT_QUOTES, 'UTF-8');
        $escapedLien = htmlspecialchars($lienEspace, ENT_QUOTES, 'UTF-8');
        $year = date('Y');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{$subject}</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0f172a;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f1f5f9;padding:36px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:24px;overflow:hidden;box-shadow:0 20px 45px rgba(10,25,47,0.10);border:1px solid #e2e8f0;">
          
          <!-- En-tête Institutionnel -->
          <tr>
            <td style="background:linear-gradient(135deg,#0a192f 0%,#112a46 55%,#2596be 100%);padding:40px 36px;text-align:center;">
              <div style="display:inline-block;background-color:rgba(37,150,190,0.22);border:1px solid rgba(99,188,224,0.45);border-radius:999px;padding:6px 16px;font-size:11px;font-weight:800;letter-spacing:1.4px;text-transform:uppercase;color:#7dd3fc;margin-bottom:16px;">
                ★ Solution Officielle • Version 2.0
              </div>
              <h1 style="margin:0;font-size:28px;line-height:1.25;font-weight:800;color:#ffffff;letter-spacing:-0.4px;">
                Carnet de <span style="color:#63bce0;">Liaison</span>
              </h1>
              <p style="margin:10px 0 0 0;font-size:14px;color:#cbd5e1;font-weight:500;">
                Le lien numérique direct entre l'école et la famille
              </p>
            </td>
          </tr>

          <!-- Corps du message -->
          <tr>
            <td style="padding:38px 36px 28px 36px;">
              <h2 style="margin:0 0 16px 0;font-size:20px;font-weight:800;color:#0f172a;">
                {$greeting}
              </h2>
              <p style="margin:0 0 26px 0;font-size:15px;line-height:1.7;color:#475569;">
                {$introHtml}
              </p>

              <!-- Carte Récapitulative de l'Établissement -->
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f8fafc;border:1px solid #cbd5e1;border-left:5px solid #2596be;border-radius:16px;margin-bottom:28px;">
                <tr>
                  <td style="padding:22px 24px;">
                    <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1.1px;color:#2596be;margin-bottom:8px;">
                      Accès à votre espace école
                    </div>
                    <p style="margin:0 0 14px 0;font-size:12px;line-height:1.55;color:#64748b;">
                      Carnet de Liaison est une plateforme multi-espaces : chaque école a son numéro d'espace. Le vôtre est <strong style="color:#0f172a;">{$escapedCode}</strong>.
                    </p>
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="font-size:14px;color:#1e293b;">
                      <tr>
                        <td style="padding:6px 0;color:#64748b;width:42%;font-weight:600;">Établissement :</td>
                        <td style="padding:6px 0;font-weight:800;color:#0f172a;">{$escapedNom}</td>
                      </tr>
                      <tr>
                        <td style="padding:6px 0;color:#64748b;font-weight:600;">N° d'espace :</td>
                        <td style="padding:6px 0;">
                          <span style="font-family:Consolas,Monaco,monospace;font-weight:800;font-size:14px;color:#0a192f;background-color:#e0f2fe;border:1px solid #bae6fd;padding:3px 10px;border-radius:6px;">
                            {$escapedCode}
                          </span>
                        </td>
                      </tr>
                      <tr>
                        <td style="padding:6px 0;color:#64748b;font-weight:600;">Email de connexion :</td>
                        <td style="padding:6px 0;font-weight:700;color:#0f172a;">{$escapedEmail}</td>
                      </tr>
                      <tr>
                        <td style="padding:6px 0;color:#64748b;font-weight:600;">Mot de passe :</td>
                        <td style="padding:6px 0;">
                          <span style="font-family:Consolas,Monaco,monospace;font-weight:800;font-size:14px;color:#0a192f;background-color:#fef3c7;border:1px solid #fcd34d;padding:3px 10px;border-radius:6px;">
                            {$escapedPassword}
                          </span>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>

              <!-- Bouton d'action principal (CTA) -->
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:30px 0;">
                <tr>
                  <td align="center">
                    <a href="{$escapedLien}" target="_blank" style="display:inline-block;background-color:#2596be;color:#ffffff;font-size:16px;font-weight:800;text-decoration:none;padding:16px 36px;border-radius:999px;box-shadow:0 10px 25px rgba(37,150,190,0.35);">
                      {$ctaLabel} &rarr;
                    </a>
                  </td>
                </tr>
              </table>

              <!-- Lien direct copiable -->
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f0f9ff;border:1px dashed #7dd3fc;border-radius:12px;margin-bottom:24px;">
                <tr>
                  <td style="padding:14px 18px;text-align:center;">
                    <div style="font-size:11px;font-weight:700;color:#0369a1;text-transform:uppercase;letter-spacing:0.8px;margin-bottom:4px;">
                      Lien direct de votre espace école
                    </div>
                    <a href="{$escapedLien}" target="_blank" style="font-size:13px;font-family:Consolas,Monaco,monospace;color:#0284c7;word-break:break-all;text-decoration:underline;font-weight:700;">
                      {$escapedLien}
                    </a>
                  </td>
                </tr>
              </table>

              <p style="margin:0;font-size:13px;line-height:1.6;color:#64748b;background-color:#fffbeb;border:1px solid #fde68a;padding:12px 16px;border-radius:12px;">
                💡 {$footerNote}
              </p>
            </td>
          </tr>

          <!-- Pied de page -->
          <tr>
            <td style="background-color:#0a192f;padding:24px 36px;text-align:center;color:#94a3b8;font-size:12px;">
              <p style="margin:0 0 6px 0;font-weight:700;color:#ffffff;font-size:13px;">
                Carnet de Liaison — Version 2.0
              </p>
              <p style="margin:0;color:#64748b;">
                © {$year} Tous droits réservés • Solution éditée par <strong style="color:#e2e8f0;">INITIAL</strong>
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

        return [
            'subject'     => $subject,
            'html'        => $html,
            'lien_espace' => $lienEspace,
        ];
    }

    public static function sendWelcomeEmail(array $schoolData, ?array $overrideConfig = null): array
    {
        $config = $overrideConfig ? array_merge(self::getConfig(), $overrideConfig) : self::getConfig();
        $recipient = trim((string) ($schoolData['email_admin'] ?? ''));

        if ($recipient === '') {
            return [
                'sent'    => false,
                'message' => 'Aucune adresse email destinataire fournie.',
            ];
        }

        if (empty($config['smtp_username']) || empty($config['smtp_password'])) {
            return [
                'sent'    => false,
                'message' => 'Boîte email expéditrice non configurée dans l\'espace Super-Admin.',
            ];
        }

        try {
            self::applyRuntimeMailConfig($config);
            $mailContent = self::buildStyledEmail($schoolData, $config);

            Mail::html($mailContent['html'], function ($message) use ($recipient, $mailContent, $config) {
                $fromEmail = trim((string) ($config['from_email'] ?: $config['smtp_username']));
                $fromName = trim((string) ($config['from_name'] ?: 'Carnet de Liaison 2.0 - INITIAL'));

                $message->to($recipient)
                    ->subject($mailContent['subject']);

                if ($fromEmail !== '') {
                    $message->from($fromEmail, $fromName);
                }
            });

            return [
                'sent'        => true,
                'message'     => "Email envoyé avec succès à {$recipient}.",
                'lien_espace' => $mailContent['lien_espace'],
            ];
        } catch (\Throwable $e) {
            Log::error('[SuperAdminEmailService] Échec envoi email à ' . $recipient . ': ' . $e->getMessage());
            return [
                'sent'    => false,
                'message' => 'Erreur SMTP : ' . $e->getMessage(),
            ];
        }
    }
}
