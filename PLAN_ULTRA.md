# PLAN ULTRA — Skooly Platform
## Architecture Consolidée : appweb_skooly + app_mobile + API Laravel

> Document de référence unique pour la consolidation de l'architecture complète.
> Couvre : inventaire des deux apps, points de simulation, scénarios métier, API Laravel, base de données.

---

## PARTIE 1 — INVENTAIRE COMPLET DES DEUX APPLICATIONS

---

### 1.1 — appweb_skooly (React 19 + Vite 6 + TailwindCSS 4)

**Rôle :** Interface web d'administration et de gestion scolaire.
**Stack :** React 19, Vite 6, TailwindCSS 4, React Router 7, Axios, PWA (Workbox)
**Déploiement :** Vercel + Docker

#### Pages et routes existantes

| Route | Composant | Rôle | État |
|---|---|---|---|
| `/` | `Accueil` | Landing page publique | ✅ Existe |
| `/register` | `SchoolCreationForm` | Création d'établissement | ✅ Existe |
| `/configuration-classes` | `ClassConfigurationForm` | Config classes/filières | ✅ Existe |
| `/school-login` | `SchoolLoginPage` | Connexion établissement | ✅ Existe |
| `/school-dashboard` | `NewHome` | Dashboard post-login école | ✅ Existe |
| `/unauthorized` | `UnauthorizedPage` | Accès refusé | ✅ Existe |
| `/pwa-settings` | `PWASettings` | Paramètres PWA | ✅ Existe |
| `/admin/dashboard` | `AdminDashboard` | Tableau de bord admin | ✅ Existe |
| `/admin/classes` | `ClassesPage` | Gestion des classes | ✅ Existe |
| `/admin/communications` | `CommunicationsPage` | Communications (lecture seule) | ✅ Existe |
| `/admin/signalements` | `SignalementsPage` | Signalements officiels | ✅ Existe |
| `/admin/messages` | `MessagesPage` | Messagerie admin | ✅ Existe |


#### Features existantes dans appweb_skooly

**featuresAuth**
- `LoginModal`, `SimpleLoginModal`, `LoginButton`
- `useAuth` hook : login, logout, hasRole, hasPermission (JWT localStorage)
- `AuthProvider` : contexte global d'authentification

**featuresAdmin**
- `AdminDashboard` : stats globales (enseignants, classes, parents)
- `ClassesPage` : liste des classes + vue détaillée (onglets Élèves/Annonces)
- `CommunicationsPage` : échanges parent↔enseignant (lecture seule admin)
- `MessagesPage` : messagerie admin (Diffusion / Ciblés / Reçus)
- `SignalementsPage` : signalements officiels avec actions admin
- `AttendanceAdminPage` : présences côté admin
- `ConfigurationPage` : configuration de l'établissement
- Hooks : `useAdminDashboard`, `useAdminConfiguration`, `useOnlineAgents`
- Composants : `StatisticsBox`, `MonthlyPresenceChart`, `CalendarGraph`, `NotificationBell`

**featuresAgent**
- CRUD complet des agents (enseignants/personnel)
- Gestion des 6 boxes d'accès mobile par agent
- `AgentManagementPage`, `AgentTable`, `AgentModal`, `AgentAccessModal`
- `useAgent` hook, `agentService`, `agentValidation`

**featuresUser**
- `VisitorLoginModal` : connexion visiteur non authentifié

#### Composants partagés appweb

- `Layout` : Shell (Sidebar + Header + Footer)
- `ProtectedRoute` : garde auth + rôle
- `AdminRoutes` : sous-routeur admin
- `Sidebar` : navigation admin (Dashboard, Classes, Communication, Signalements)
- `DropdownNotifications`, `DropdownProfile`, `DropdownHelp`
- PWA : `PWAManager`, `OfflineIndicator`, `CacheManager`, `NotificationManager`

#### Ce qui MANQUE dans appweb (à créer)

- ❌ Page de connexion parent (portail parent web)
- ❌ Gestion QR Code / Code secret élève
- ❌ Module paiement (rappel + paiement en ligne)
- ❌ Tableau de bord parent web
- ❌ Messagerie parent ↔ enseignant (côté parent)
- ❌ Notifications temps réel (WebSocket/Pusher)
- ❌ Gestion des présences en temps réel (feed live)


---

### 1.2 — app_mobile (Flutter 3 + Dart 3.10 + Material Design 3)

**Rôle :** Application mobile multi-rôles (Parent, Enseignant, Élève).
**Stack :** Flutter 3, Dart 3.10, Material Design 3
**Cibles :** Android, iOS (+ Web/Desktop Flutter)
**État actuel :** Prototype UI — données hardcodées, pas d'API réelle, pas de gestion d'état globale

#### Features existantes dans app_mobile

**auth/**
- `SelectRolePage` : sélection du rôle (Parent / Enseignant / Élève)
- `LoginPage` : formulaire de connexion par rôle (modal bottom sheet)
- `CreateAccountPage` : création de compte
- `FirstAccessPage` : premier accès
- `AuthService` : simulation auth (3 cas : fail / new / success)
- ⚠️ Mode démo : login bypassé, navigation directe vers home

**parent/**
- `ParentHomePage` : shell principal (BottomNavigationBar 4 onglets dynamiques)
- `ChildDashboard` : vue détaillée d'un enfant (3 onglets : Aperçu/Actualités/Devoirs)
- `ChildDetailsPage` : détails complets enfant
- `ChildrenList` : liste des enfants
- `CalendarPage` : suivi présences (calendrier mensuel + justifications)
- `TextbookPage` : cahier de textes numérique
- Composants : `ChildCard`, `AttendanceStatus`, `HomeworkPreview`, `SignatureModal`
- 3 enfants hardcodés : Yannick, Emmanuella, Junior (3 établissements)

**teacher/**
- `TeacherHomePage` : shell (BottomNavigationBar 5 onglets)
- `TeacherClassesPage` : liste des classes enseignées
- `ClassDashboardPage` : dashboard classe (5 onglets : Appel/Cahier/Devoirs/Élèves/Stats)
- `AttendanceView` : faire l'appel (grille numérotée, statuts Présent/Absent/Retard)
- `TextbookView` : publier cours + devoirs
- `HomeworkManager`, `CreateHomeworkPage` : gestion devoirs
- `GradesEntryView` : saisie des notes
- `TeacherMessagesPage` : messagerie enseignant
- `TeacherProfilePage` : profil enseignant
- Switcher d'établissement (multi-école)

**student/**
- `StudentMainPage` : shell (BottomNavigationBar 5 onglets)
- `StudentDashboardPage` : accueil élève
- `StudentTextbookPage` : cahier de textes
- `StudentHomeworkPage` : devoirs
- `StudentMessagesPage` : messages
- `StudentProfilePage` : profil

**communication/**
- `MessagesPage` : interface messagerie
- `AnnouncementsPage` : annonces établissement
- Composants : `MessageCard`, `ConfirmationBadge`

**documents/**
- `DocumentsPage` : liste et gestion documents
- `DocumentViewer` : visualiseur
- `SignaturePad` : signature numérique

**notifications/**
- `NotificationsService` : service notifications push (placeholder)

#### Ce qui MANQUE dans app_mobile (à créer/implémenter)

- ❌ Gestion d'état globale (Riverpod recommandé — actuellement setState local)
- ❌ Couche HTTP réelle (dio ou http — aucun package réseau installé)
- ❌ Authentification JWT réelle (token stocké, refresh)
- ❌ Scanner QR Code (qr_code_scanner ou mobile_scanner)
- ❌ Notifications push réelles (firebase_messaging)
- ❌ Module paiement parent (Airtel Money / bancaire)
- ❌ Connexion parent-enfant par QR Code ou code secret
- ❌ Présences temps réel (WebSocket)
- ❌ Localisation (intl / flutter_localizations)


---

## PARTIE 2 — POINTS DE SIMULATION ENTRE appweb ET app_mobile

---

### 2.1 — Flux de données inter-applications

```
┌─────────────────────────────────────────────────────────────────────┐
│                        API LARAVEL (api/)                           │
│                    Backend central — JWT Auth                       │
│                  WebSocket (Laravel Echo + Pusher)                  │
└──────────────┬──────────────────────────────┬───────────────────────┘
               │                              │
               ▼                              ▼
   ┌───────────────────┐          ┌───────────────────────┐
   │   appweb_skooly   │          │      app_mobile        │
   │  (React 19 + PWA) │          │  (Flutter 3 + Dart)   │
   │                   │          │                        │
   │  • Admin          │          │  • Parent (mobile)     │
   │  • Enseignant web │          │  • Enseignant (mobile) │
   │  • Portail parent │          │  • Élève (mobile)      │
   └───────────────────┘          └───────────────────────┘
```

### 2.2 — Table des synchronisations critiques

| Action source | App source | Reçu par | App cible | Temps |
|---|---|---|---|---|
| Enseignant fait l'appel | app_mobile (teacher) | Parent reçoit notification présence | app_mobile (parent) | Temps réel |
| Enseignant publie cours/devoir | app_mobile (teacher) | Parent voit dans cahier de textes | app_mobile (parent) | Temps réel |
| Admin envoie rappel paiement | appweb (admin) | Parent reçoit notification + alerte | app_mobile (parent) | Immédiat |
| Admin envoie convocation | appweb (admin) | Parent reçoit notification + message | app_mobile (parent) | Immédiat |
| Parent justifie absence | app_mobile (parent) | Admin voit dans Communications | appweb (admin) | Immédiat |
| Parent demande RDV enseignant | app_mobile (parent) | Enseignant reçoit demande | app_mobile (teacher) | Immédiat |
| Enseignant accepte/refuse RDV | app_mobile (teacher) | Parent reçoit réponse | app_mobile (parent) | Immédiat |
| Parent effectue paiement | app_mobile (parent) | Admin voit paiement validé | appweb (admin) | Immédiat |
| QR Code scanné (parent inconnu) | app_mobile (parent) | Admin reçoit alerte connexion | appweb (admin) | Temps réel |
| Admin envoie signalement | appweb (admin) | Enseignant + Parent notifiés | app_mobile | Immédiat |


---

## PARTIE 3 — SCÉNARIO 1 : CONNEXION PARENT ↔ ENFANT

---

### 3.1 — Cas A : Parent IDENTIFIÉ (déjà enregistré dans le système)

```
FLUX COMPLET — Parent identifié se connecte à son enfant

1. Parent ouvre app_mobile
2. Sélectionne rôle "Parent"
3. Se connecte avec email + mot de passe → JWT token
4. Dashboard parent s'affiche avec ses enfants déjà liés

   OU si premier accès :

1. Parent ouvre app_mobile → "Premier accès"
2. Choisit méthode de liaison :
   ┌─────────────────────────────────────────────┐
   │  Comment lier votre enfant ?                │
   │                                             │
   │  [📷 Scanner le QR Code de l'enfant]        │
   │  [🔑 Entrer le code secret + établissement] │
   └─────────────────────────────────────────────┘

   OPTION A — QR Code :
   → Parent scanne le QR Code imprimé sur la carte de l'élève
   → QR Code contient : { student_id, school_id, secret_token }
   → API vérifie : parent est-il enregistré comme parent de cet élève ?
   → OUI → liaison confirmée → enfant apparaît dans le dashboard
   → NON → voir Cas B (parent non identifié)

   OPTION B — Code secret + Établissement :
   → Parent saisit : Code secret élève (ex: SKL-2026-4521)
   → Parent saisit : Nom de l'établissement (autocomplete)
   → API vérifie : code secret valide + parent lié à cet élève ?
   → OUI → liaison confirmée
   → NON → voir Cas B
```

### 3.2 — Cas B : Parent NON IDENTIFIÉ (inconnu du système)

```
FLUX — Parent inconnu tente de se connecter à un enfant

1. Parent scanne QR Code OU saisit code secret + établissement
2. API vérifie → parent NON enregistré comme parent de cet élève

3. app_mobile affiche :
   ┌─────────────────────────────────────────────────────────┐
   │  ⚠️  Connexion non autorisée                           │
   │                                                         │
   │  Vous n'êtes pas identifié(e) comme parent ou tuteur   │
   │  légal de cet élève dans notre système.                 │
   │                                                         │
   │  L'établissement a été informé de cette tentative.      │
   │  Un responsable vous contactera pour vérification.      │
   │                                                         │
   │  [Contacter l'établissement]  [Fermer]                  │
   └─────────────────────────────────────────────────────────┘

4. SIMULTANÉMENT → API envoie notification à appweb (admin) :
   ┌─────────────────────────────────────────────────────────┐
   │  🔔 ALERTE — Tentative de connexion non autorisée      │
   │                                                         │
   │  Élève : Junior Nguema (3ème B)                        │
   │  QR Code scanné à : 14:32 le 23/05/2026               │
   │  Appareil : Android — IP : 197.x.x.x                  │
   │  Compte utilisé : parent_inconnu@email.com             │
   │                                                         │
   │  [Voir le profil]  [Contacter]  [Ignorer]              │
   └─────────────────────────────────────────────────────────┘

5. Admin peut :
   → Contacter le parent inconnu pour vérification
   → Valider et créer le lien parent-enfant
   → Rejeter et bloquer la tentative
```

### 3.3 — Génération et gestion des QR Codes (côté appweb admin)

```
Dans appweb → /admin/classes → Vue élève :
→ Bouton "Générer QR Code" par élève
→ QR Code contient (chiffré) : { student_id, school_id, secret_token, expires_at }
→ Peut être imprimé ou envoyé par email au parent identifié
→ Code secret alternatif : généré automatiquement (format SKL-YYYY-XXXX)
→ Visible dans la fiche élève côté admin
```


---

## PARTIE 4 — SCÉNARIO 2 : COMMUNICATIONS MULTI-ACTEURS

---

### 4.1 — Administration → Parent : Rappel de paiement

```
FLUX COMPLET

1. Admin (appweb) → /admin/messages → Onglet "Ciblés"
   → Sélectionne : "Rappel paiement"
   → Choisit : Parent(s) concerné(s) ou classe entière
   → Saisit : montant dû, échéance, motif
   → Clique "Envoyer"

2. API Laravel :
   → Crée notification type "payment_reminder"
   → Envoie push notification via Firebase FCM
   → Enregistre en base (table notifications)

3. app_mobile (parent) reçoit :
   ┌─────────────────────────────────────────────────────────┐
   │  🔔 Rappel de paiement — Lycée Notre-Dame              │
   │                                                         │
   │  Montant dû : 75 000 FCFA                              │
   │  Motif : Frais de scolarité — 2ème trimestre           │
   │  Échéance : 30 Mai 2026                                │
   │                                                         │
   │  [💳 Payer maintenant]  [Voir détails]  [Plus tard]    │
   └─────────────────────────────────────────────────────────┘

4. Parent clique "Payer maintenant" :
   → Choix du mode de paiement :
     ┌──────────────────────────────────────┐
     │  [📱 Airtel Money]                   │
     │  [🏦 Virement bancaire]              │
     │  [💳 Carte bancaire]                 │
     └──────────────────────────────────────┘
   → Saisit numéro / infos paiement
   → Confirmation → API enregistre paiement
   → Admin voit paiement validé dans appweb
```

### 4.2 — Administration → Parent : Convocation

```
FLUX COMPLET

1. Admin (appweb) → /admin/messages → Onglet "Ciblés"
   → Type : "Convocation officielle"
   → Destinataire : parent spécifique
   → Objet : "Convocation — Comportement de votre enfant"
   → Date/heure proposée : 26/05/2026 à 10h00
   → Clique "Envoyer convocation"

2. app_mobile (parent) reçoit notification urgente :
   ┌─────────────────────────────────────────────────────────┐
   │  📋 CONVOCATION — Lycée Notre-Dame                     │
   │                                                         │
   │  Objet : Comportement de votre enfant Junior           │
   │  Date : Mercredi 26 Mai 2026 à 10h00                  │
   │  Lieu : Bureau de la Direction                         │
   │                                                         │
   │  [✅ Confirmer ma présence]  [📞 Appeler]  [Voir]      │
   └─────────────────────────────────────────────────────────┘

3. Parent confirme → Admin voit confirmation dans appweb
```

### 4.3 — Enseignant → Présences → Parent (temps réel)

```
FLUX TEMPS RÉEL — Le plus critique

1. Enseignant (app_mobile) → Classes → 3ème B → Onglet "Appel"
   → Marque les présences (grille numérotée)
   → Clique "VALIDER L'APPEL"

2. API Laravel reçoit POST /api/attendance
   → Enregistre en base
   → Déclenche événement WebSocket : "attendance.validated"
   → Identifie les parents des élèves absents/en retard

3. Pour chaque élève absent/en retard :
   → Push notification Firebase → app_mobile (parent)
   ┌─────────────────────────────────────────────────────────┐
   │  🔔 Absence signalée — Junior Nguema                   │
   │                                                         │
   │  Votre enfant Junior a été marqué ABSENT               │
   │  Cours : Mathématiques — 3ème B                        │
   │  Heure : 08h00 — 23 Mai 2026                          │
   │                                                         │
   │  [📝 Justifier l'absence]  [📞 Appeler l'école]       │
   └─────────────────────────────────────────────────────────┘

4. Parent peut immédiatement justifier :
   → Motif (maladie, RDV médical, transport...)
   → Commentaire libre
   → Pièce jointe (certificat médical)
   → API enregistre justification
   → Enseignant et Admin voient la justification

5. Admin (appweb) voit en temps réel :
   → Dashboard : compteur "Absences aujourd'hui" mis à jour
   → /admin/communications → Justifications : nouvelle entrée
```


### 4.4 — Parent ↔ Enseignant : Messagerie directe

```
FLUX — Parent demande communication à un enseignant

1. Parent (app_mobile) → Messages → "Nouveau message"
   → Sélectionne enseignant (liste des enseignants de ses enfants)
   → Saisit objet + message
   → Envoie

2. API Laravel :
   → Crée conversation (table conversations)
   → Enregistre message (table messages)
   → Push notification → Enseignant

3. Enseignant (app_mobile) reçoit :
   ┌─────────────────────────────────────────────────────────┐
   │  💬 Message de M. Ewosso D-Gall                        │
   │  Parent de : Junior Nguema (3ème B)                    │
   │  "Bonjour M. Obiang, je souhaite discuter des..."      │
   │                                                         │
   │  [Répondre]  [Demander RDV]  [Ignorer]                 │
   └─────────────────────────────────────────────────────────┘

4. Enseignant répond → Parent reçoit notification
5. Admin (appweb) voit l'échange en lecture seule dans /admin/communications

FLUX — Enseignant demande communication à un parent

1. Enseignant (app_mobile) → Classes → Élève → "Contacter parent"
   → Saisit message
   → Envoie

2. Parent reçoit notification push
3. Échange bidirectionnel dans la messagerie
```

### 4.5 — Demande de RDV Parent ↔ Enseignant

```
FLUX — Demande de rendez-vous

1. Parent (app_mobile) → Messages → Enseignant → "Demander un RDV"
   → Propose 2-3 créneaux
   → Motif du RDV
   → Envoie

2. Enseignant reçoit notification
   → Accepte un créneau OU propose autre date
   → Confirmation automatique aux deux parties

3. Admin (appweb) voit la demande en lecture seule
   → Statut : En attente / Accepté / Refusé
   → Ne peut PAS intervenir (règle fondamentale)

4. Rappel automatique 24h avant le RDV :
   → Notification push aux deux parties
```


---

## PARTIE 5 — ARCHITECTURE API LARAVEL

---

### 5.1 — Structure du projet Laravel (api/)

```
api/                                    ← Projet Laravel 11
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   ├── AuthController.php          ← Login/Register/Logout
│   │   │   │   └── QrCodeController.php        ← Scan QR + liaison parent-enfant
│   │   │   ├── Admin/
│   │   │   │   ├── DashboardController.php     ← Stats globales
│   │   │   │   ├── SchoolController.php        ← CRUD établissements
│   │   │   │   ├── ClassController.php         ← CRUD classes
│   │   │   │   └── SignalementController.php   ← Signalements officiels
│   │   │   ├── Parent/
│   │   │   │   ├── ParentController.php        ← Profil + enfants liés
│   │   │   │   ├── ChildLinkController.php     ← Liaison parent-enfant
│   │   │   │   └── PaymentController.php       ← Paiements
│   │   │   ├── Teacher/
│   │   │   │   ├── TeacherController.php       ← Profil enseignant
│   │   │   │   ├── AttendanceController.php    ← Appel + présences
│   │   │   │   ├── TextbookController.php      ← Cahier de textes
│   │   │   │   └── HomeworkController.php      ← Devoirs
│   │   │   ├── Student/
│   │   │   │   └── StudentController.php       ← Profil élève
│   │   │   ├── Communication/
│   │   │   │   ├── MessageController.php       ← Messagerie directe
│   │   │   │   ├── AnnouncementController.php  ← Annonces admin
│   │   │   │   ├── AppointmentController.php   ← Demandes de RDV
│   │   │   │   └── JustificationController.php ← Justifications absence
│   │   │   └── Notification/
│   │   │       └── NotificationController.php  ← Push notifications
│   │   ├── Middleware/
│   │   │   ├── RoleMiddleware.php              ← Vérification rôle
│   │   │   └── SchoolMiddleware.php            ← Isolation multi-tenant
│   │   └── Requests/                           ← Form Requests (validation)
│   ├── Models/
│   │   ├── User.php
│   │   ├── School.php
│   │   ├── Student.php
│   │   ├── Parent.php (ParentModel)
│   │   ├── Teacher.php
│   │   ├── ClassRoom.php
│   │   ├── Attendance.php
│   │   ├── Textbook.php
│   │   ├── Homework.php
│   │   ├── Message.php
│   │   ├── Conversation.php
│   │   ├── Appointment.php
│   │   ├── Justification.php
│   │   ├── Notification.php
│   │   ├── Payment.php
│   │   ├── Signalement.php
│   │   └── QrCode.php
│   ├── Events/
│   │   ├── AttendanceValidated.php             ← WebSocket event
│   │   ├── MessageSent.php                     ← WebSocket event
│   │   ├── PaymentReceived.php                 ← WebSocket event
│   │   └── UnauthorizedQrScan.php              ← WebSocket event
│   ├── Listeners/
│   │   ├── SendAttendanceNotification.php
│   │   ├── SendMessageNotification.php
│   │   └── AlertAdminUnauthorizedScan.php
│   └── Services/
│       ├── QrCodeService.php                   ← Génération/validation QR
│       ├── PaymentService.php                  ← Intégration Airtel Money
│       ├── NotificationService.php             ← Firebase FCM
│       └── AttendanceService.php               ← Logique présences
├── routes/
│   └── api.php                                 ← Toutes les routes API
├── database/
│   └── migrations/                             ← Migrations (voir Partie 6)
└── config/
    ├── broadcasting.php                        ← Pusher/Laravel Echo
    └── services.php                            ← Firebase, Airtel Money
```


---

### 5.2 — Routes API complètes (routes/api.php)

```php
// ============================================================
// AUTH — Public (pas de token requis)
// ============================================================
POST   /api/auth/login                    → AuthController@login
POST   /api/auth/register                 → AuthController@register
POST   /api/auth/logout                   → AuthController@logout
POST   /api/auth/refresh                  → AuthController@refresh

// ============================================================
// QR CODE & LIAISON PARENT-ENFANT
// ============================================================
POST   /api/qrcode/scan                   → QrCodeController@scan
       // Body: { qr_token, parent_id }
       // Retourne: { linked: bool, student: {...}, alert_sent: bool }

POST   /api/qrcode/link-by-code           → QrCodeController@linkByCode
       // Body: { secret_code, school_name, parent_id }

GET    /api/qrcode/generate/{student_id}  → QrCodeController@generate
       // Admin only — génère QR Code pour un élève

// ============================================================
// ADMIN — Protégé (role: admin)
// ============================================================
GET    /api/admin/dashboard               → DashboardController@index
GET    /api/admin/schools                 → SchoolController@index
POST   /api/admin/schools                 → SchoolController@store
PUT    /api/admin/schools/{id}            → SchoolController@update

GET    /api/admin/classes                 → ClassController@index
POST   /api/admin/classes                 → ClassController@store
GET    /api/admin/classes/{id}/students   → ClassController@students
GET    /api/admin/classes/{id}/attendance → ClassController@attendance

GET    /api/admin/signalements            → SignalementController@index
POST   /api/admin/signalements/{id}/take  → SignalementController@takeCharge
POST   /api/admin/signalements/{id}/close → SignalementController@close
POST   /api/admin/signalements/{id}/archive → SignalementController@archive

// ============================================================
// PARENT — Protégé (role: parent)
// ============================================================
GET    /api/parent/profile                → ParentController@profile
GET    /api/parent/children               → ParentController@children
GET    /api/parent/children/{id}          → ParentController@childDetail
GET    /api/parent/children/{id}/attendance → ParentController@childAttendance
GET    /api/parent/children/{id}/textbook → ParentController@childTextbook
GET    /api/parent/children/{id}/homework → ParentController@childHomework

POST   /api/parent/link-child             → ChildLinkController@link
DELETE /api/parent/unlink-child/{id}      → ChildLinkController@unlink

GET    /api/parent/payments               → PaymentController@index
POST   /api/parent/payments               → PaymentController@pay
       // Body: { amount, method: 'airtel_money'|'bank'|'card', reference }
GET    /api/parent/payments/{id}          → PaymentController@show

// ============================================================
// ENSEIGNANT — Protégé (role: teacher)
// ============================================================
GET    /api/teacher/profile               → TeacherController@profile
GET    /api/teacher/classes               → TeacherController@classes
GET    /api/teacher/classes/{id}/students → TeacherController@students

POST   /api/teacher/attendance            → AttendanceController@store
       // Body: { class_id, date, students: [{id, status: present|absent|late}] }
GET    /api/teacher/attendance/{class_id} → AttendanceController@index

POST   /api/teacher/textbook              → TextbookController@store
GET    /api/teacher/textbook/{class_id}   → TextbookController@index

POST   /api/teacher/homework              → HomeworkController@store
GET    /api/teacher/homework/{class_id}   → HomeworkController@index

// ============================================================
// ÉLÈVE — Protégé (role: student)
// ============================================================
GET    /api/student/profile               → StudentController@profile
GET    /api/student/textbook              → StudentController@textbook
GET    /api/student/homework              → StudentController@homework
GET    /api/student/attendance            → StudentController@attendance

// ============================================================
// COMMUNICATION — Protégé (tous rôles authentifiés)
// ============================================================
GET    /api/messages                      → MessageController@index
POST   /api/messages                      → MessageController@store
GET    /api/messages/{conversation_id}    → MessageController@show
DELETE /api/messages/{id}                 → MessageController@destroy

GET    /api/announcements                 → AnnouncementController@index
POST   /api/announcements                 → AnnouncementController@store (admin)

GET    /api/appointments                  → AppointmentController@index
POST   /api/appointments                  → AppointmentController@store
PUT    /api/appointments/{id}/accept      → AppointmentController@accept
PUT    /api/appointments/{id}/refuse      → AppointmentController@refuse

POST   /api/justifications                → JustificationController@store
GET    /api/justifications                → JustificationController@index

// ============================================================
// NOTIFICATIONS — Protégé
// ============================================================
GET    /api/notifications                 → NotificationController@index
PUT    /api/notifications/{id}/read       → NotificationController@markRead
POST   /api/notifications/register-token  → NotificationController@registerFcmToken
       // Body: { fcm_token, device_type: 'android'|'ios' }

// ============================================================
// SIGNALEMENTS — Protégé (parent ou enseignant peut créer)
// ============================================================
POST   /api/signalements                  → SignalementController@store
GET    /api/signalements/mine             → SignalementController@mine
```


---

## PARTIE 6 — STRUCTURE BASE DE DONNÉES

---

### 6.1 — Schéma complet des tables

```sql
-- ============================================================
-- MULTI-TENANT : chaque établissement est isolé
-- ============================================================

-- Table : schools (établissements)
CREATE TABLE schools (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    slug            VARCHAR(255) UNIQUE NOT NULL,       -- ex: notre-dame-quaben
    address         TEXT,
    phone           VARCHAR(20),
    email           VARCHAR(255),
    logo_url        VARCHAR(500),
    subscription_plan ENUM('free','basic','premium') DEFAULT 'free',
    is_active       BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP
);

-- Table : users (tous les utilisateurs)
CREATE TABLE users (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id       BIGINT UNSIGNED NOT NULL,           -- isolation multi-tenant
    name            VARCHAR(255) NOT NULL,
    email           VARCHAR(255) UNIQUE NOT NULL,
    phone           VARCHAR(20),
    password        VARCHAR(255) NOT NULL,
    role            ENUM('admin','teacher','parent','student') NOT NULL,
    avatar_url      VARCHAR(500),
    fcm_token       VARCHAR(500),                       -- Firebase push token
    device_type     ENUM('android','ios','web'),
    is_active       BOOLEAN DEFAULT TRUE,
    email_verified_at TIMESTAMP NULL,
    last_login_at   TIMESTAMP NULL,
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id)
);

-- Table : students (élèves — profil étendu)
CREATE TABLE students (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NULL,               -- NULL si pas de compte
    school_id       BIGINT UNSIGNED NOT NULL,
    class_id        BIGINT UNSIGNED NOT NULL,
    matricule       VARCHAR(50) UNIQUE NOT NULL,        -- ex: SKL-2026-4521
    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100) NOT NULL,
    date_of_birth   DATE,
    gender          ENUM('M','F'),
    photo_url       VARCHAR(500),
    secret_code     VARCHAR(20) UNIQUE NOT NULL,        -- code secret liaison parent
    qr_token        VARCHAR(255) UNIQUE NOT NULL,       -- token QR Code (chiffré)
    qr_expires_at   TIMESTAMP NULL,
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id),
    FOREIGN KEY (class_id) REFERENCES class_rooms(id)
);

-- Table : class_rooms (classes)
CREATE TABLE class_rooms (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id       BIGINT UNSIGNED NOT NULL,
    name            VARCHAR(100) NOT NULL,              -- ex: 3ème B
    level           VARCHAR(50),                        -- ex: 3ème
    section         VARCHAR(10),                        -- ex: B
    academic_year   VARCHAR(10) NOT NULL,               -- ex: 2025-2026
    max_students    INT DEFAULT 40,
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id)
);

-- Table : parent_student (liaison parent-enfant)
CREATE TABLE parent_student (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id       BIGINT UNSIGNED NOT NULL,           -- users.id (role=parent)
    student_id      BIGINT UNSIGNED NOT NULL,
    relationship    ENUM('father','mother','guardian','other') DEFAULT 'guardian',
    is_primary      BOOLEAN DEFAULT FALSE,              -- parent principal
    linked_at       TIMESTAMP,
    linked_by       BIGINT UNSIGNED NULL,               -- admin qui a validé
    created_at      TIMESTAMP,
    UNIQUE KEY unique_parent_student (parent_id, student_id),
    FOREIGN KEY (parent_id) REFERENCES users(id),
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- Table : qr_scan_attempts (tentatives de scan — sécurité)
CREATE TABLE qr_scan_attempts (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id      BIGINT UNSIGNED NOT NULL,
    scanned_by_user_id BIGINT UNSIGNED NULL,            -- NULL si inconnu
    scanned_by_email VARCHAR(255) NULL,
    ip_address      VARCHAR(45),
    device_info     VARCHAR(255),
    is_authorized   BOOLEAN NOT NULL,                   -- TRUE si parent lié
    alert_sent      BOOLEAN DEFAULT FALSE,
    scanned_at      TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id)
);
```


```sql
-- Table : teacher_class (enseignant ↔ classe ↔ matière)
CREATE TABLE teacher_class (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id      BIGINT UNSIGNED NOT NULL,           -- users.id (role=teacher)
    class_id        BIGINT UNSIGNED NOT NULL,
    subject         VARCHAR(100) NOT NULL,              -- ex: Mathématiques
    academic_year   VARCHAR(10) NOT NULL,
    created_at      TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id),
    FOREIGN KEY (class_id) REFERENCES class_rooms(id)
);

-- Table : attendances (présences)
CREATE TABLE attendances (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id      BIGINT UNSIGNED NOT NULL,
    class_id        BIGINT UNSIGNED NOT NULL,
    teacher_id      BIGINT UNSIGNED NOT NULL,
    date            DATE NOT NULL,
    session         ENUM('morning','afternoon') DEFAULT 'morning',
    status          ENUM('present','absent','late') NOT NULL,
    marked_at       TIMESTAMP,
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP,
    UNIQUE KEY unique_attendance (student_id, class_id, date, session),
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (class_id) REFERENCES class_rooms(id),
    FOREIGN KEY (teacher_id) REFERENCES users(id)
);

-- Table : justifications (justifications d'absence)
CREATE TABLE justifications (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attendance_id   BIGINT UNSIGNED NOT NULL,
    parent_id       BIGINT UNSIGNED NOT NULL,
    reason          ENUM('illness','medical_appointment','transport','family','other'),
    comment         TEXT,
    attachment_url  VARCHAR(500),
    status          ENUM('pending','validated','rejected') DEFAULT 'pending',
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP,
    FOREIGN KEY (attendance_id) REFERENCES attendances(id),
    FOREIGN KEY (parent_id) REFERENCES users(id)
);

-- Table : textbooks (cahier de textes)
CREATE TABLE textbooks (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id      BIGINT UNSIGNED NOT NULL,
    class_id        BIGINT UNSIGNED NOT NULL,
    subject         VARCHAR(100) NOT NULL,
    date            DATE NOT NULL,
    content         TEXT NOT NULL,                      -- résumé du cours
    attachment_url  VARCHAR(500),
    external_link   VARCHAR(500),
    published_at    TIMESTAMP NULL,
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id),
    FOREIGN KEY (class_id) REFERENCES class_rooms(id)
);

-- Table : homeworks (devoirs)
CREATE TABLE homeworks (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    textbook_id     BIGINT UNSIGNED NULL,               -- lié à un cours
    teacher_id      BIGINT UNSIGNED NOT NULL,
    class_id        BIGINT UNSIGNED NOT NULL,
    subject         VARCHAR(100) NOT NULL,
    description     TEXT NOT NULL,
    due_date        DATE NOT NULL,
    estimated_time  ENUM('15min','30min','45min','1h+'),
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id),
    FOREIGN KEY (class_id) REFERENCES class_rooms(id)
);

-- Table : conversations (fils de messagerie)
CREATE TABLE conversations (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id       BIGINT UNSIGNED NOT NULL,
    type            ENUM('direct','group','announcement') DEFAULT 'direct',
    subject         VARCHAR(255),
    created_by      BIGINT UNSIGNED NOT NULL,
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Table : conversation_participants
CREATE TABLE conversation_participants (
    conversation_id BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,
    joined_at       TIMESTAMP,
    last_read_at    TIMESTAMP NULL,
    PRIMARY KEY (conversation_id, user_id),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Table : messages
CREATE TABLE messages (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    sender_id       BIGINT UNSIGNED NOT NULL,
    body            TEXT NOT NULL,
    attachment_url  VARCHAR(500),
    type            ENUM('text','image','document','payment_reminder','convocation') DEFAULT 'text',
    metadata        JSON NULL,                          -- données extra (montant, date RDV...)
    read_at         TIMESTAMP NULL,
    created_at      TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id),
    FOREIGN KEY (sender_id) REFERENCES users(id)
);
```


```sql
-- Table : appointments (demandes de RDV)
CREATE TABLE appointments (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requester_id    BIGINT UNSIGNED NOT NULL,           -- parent ou enseignant
    recipient_id    BIGINT UNSIGNED NOT NULL,           -- enseignant ou parent
    student_id      BIGINT UNSIGNED NULL,               -- enfant concerné
    reason          TEXT NOT NULL,
    proposed_slots  JSON NOT NULL,                      -- [{date, time}, ...]
    confirmed_slot  JSON NULL,                          -- créneau retenu
    status          ENUM('pending','accepted','refused','cancelled') DEFAULT 'pending',
    response_note   TEXT NULL,
    reminder_sent   BOOLEAN DEFAULT FALSE,
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES users(id),
    FOREIGN KEY (recipient_id) REFERENCES users(id)
);

-- Table : payments (paiements)
CREATE TABLE payments (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id       BIGINT UNSIGNED NOT NULL,
    parent_id       BIGINT UNSIGNED NOT NULL,
    student_id      BIGINT UNSIGNED NOT NULL,
    amount          DECIMAL(10,2) NOT NULL,
    currency        VARCHAR(10) DEFAULT 'FCFA',
    reason          VARCHAR(255) NOT NULL,              -- ex: Frais scolarité T2
    method          ENUM('airtel_money','bank_transfer','card','cash') NOT NULL,
    status          ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
    transaction_ref VARCHAR(255) UNIQUE,                -- référence opérateur
    paid_at         TIMESTAMP NULL,
    due_date        DATE NULL,
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id),
    FOREIGN KEY (parent_id) REFERENCES users(id),
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- Table : signalements (signalements officiels)
CREATE TABLE signalements (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id       BIGINT UNSIGNED NOT NULL,
    reporter_id     BIGINT UNSIGNED NOT NULL,           -- parent ou enseignant
    student_id      BIGINT UNSIGNED NULL,
    type            ENUM('behavior','harassment','safety','payment','other'),
    description     TEXT NOT NULL,
    attachment_url  VARCHAR(500),
    status          ENUM('new','in_progress','closed','archived') DEFAULT 'new',
    handled_by      BIGINT UNSIGNED NULL,               -- admin qui prend en charge
    handled_at      TIMESTAMP NULL,
    admin_note      TEXT NULL,
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id),
    FOREIGN KEY (reporter_id) REFERENCES users(id)
);

-- Table : notifications (notifications in-app)
CREATE TABLE notifications (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    type            VARCHAR(100) NOT NULL,              -- ex: attendance.absent
    title           VARCHAR(255) NOT NULL,
    body            TEXT NOT NULL,
    data            JSON NULL,                          -- payload extra
    read_at         TIMESTAMP NULL,
    sent_via_push   BOOLEAN DEFAULT FALSE,
    created_at      TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Table : announcements (annonces admin → tous)
CREATE TABLE announcements (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id       BIGINT UNSIGNED NOT NULL,
    author_id       BIGINT UNSIGNED NOT NULL,
    title           VARCHAR(255) NOT NULL,
    body            TEXT NOT NULL,
    target_role     ENUM('all','parents','teachers','students') DEFAULT 'all',
    target_class_id BIGINT UNSIGNED NULL,               -- NULL = toute l'école
    published_at    TIMESTAMP NULL,
    expires_at      TIMESTAMP NULL,
    created_at      TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id),
    FOREIGN KEY (author_id) REFERENCES users(id)
);

-- Table : agent_boxes (accès mobile par agent/enseignant)
CREATE TABLE agent_boxes (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id      BIGINT UNSIGNED NOT NULL,
    box_type        ENUM('emploi_du_temps','concours_info','planning',
                         'qualite_securite','social_marketing','website'),
    is_enabled      BOOLEAN DEFAULT FALSE,
    updated_at      TIMESTAMP,
    UNIQUE KEY unique_agent_box (teacher_id, box_type),
    FOREIGN KEY (teacher_id) REFERENCES users(id)
);
```


---

### 6.2 — Diagramme des relations (ERD simplifié)

```
schools ──────────────────────────────────────────────────────────────┐
   │                                                                   │
   ├── users (admin, teacher, parent, student)                        │
   │      │                                                            │
   │      ├── [role=teacher] ──── teacher_class ──── class_rooms ─────┤
   │      │                              │                             │
   │      │                              └── attendances               │
   │      │                                       │                    │
   │      ├── [role=parent] ──── parent_student ──┤                   │
   │      │                              │         │                   │
   │      │                              │    students ───────────────┤
   │      │                              │         │                   │
   │      │                              └── justifications            │
   │      │                                                            │
   │      ├── conversations ──── messages                              │
   │      │         │                                                  │
   │      │         └── conversation_participants                      │
   │      │                                                            │
   │      ├── appointments                                             │
   │      ├── payments                                                 │
   │      ├── signalements                                             │
   │      ├── notifications                                            │
   │      └── agent_boxes                                              │
   │                                                                   │
   ├── class_rooms ──── textbooks ──── homeworks                      │
   │                                                                   │
   └── announcements                                                   │
                                                                       │
qr_scan_attempts ──── students ────────────────────────────────────────┘
```

---

## PARTIE 7 — ARCHITECTURE TEMPS RÉEL (WebSocket)

---

### 7.1 — Configuration Laravel Echo + Pusher

```php
// config/broadcasting.php
'pusher' => [
    'driver' => 'pusher',
    'key'    => env('PUSHER_APP_KEY'),
    'secret' => env('PUSHER_APP_SECRET'),
    'app_id' => env('PUSHER_APP_ID'),
    'options' => [
        'cluster' => env('PUSHER_APP_CLUSTER', 'eu'),
        'encrypted' => true,
    ],
],
```

### 7.2 — Canaux WebSocket par fonctionnalité

```
Canal : school.{school_id}
  → Événements admin : nouvelles absences, signalements, paiements

Canal : parent.{user_id}
  → Événements parent : absence enfant, message reçu, rappel paiement

Canal : teacher.{user_id}
  → Événements enseignant : message reçu, demande RDV

Canal : class.{class_id}
  → Événements classe : appel validé (broadcast à tous les parents de la classe)
```

### 7.3 — Événements WebSocket

```php
// AttendanceValidated → broadcast sur canal class.{class_id}
// Payload : { class_id, date, absents: [{student_id, student_name, parent_id}] }

// MessageSent → broadcast sur canal parent.{id} ou teacher.{id}
// Payload : { conversation_id, sender_name, preview, type }

// PaymentReminder → broadcast sur canal parent.{id}
// Payload : { amount, currency, reason, due_date, student_name }

// UnauthorizedQrScan → broadcast sur canal school.{school_id}
// Payload : { student_id, student_name, scanned_by_email, ip, timestamp }
```


---

## PARTIE 8 — STRATÉGIE DE CONSOLIDATION (PLAN D'ATTAQUE)

---

### 8.1 — Phases de développement

```
PHASE 0 — Fondations API (2 semaines)
  ├── Créer projet Laravel 11 dans api/
  ├── Configurer JWT (tymon/jwt-auth ou Laravel Sanctum)
  ├── Migrations : schools, users, students, class_rooms
  ├── Seeders de démo (1 école, 3 classes, 10 élèves, 5 parents)
  ├── Routes auth : login / register / logout / refresh
  └── Tests Postman de base

PHASE 1 — Connexion appweb ↔ API (1 semaine)
  ├── Connecter authService.js de appweb à POST /api/auth/login
  ├── Configurer VITE_API_URL dans .env
  ├── Tester login admin → JWT → dashboard
  ├── Connecter AdminDashboard aux vraies stats API
  └── Connecter ClassesPage aux vraies données

PHASE 2 — QR Code & Liaison Parent-Enfant (1 semaine)
  ├── API : QrCodeController (generate + scan + linkByCode)
  ├── app_mobile : ajouter mobile_scanner (pubspec.yaml)
  ├── app_mobile : créer QrScanPage dans features/auth/
  ├── app_mobile : créer LinkChildPage (QR + code secret)
  ├── Scénario parent identifié → liaison confirmée
  └── Scénario parent inconnu → alerte admin (appweb)

PHASE 3 — Présences temps réel (1 semaine)
  ├── API : AttendanceController + événement AttendanceValidated
  ├── Configurer Pusher + Laravel Echo
  ├── app_mobile (teacher) : connecter AttendanceView à POST /api/teacher/attendance
  ├── app_mobile (parent) : recevoir notification push absence
  ├── app_mobile (parent) : CalendarPage connectée à GET /api/parent/children/{id}/attendance
  └── appweb : dashboard mis à jour en temps réel (compteur absences)

PHASE 4 — Messagerie & Communications (2 semaines)
  ├── API : MessageController + ConversationController
  ├── app_mobile (parent) : connecter MessagesPage à l'API
  ├── app_mobile (teacher) : connecter TeacherMessagesPage à l'API
  ├── Notifications push (Firebase FCM) pour nouveaux messages
  ├── appweb : CommunicationsPage connectée (lecture seule)
  └── Demandes de RDV : AppointmentController + notifications

PHASE 5 — Paiements (1 semaine)
  ├── API : PaymentController + intégration Airtel Money sandbox
  ├── app_mobile (parent) : créer PaymentPage dans features/parent/
  ├── Rappel paiement : admin envoie → parent reçoit notification
  ├── Parent effectue paiement → confirmation → admin voit
  └── Historique paiements dans profil parent

PHASE 6 — Signalements & Convocations (3 jours)
  ├── API : SignalementController complet
  ├── app_mobile : créer SignalementPage (parent + enseignant)
  ├── appweb : SignalementsPage connectée à l'API
  └── Convocations : type de message spécial avec confirmation

PHASE 7 — Polissage & Tests (1 semaine)
  ├── Gestion d'état globale app_mobile (Riverpod)
  ├── Tests d'intégration API (Postman + Laravel tests)
  ├── Tests Flutter (widget tests + integration tests)
  ├── Correction des doublons de pages app_mobile
  ├── Cohérence PWA appweb (corriger nom "iSanté" → "Skooly")
  └── Documentation API (Swagger/OpenAPI)
```

---

### 8.2 — Dépendances à ajouter

#### app_mobile (pubspec.yaml)
```yaml
dependencies:
  flutter:
    sdk: flutter
  cupertino_icons: ^1.0.8

  # Gestion d'état
  flutter_riverpod: ^2.5.1

  # HTTP
  dio: ^5.4.3+1

  # Auth & stockage
  flutter_secure_storage: ^9.0.0
  shared_preferences: ^2.2.3

  # QR Code
  mobile_scanner: ^5.2.3

  # Notifications push
  firebase_core: ^3.3.0
  firebase_messaging: ^15.1.0
  flutter_local_notifications: ^17.2.2

  # WebSocket temps réel
  pusher_channels_flutter: ^2.0.1

  # Paiement
  flutter_inappwebview: ^6.0.0   # pour Airtel Money web flow

  # Utilitaires
  intl: ^0.19.0
  cached_network_image: ^3.3.1
  image_picker: ^1.1.2
  file_picker: ^8.0.7
```

#### appweb_skooly (package.json — à ajouter)
```json
{
  "laravel-echo": "^1.16.1",
  "pusher-js": "^8.4.0",
  "qrcode": "^1.5.4",
  "react-qr-code": "^2.0.15"
}
```

#### API Laravel (composer.json — à ajouter)
```json
{
  "tymon/jwt-auth": "^2.1",
  "pusher/pusher-php-server": "^7.2",
  "kreait/laravel-firebase": "^5.7",
  "simplesoftwareio/simple-qrcode": "^4.2",
  "spatie/laravel-permission": "^6.4"
}
```


---

## PARTIE 9 — RÉAJUSTEMENTS PRIORITAIRES DANS appweb_skooly

---

### 9.1 — Ce qui doit être fait MAINTENANT dans appweb

```
PRIORITÉ 1 — Compléter le plan PLAN_APPWEB.md existant
  ├── ClassesPage.jsx : ajouter onglet "Présences & Absences" (calendrier mensuel)
  ├── AdminDashboard.jsx : ajouter compteurs Absences + Signalements cliquables
  ├── Sidebar.jsx : ajouter lien "Messagerie" → /admin/messages
  └── AdminRoutes.jsx : ajouter route /admin/messages → MessagesPage

PRIORITÉ 2 — Nouveau module QR Code (côté admin)
  ├── Créer : src/features/featuresAdmin/components/QrCodeModal.jsx
  │     → Affiche QR Code d'un élève
  │     → Bouton "Imprimer" + "Envoyer par email"
  │     → Affiche aussi le code secret texte
  ├── Intégrer dans ClassesPage → vue élève → bouton "QR Code"
  └── Créer : src/features/featuresAdmin/components/UnauthorizedScanAlert.jsx
        → Notification temps réel quand parent inconnu scanne

PRIORITÉ 3 — Module Paiements (côté admin)
  ├── Créer : src/features/featuresAdmin/PaymentsPage.jsx
  │     → Liste des paiements reçus
  │     → Filtres : classe / élève / statut / période
  │     → Bouton "Envoyer rappel paiement" → ouvre MessagesPage ciblé
  └── Ajouter dans Sidebar : "Paiements" → /admin/payments

PRIORITÉ 4 — Notifications temps réel (appweb)
  ├── Installer laravel-echo + pusher-js
  ├── Créer : src/hooks/useRealTimeNotifications.js
  │     → Écoute canal school.{school_id}
  │     → Met à jour compteurs dashboard en temps réel
  └── Intégrer dans DropdownNotifications.jsx
```

### 9.2 — Corrections techniques appweb

```
CORRECTIONS IMMÉDIATES :
  ├── Corriger nom PWA : "iSanté" → "Skooly" dans vite.config.js + manifest
  ├── Implémenter hasPermission() dans useAuth.jsx (retourne toujours true)
  ├── Supprimer services_backup/ (dossier vide)
  ├── Connecter authService à l'API réelle (authService.example.js → authService.js)
  └── Ajouter VITE_API_URL dans .env.example avec valeur par défaut
```

---

## PARTIE 10 — RÉAJUSTEMENTS PRIORITAIRES DANS app_mobile

---

### 10.1 — Ce qui doit être fait MAINTENANT dans app_mobile

```
PRIORITÉ 1 — Gestion d'état globale (Riverpod)
  ├── Remplacer AppStore vide par providers Riverpod
  ├── Créer : lib/app/providers/
  │     ├── auth_provider.dart      → StateNotifierProvider<AuthState>
  │     ├── parent_provider.dart    → FutureProvider<List<Child>>
  │     ├── teacher_provider.dart   → StateNotifierProvider<TeacherState>
  │     └── notification_provider.dart → StreamProvider<List<Notification>>
  └── Injecter ProviderScope dans main.dart

PRIORITÉ 2 — Couche HTTP (Dio)
  ├── Créer : lib/shared/config/api_client.dart
  │     → Instance Dio centralisée
  │     → Intercepteur : ajout Bearer token
  │     → Intercepteur : gestion 401 → logout
  │     → Base URL depuis const ou env
  ├── Créer : lib/shared/config/api_endpoints.dart
  │     → Toutes les constantes d'URL
  └── Remplacer AuthService simulé par appels réels

PRIORITÉ 3 — QR Code Scanner
  ├── Ajouter mobile_scanner dans pubspec.yaml
  ├── Créer : lib/features/auth/pages/qr_scan_page.dart
  │     → Scanner QR Code avec mobile_scanner
  │     → Appel API POST /api/qrcode/scan
  │     → Afficher résultat (lié / non autorisé)
  └── Créer : lib/features/auth/pages/link_child_page.dart
        → Formulaire code secret + établissement
        → Appel API POST /api/qrcode/link-by-code

PRIORITÉ 4 — Notifications push (Firebase)
  ├── Configurer Firebase project (google-services.json)
  ├── Implémenter NotificationsService réel
  ├── Enregistrer FCM token → POST /api/notifications/register-token
  └── Gérer notifications en foreground + background

PRIORITÉ 5 — Nettoyage doublons
  ├── Supprimer : student_home.dart (doublon de student_dashboard_page.dart)
  ├── Supprimer : attendance.dart (doublon de attendance_view.dart)
  └── Supprimer : parent_home.dart (doublon de parent_home_page.dart)
```

---

## PARTIE 11 — SCÉNARIO DE DÉMO COMPLET (10 minutes)

---

```
DÉMO COMPLÈTE — Skooly Platform

1. [appweb] Page d'accueil (/)
   → Présenter Skooly, landing page

2. [appweb] Connexion admin → /admin/dashboard
   → Stats : 48 enseignants, 15 classes, 342 parents
   → Compteurs : 7 absences aujourd'hui, 2 signalements

3. [app_mobile] Connexion enseignant → M. Obiang
   → Dashboard enseignant → Classes → 3ème B → Appel
   → Marquer Junior absent → Valider l'appel

4. [app_mobile] Notification reçue côté parent (M. Ewosso)
   → "Junior a été marqué ABSENT — Mathématiques 08h00"
   → Parent clique "Justifier" → Motif : Maladie → Envoie

5. [appweb] Admin voit en temps réel
   → Compteur absences passe de 7 à 8
   → /admin/communications → Justifications → nouvelle entrée

6. [app_mobile] Scénario QR Code — parent inconnu
   → Nouveau compte parent scanne QR Code de Junior
   → Message : "Vous n'êtes pas identifié comme parent"
   → [appweb] Admin reçoit alerte : "Tentative non autorisée"

7. [appweb] Admin envoie rappel paiement
   → /admin/messages → Ciblés → M. Ewosso → Rappel paiement
   → Montant : 75 000 FCFA — Échéance : 30 Mai

8. [app_mobile] Parent reçoit notification paiement
   → Clique "Payer maintenant" → Airtel Money → Confirme
   → [appweb] Admin voit paiement validé

9. [app_mobile] Parent envoie message à M. Obiang
   → "Bonjour, je voudrais discuter des résultats de Junior"
   → Enseignant reçoit notification → Répond
   → [appweb] Admin voit l'échange en lecture seule

10. [appweb] Admin gère signalement
    → /admin/signalements → Signalement de M. Obiang
    → Clique "Prendre en charge" → Ajoute note → Clôture
```

---

## PARTIE 12 — RÉCAPITULATIF DES FICHIERS À CRÉER/MODIFIER

---

### appweb_skooly — Fichiers à créer

```
src/features/featuresAdmin/
  ├── PaymentsPage.jsx                    ← NOUVEAU
  ├── components/
  │   ├── QrCodeModal.jsx                 ← NOUVEAU
  │   ├── UnauthorizedScanAlert.jsx       ← NOUVEAU
  │   └── PaymentReminderModal.jsx        ← NOUVEAU

src/hooks/
  └── useRealTimeNotifications.js         ← NOUVEAU

src/features/featuresAuth/services/
  └── authService.js                      ← RENOMMER depuis .example.js
```

### appweb_skooly — Fichiers à modifier

```
src/partials/Sidebar.jsx                  ← Ajouter lien Messagerie + Paiements
src/components/AdminRoutes.jsx            ← Ajouter routes /messages + /payments
src/features/featuresAdmin/ClassesPage.jsx ← Ajouter onglet Présences
src/features/featuresAdmin/components/AdminDashboard.jsx ← 2 compteurs cliquables
src/config/constants.js                   ← Ajouter types notifications + paiements
vite.config.js                            ← Corriger nom PWA "iSanté" → "Skooly"
```

### app_mobile — Fichiers à créer

```
lib/app/providers/
  ├── auth_provider.dart
  ├── parent_provider.dart
  ├── teacher_provider.dart
  └── notification_provider.dart

lib/shared/config/
  ├── api_client.dart
  └── api_endpoints.dart

lib/features/auth/pages/
  ├── qr_scan_page.dart
  └── link_child_page.dart

lib/features/parent/pages/
  └── payment_page.dart

lib/features/notifications/services/
  └── fcm_service.dart                    ← Remplace notifications_service.dart
```

### API Laravel — Fichiers à créer (projet complet)

```
api/                                      ← Nouveau projet Laravel 11
  ├── app/Http/Controllers/...            ← Voir Partie 5.1
  ├── app/Models/...                      ← Voir Partie 6.1
  ├── app/Events/...                      ← WebSocket events
  ├── app/Services/...                    ← QrCode, Payment, Notification
  ├── routes/api.php                      ← Voir Partie 5.2
  └── database/migrations/...            ← Voir Partie 6.1
```

---

> **Document généré le 23 Mai 2026**
> **Version : 1.0 — Plan Ultra Skooly Platform**
> **Prochaine étape : Phase 0 — Fondations API Laravel**
