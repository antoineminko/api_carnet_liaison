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
            'from_name'       => env('MAIL_FROM_NAME', 'Carnet de Liaison'),
            'frontend_url'    => env('FRONTEND_URL', 'https://carnet-liaison-three.vercel.app'),
            'subject'         => 'Accès à votre espace {nom_ecole} — n° {code_ecole}',
            'greeting'        => 'Bonjour,',
            'intro_message'   => "Votre établissement {nom_ecole} a été enregistré sur Carnet de Liaison.\nVoici vos informations de connexion. Le numéro d'espace {code_ecole} identifie votre école dans la plateforme.",
            'cta_label'       => 'Accéder à mon espace',
            'footer_note'     => 'Utilisez l\'email et le mot de passe indiqués pour vous connecter. Ce message est automatique, ne pas répondre.',
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
            $merged = array_merge($defaults, $decoded);

            // Remplace les anciens textes trop "marketing" (favorisent le spam)
            $subject = (string) ($merged['subject'] ?? '');
            if (
                str_contains($subject, 'Version 2.0')
                || str_contains($subject, 'Bienvenue sur Carnet')
                || str_contains($subject, 'félicitations')
            ) {
                foreach (['subject', 'greeting', 'intro_message', 'cta_label', 'footer_note', 'from_name'] as $key) {
                    $merged[$key] = $defaults[$key];
                }
            }

            return self::normalizeFromEmail($merged);
        } catch (\Throwable $e) {
            Log::warning('[SuperAdminEmailService] Impossible de lire la configuration email: ' . $e->getMessage());
            return $defaults;
        }
    }

    public static function isMaskedPassword(?string $password): bool
    {
        $password = trim((string) $password);
        return $password === '' || $password === '••••••••' || preg_match('/^•+$/u', $password) === 1;
    }

    /** Force l'expéditeur = compte SMTP (Gmail refuse hello@example.com, etc.). */
    public static function normalizeFromEmail(array $config): array
    {
        $username = trim((string) ($config['smtp_username'] ?? ''));
        $fromEmail = trim((string) ($config['from_email'] ?? ''));

        $invalidFrom = $fromEmail === ''
            || str_contains($fromEmail, 'example.com')
            || str_contains($fromEmail, 'noreply@carnetliaison');

        if ($username !== '' && $invalidFrom) {
            $config['from_email'] = $username;
        } elseif ($fromEmail === '' && $username !== '') {
            $config['from_email'] = $username;
        }

        return $config;
    }

    public static function saveConfig(array $newConfig): array
    {
        $current = self::getConfig();

        // Si le mot de passe envoyé est masqué ou vide, on conserve l'ancien mot de passe enregistré
        if (isset($newConfig['smtp_password']) && self::isMaskedPassword($newConfig['smtp_password'])) {
            unset($newConfig['smtp_password']);
        }

        $merged = self::normalizeFromEmail(array_merge($current, $newConfig));
        $path = self::getConfigPath();

        if (!is_dir(dirname($path))) {
            @mkdir(dirname($path), 0755, true);
        }

        $written = @file_put_contents(
            $path,
            json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        if ($written === false) {
            Log::error('[SuperAdminEmailService] Impossible d\'écrire ' . $path);
        }

        return $merged;
    }

    public static function applyRuntimeMailConfig(array $config): void
    {
        $config = self::normalizeFromEmail($config);
        $host = trim((string) ($config['smtp_host'] ?? 'smtp.gmail.com'));
        $port = (int) ($config['smtp_port'] ?? 587);
        $encryption = strtolower(trim((string) ($config['smtp_encryption'] ?? 'tls')));
        $username = trim((string) ($config['smtp_username'] ?? ''));
        $password = (string) ($config['smtp_password'] ?? '');
        // Retirer les espaces du mot de passe d'application Gmail (jjol gclj ... → jjolgclj...)
        $password = preg_replace('/\s+/', '', $password) ?? $password;
        $fromEmail = trim((string) ($config['from_email'] ?: $username));
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
        $greetingPlain = strtr((string) $config['greeting'], $replacements);
        $introPlain = strtr((string) $config['intro_message'], $replacements);
        $ctaLabelPlain = strtr((string) $config['cta_label'], $replacements);
        $footerPlain = strtr((string) $config['footer_note'], $replacements);

        $greeting = htmlspecialchars($greetingPlain, ENT_QUOTES, 'UTF-8');
        $introHtml = nl2br(htmlspecialchars($introPlain, ENT_QUOTES, 'UTF-8'));
        $ctaLabel = htmlspecialchars($ctaLabelPlain, ENT_QUOTES, 'UTF-8');
        $footerNote = nl2br(htmlspecialchars($footerPlain, ENT_QUOTES, 'UTF-8'));

        $escapedNom = htmlspecialchars($nomEcole, ENT_QUOTES, 'UTF-8');
        $escapedCode = htmlspecialchars($codeEcole, ENT_QUOTES, 'UTF-8');
        $escapedEmail = htmlspecialchars($emailAdmin, ENT_QUOTES, 'UTF-8');
        $escapedPassword = htmlspecialchars($motDePasse, ENT_QUOTES, 'UTF-8');
        $escapedLien = htmlspecialchars($lienEspace, ENT_QUOTES, 'UTF-8');
        $year = date('Y');

        // HTML transactionnel sobre (moins de signaux spam que le template marketing)
        $html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{$subject}</title>
</head>
<body style="margin:0;padding:0;background:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
    <tr>
      <td style="padding:24px 16px;">
        <table role="presentation" width="560" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;width:100%;margin:0 auto;border:1px solid #e5e7eb;">
          <tr>
            <td style="padding:20px 24px;border-bottom:1px solid #e5e7eb;background:#0a192f;color:#ffffff;font-size:18px;font-weight:700;">
              Carnet de Liaison
            </td>
          </tr>
          <tr>
            <td style="padding:24px;">
              <p style="margin:0 0 12px 0;font-size:15px;line-height:1.5;">{$greeting}</p>
              <p style="margin:0 0 20px 0;font-size:14px;line-height:1.6;color:#333333;">{$introHtml}</p>

              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #e5e7eb;margin:0 0 20px 0;">
                <tr>
                  <td style="padding:14px 16px;font-size:14px;line-height:1.7;">
                    <strong>Établissement :</strong> {$escapedNom}<br />
                    <strong>N° d'espace :</strong> {$escapedCode}<br />
                    <strong>Email :</strong> {$escapedEmail}<br />
                    <strong>Mot de passe :</strong> {$escapedPassword}
                  </td>
                </tr>
              </table>

              <p style="margin:0 0 8px 0;">
                <a href="{$escapedLien}" style="display:inline-block;background:#2596be;color:#ffffff;text-decoration:none;padding:12px 20px;font-size:14px;font-weight:700;">
                  {$ctaLabel}
                </a>
              </p>
              <p style="margin:0 0 16px 0;font-size:12px;line-height:1.5;color:#555555;word-break:break-all;">
                Lien : {$escapedLien}
              </p>
              <p style="margin:0;font-size:12px;line-height:1.5;color:#666666;">{$footerNote}</p>
            </td>
          </tr>
          <tr>
            <td style="padding:14px 24px;border-top:1px solid #e5e7eb;font-size:11px;color:#777777;">
              Carnet de Liaison — INITIAL — {$year}
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

        $text = implode("\n", [
            $greetingPlain,
            '',
            $introPlain,
            '',
            'Etablissement : ' . $nomEcole,
            "N d'espace : " . $codeEcole,
            'Email : ' . $emailAdmin,
            'Mot de passe : ' . $motDePasse,
            '',
            $ctaLabelPlain . ' :',
            $lienEspace,
            '',
            $footerPlain,
            '',
            'Carnet de Liaison — INITIAL — ' . $year,
        ]);

        return [
            'subject'     => $subject,
            'html'        => $html,
            'text'        => $text,
            'lien_espace' => $lienEspace,
        ];
    }

    public static function sendWelcomeEmail(array $schoolData, ?array $overrideConfig = null): array
    {
        $override = $overrideConfig ?? [];
        if (isset($override['smtp_password']) && self::isMaskedPassword($override['smtp_password'])) {
            unset($override['smtp_password']);
        }

        $config = self::normalizeFromEmail(array_merge(self::getConfig(), $override));
        $recipient = trim((string) ($schoolData['email_admin'] ?? ''));

        if ($recipient === '') {
            return [
                'sent'    => false,
                'message' => 'Aucune adresse email destinataire fournie.',
            ];
        }

        if (empty($config['smtp_username']) || self::isMaskedPassword($config['smtp_password'] ?? '')) {
            return [
                'sent'    => false,
                'message' => 'Mot de passe d\'application Gmail manquant. Saisissez-le puis cliquez sur Enregistrer.',
            ];
        }

        try {
            self::applyRuntimeMailConfig($config);
            $mailContent = self::buildStyledEmail($schoolData, $config);

            $fromEmail = trim((string) ($config['from_email'] ?: $config['smtp_username']));
            $fromName = trim((string) ($config['from_name'] ?: 'Carnet de Liaison'));

            Mail::send([], [], function ($message) use ($recipient, $mailContent, $fromEmail, $fromName) {
                $message->to($recipient)
                    ->subject($mailContent['subject'])
                    ->html($mailContent['html'])
                    ->text($mailContent['text'] ?? strip_tags($mailContent['html']));

                if ($fromEmail !== '') {
                    $message->from($fromEmail, $fromName);
                    $message->replyTo($fromEmail, $fromName);
                    $message->returnPath($fromEmail);
                }

                // Signaux transactionnels (réduit le filtrage promo/spam)
                try {
                    $symfony = method_exists($message, 'getSymfonyMessage')
                        ? $message->getSymfonyMessage()
                        : null;
                    if ($symfony && method_exists($symfony, 'getHeaders')) {
                        $headers = $symfony->getHeaders();
                        $headers->addTextHeader('Auto-Submitted', 'auto-generated');
                        $headers->addTextHeader('X-Auto-Response-Suppress', 'All');
                        $headers->addTextHeader('X-Entity-Ref-ID', 'carnet-liaison-welcome');
                    }
                } catch (\Throwable $headerError) {
                    // Headers optionnels
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
