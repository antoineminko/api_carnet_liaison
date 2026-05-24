# PLAN SCÉNARIO 1 — Démo Connexion Parent ↔ Enfant
## Version simplifiée — Focus démonstration

> Objectif : créer un flux complet de démo entre appweb_skooly (admin)
> et app_mobile (parent) autour de la liaison parent-enfant.
> Pas de sécurité complexe — juste une démo fonctionnelle.

---

## CE QU'ON VEUT DÉMONTRER

```
CÔTÉ appweb_skooly (admin) :
  1. Créer une école
  2. Créer une classe
  3. Créer des matières et les attribuer à la classe
  4. Créer des enseignants et les affecter à des matières
  5. Créer un élève → lui attribuer une classe → il hérite des matières
  6. Créer des parents et les lier à l'élève
  7. L'élève reçoit automatiquement un QR Code + code secret

CÔTÉ app_mobile (parent) :
  1. Login parent (email ou téléphone)
  2. Espace "Mes enfants" → vide au départ
  3. Scanner QR Code OU saisir code secret + sélectionner école
  4. L'enfant apparaît dans son espace
```

---

## PARTIE 1 — BASE DE DONNÉES (carnet.sql)

### Tables nécessaires et leurs relations

```
ecoles
  └── classes (une école a plusieurs classes)
        └── eleves (un élève appartient à une classe)
              └── eleve_parents (un élève a plusieurs parents)
        └── classe_matieres (une classe a plusieurs matières)
              └── matieres (une matière a un enseignant)
                    └── enseignants
parents
  └── eleve_parents (un parent peut avoir plusieurs enfants)
```

---

## PARTIE 2 — CE QUI DOIT ÊTRE FAIT DANS appweb_skooly/

### 2.1 — Structure des pages à créer

```
/admin/setup          → Page de configuration initiale (wizard)
/admin/ecole          → Gérer l'école (nom, logo, infos)
/admin/classes        → ✅ EXISTE — enrichir
/admin/matieres       → NOUVEAU — gérer les matières
/admin/enseignants    → ✅ EXISTE (AgentManagementPage) — adapter
/admin/eleves         → NOUVEAU — gérer les élèves
/admin/parents        → NOUVEAU — gérer les parents
```

---

### 2.2 — Page Matières (NOUVELLE)

**Fichier à créer :** `src/features/featuresAdmin/MatieresPage.jsx`

```
Contenu :
┌─────────────────────────────────────────────────────────────┐
│  📚 Gestion des Matières                                    │
│                                                             │
│  [+ Ajouter une matière]                                    │
│                                                             │
│  Matière          Enseignant assigné    Classe(s)   Actions │
│  ─────────────────────────────────────────────────────────  │
│  Mathématiques    M. Obiang             3ème B      ✏️ 🗑️  │
│  Français         Mme Nze               3ème B      ✏️ 🗑️  │
│  Histoire-Géo     M. Koumba             3ème B      ✏️ 🗑️  │
└─────────────────────────────────────────────────────────────┘

Formulaire d'ajout :
  - Nom de la matière (ex: Mathématiques)
  - Enseignant responsable (dropdown → liste enseignants)
  - Classe(s) concernée(s) (multi-select)
```

---

### 2.3 — Page Élèves (NOUVELLE)

**Fichier à créer :** `src/features/featuresAdmin/ElevesPage.jsx`

```
Contenu :
┌─────────────────────────────────────────────────────────────┐
│  👨‍🎓 Gestion des Élèves                                     │
│                                                             │
│  [+ Ajouter un élève]    Filtre par classe: [Toutes ▼]     │
│                                                             │
│  Photo  Nom           Classe   Code Secret  Parents  Actions│
│  ─────────────────────────────────────────────────────────  │
│  📷     Junior Nguema  3ème B   SKL-001      2 liés   ✏️ 👁️│
│  📷     Alice Ndong    3ème B   SKL-002      0 liés   ✏️ 👁️│
└─────────────────────────────────────────────────────────────┘

Formulaire de création d'un élève :
  ┌─────────────────────────────────────────────────────────┐
  │  Prénom *          [              ]                      │
  │  Nom *             [              ]                      │
  │  Photo             [Choisir fichier]                     │
  │  Classe *          [3ème B      ▼]                       │
  │  Date de naissance [  /  /      ]                        │
  │                                                          │
  │  → Matières assignées automatiquement selon la classe    │
  │  → Code secret généré automatiquement : SKL-2026-XXXX   │
  │  → QR Code généré automatiquement                        │
  │                                                          │
  │  [Créer l'élève]                                         │
  └─────────────────────────────────────────────────────────┘

Vue détaillée d'un élève (clic sur 👁️) :
  ┌─────────────────────────────────────────────────────────┐
  │  [Photo]  Junior Nguema — 3ème B                        │
  │                                                         │
  │  📚 Matières de sa classe :                             │
  │     • Mathématiques → M. Obiang                         │
  │     • Français → Mme Nze                                │
  │     • Histoire-Géo → M. Koumba                          │
  │                                                         │
  │  🔑 Code secret : SKL-2026-4521                         │
  │  📷 QR Code : [image QR]  [Imprimer]                    │
  │                                                         │
  │  👨‍👩‍👧 Parents liés :                                      │
  │     • M. Ewosso D-Gall (père) — lié le 20/05/2026      │
  │     • Mme Ewosso (mère) — lié le 20/05/2026            │
  │     [+ Lier un parent]                                  │
  └─────────────────────────────────────────────────────────┘
```

---

### 2.4 — Page Parents (NOUVELLE)

**Fichier à créer :** `src/features/featuresAdmin/ParentsPage.jsx`

```
Contenu :
┌─────────────────────────────────────────────────────────────┐
│  👨‍👩‍👧 Gestion des Parents                                    │
│                                                             │
│  [+ Ajouter un parent]                                      │
│                                                             │
│  Nom              Téléphone      Email           Enfants    │
│  ─────────────────────────────────────────────────────────  │
│  M. Ewosso D-Gall +241 06 11 22  ewosso@mail.com  1 enfant │
│  Mme Mintsa       +241 07 33 44  mintsa@mail.com  1 enfant │
└─────────────────────────────────────────────────────────────┘

Formulaire de création d'un parent :
  ┌─────────────────────────────────────────────────────────┐
  │  Prénom *          [              ]                      │
  │  Nom *             [              ]                      │
  │  Téléphone *       [+241          ]  ← login mobile     │
  │  Email             [              ]  ← login alternatif │
  │  Mot de passe      [auto-généré   ]  ← affiché 1 fois   │
  │                                                          │
  │  Lier à un élève : [Rechercher élève...  ▼]             │
  │  Lien de parenté : [Père ▼]                             │
  │                                                          │
  │  [Créer le parent]                                       │
  └─────────────────────────────────────────────────────────┘

NOTE : Le parent peut aussi se lier lui-même depuis app_mobile
       via QR Code ou code secret (sans passer par l'admin)
```

---

### 2.5 — Enrichissement ClassesPage.jsx (EXISTANT)

```
AJOUTER dans la vue détaillée d'une classe :

Onglet existant [Élèves & Parents] → afficher la liste des élèves
                                      avec leur statut de liaison parent

AJOUTER onglet [Matières & Enseignants] :
  ┌─────────────────────────────────────────────────────────┐
  │  Matières de la 3ème B                                  │
  │                                                         │
  │  Matière          Enseignant         Heures/semaine     │
  │  ─────────────────────────────────────────────────────  │
  │  Mathématiques    M. Obiang           4h                │
  │  Français         Mme Nze             4h                │
  │  Histoire-Géo     M. Koumba           3h                │
  │  [+ Ajouter une matière à cette classe]                 │
  └─────────────────────────────────────────────────────────┘
```

---

### 2.6 — Sidebar.jsx — Liens à ajouter

```
SIDEBAR FINALE appweb_skooly :

├── 📊  Tableau de bord          /admin/dashboard
├── 🏫  Gestion Classes          /admin/classes
├── 👨‍🎓  Élèves                   /admin/eleves       ← NOUVEAU
├── 👨‍👩‍👧  Parents                  /admin/parents      ← NOUVEAU
├── 📚  Matières                 /admin/matieres     ← NOUVEAU
├── 👨‍🏫  Enseignants              /admin/enseignants  (existant)
├── 💬  Communication            /admin/communications
├── 🚨  Signalements             /admin/signalements
└── 📢  Messagerie               /admin/messages
```

---

### 2.7 — AdminRoutes.jsx — Routes à ajouter

```jsx
// AJOUTER ces 3 routes :
<Route path="eleves"      element={<ElevesPage />} />
<Route path="parents"     element={<ParentsPage />} />
<Route path="matieres"    element={<MatieresPage />} />
```

---

## PARTIE 3 — CE QUI DOIT ÊTRE FAIT DANS api/ (Laravel — démo simple)

### 3.1 — Contrôleurs à créer

```
app/Http/Controllers/
  ├── EleveController.php      → CRUD élèves
  ├── ParentController.php     → CRUD parents + liaison élève
  ├── MatiereController.php    → CRUD matières
  ├── ClasseController.php     → CRUD classes (enrichi)
  ├── EnseignantController.php → CRUD enseignants
  └── QrCodeController.php     → scan + liaison parent-enfant
```

---

### 3.2 — Routes API (routes/api.php) — version démo

```php
// ÉLÈVES
Route::get('/eleves',              [EleveController::class, 'index']);
Route::post('/eleves',             [EleveController::class, 'store']);
Route::get('/eleves/{id}',         [EleveController::class, 'show']);
Route::put('/eleves/{id}',         [EleveController::class, 'update']);
Route::delete('/eleves/{id}',      [EleveController::class, 'destroy']);

// PARENTS
Route::get('/parents',             [ParentController::class, 'index']);
Route::post('/parents',            [ParentController::class, 'store']);
Route::get('/parents/{id}',        [ParentController::class, 'show']);
Route::post('/parents/{id}/lier-eleve', [ParentController::class, 'lierEleve']);

// MATIÈRES
Route::get('/matieres',            [MatiereController::class, 'index']);
Route::post('/matieres',           [MatiereController::class, 'store']);
Route::put('/matieres/{id}',       [MatiereController::class, 'update']);
Route::delete('/matieres/{id}',    [MatiereController::class, 'destroy']);

// CLASSES
Route::get('/classes',             [ClasseController::class, 'index']);
Route::post('/classes',            [ClasseController::class, 'store']);
Route::get('/classes/{id}/eleves', [ClasseController::class, 'eleves']);
Route::get('/classes/{id}/matieres', [ClasseController::class, 'matieres']);

// ENSEIGNANTS
Route::get('/enseignants',         [EnseignantController::class, 'index']);
Route::post('/enseignants',        [EnseignantController::class, 'store']);

// AUTH PARENT (app_mobile)
Route::post('/auth/parent/login',  [ParentController::class, 'login']);

// QR CODE (app_mobile)
Route::post('/qrcode/scan',        [QrCodeController::class, 'scan']);
Route::post('/qrcode/code-secret', [QrCodeController::class, 'parCodeSecret']);
```

---

### 3.3 — Logique QrCodeController (version démo simple)

```php
// scan() — reçoit le token QR Code
public function scan(Request $request)
{
    $token = $request->input('qr_token');
    $parentId = $request->input('parent_id');

    // Chercher l'élève par son qr_token
    $eleve = Eleve::where('qr_token', $token)->first();

    if (!$eleve) {
        return response()->json(['success' => false, 'message' => 'QR Code invalide'], 404);
    }

    // Vérifier si déjà lié
    $dejaLie = EleveParent::where('parent_id', $parentId)
                           ->where('eleve_id', $eleve->id)
                           ->exists();

    if ($dejaLie) {
        return response()->json(['success' => true, 'eleve' => $eleve, 'message' => 'Déjà lié']);
    }

    // Créer la liaison
    EleveParent::create([
        'parent_id' => $parentId,
        'eleve_id'  => $eleve->id,
        'methode'   => 'qr_code',
    ]);

    return response()->json(['success' => true, 'eleve' => $eleve]);
}

// parCodeSecret() — reçoit code secret + nom école
public function parCodeSecret(Request $request)
{
    $codeSecret = $request->input('code_secret');
    $nomEcole   = $request->input('nom_ecole');
    $parentId   = $request->input('parent_id');

    $eleve = Eleve::where('code_secret', $codeSecret)
                  ->whereHas('classe.ecole', fn($q) => $q->where('nom', 'like', "%$nomEcole%"))
                  ->first();

    if (!$eleve) {
        return response()->json(['success' => false, 'message' => 'Code ou école incorrect'], 404);
    }

    EleveParent::firstOrCreate([
        'parent_id' => $parentId,
        'eleve_id'  => $eleve->id,
    ], ['methode' => 'code_secret']);

    return response()->json(['success' => true, 'eleve' => $eleve]);
}
```

---

## PARTIE 4 — CE QUI DOIT ÊTRE FAIT DANS app_mobile/

### 4.1 — Page Login Parent

**Fichier :** `lib/features/auth/pages/login.dart` (modifier l'existant)

```
Interface :
┌─────────────────────────────────────────────────────────────┐
│              🏫 Skooly — Espace Parent                      │
│                                                             │
│  Connectez-vous avec votre téléphone ou email               │
│                                                             │
│  [📱 Numéro de téléphone  OU  📧 Email]                    │
│  [🔒 Mot de passe                     ]                    │
│                                                             │
│  [Se connecter]                                             │
└─────────────────────────────────────────────────────────────┘

Logique :
→ POST /api/auth/parent/login
  Body : { identifiant: "email ou téléphone", password: "..." }
→ Réponse : { token, parent: { id, nom, prenom } }
→ Stocker le token → naviguer vers ParentHomePage
```

---

### 4.2 — Page Mes Enfants (état vide → liaison)

**Fichier :** `lib/features/parent/pages/parent_home_page.dart` (modifier)

```
État VIDE (aucun enfant lié) :
┌─────────────────────────────────────────────────────────────┐
│  👋 Bonjour M. Ewosso                                       │
│                                                             │
│  Vous n'avez pas encore lié d'enfant.                      │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  📷 Scanner le QR Code de votre enfant              │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  🔑 Entrer le code secret                           │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘

État AVEC enfants liés :
┌─────────────────────────────────────────────────────────────┐
│  👋 Bonjour M. Ewosso          [+ Lier un autre enfant]    │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  📷 Junior Nguema                                   │   │
│  │  3ème B — Lycée Notre-Dame                          │   │
│  │  Présent aujourd'hui ✅                             │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

---

### 4.3 — Page Scanner QR Code

**Fichier à créer :** `lib/features/auth/pages/qr_scan_page.dart`

```
Interface :
→ Caméra plein écran
→ Carré de scan centré
→ Texte : "Pointez vers le QR Code de votre enfant"
→ Bouton "Entrer le code manuellement" en bas

Logique (version démo simple) :
1. Scan → récupère le token
2. POST /api/qrcode/scan { qr_token, parent_id }
3. Succès → afficher "✅ Junior Nguema lié !" → retour dashboard
4. Échec → afficher "❌ QR Code non reconnu"
```

---

### 4.4 — Page Code Secret

**Fichier à créer :** `lib/features/auth/pages/link_child_page.dart`

```
Interface :
┌─────────────────────────────────────────────────────────────┐
│  🔑 Lier votre enfant par code secret                       │
│                                                             │
│  Code secret de l'élève                                     │
│  [SKL-2026-XXXX              ]                              │
│                                                             │
│  Sélectionner l'école                                       │
│  [Rechercher une école...  ▼]                               │
│    → Notre-Dame de Quaben                                   │
│    → Sainte-Thérèse                                         │
│    → École Catholique                                       │
│                                                             │
│  [Lier mon enfant]                                          │
└─────────────────────────────────────────────────────────────┘

Logique :
→ POST /api/qrcode/code-secret { code_secret, nom_ecole, parent_id }
→ Même résultat que QR Code
```

---

### 4.5 — Dépendances pubspec.yaml (minimum pour la démo)

```yaml
dependencies:
  flutter:
    sdk: flutter
  cupertino_icons: ^1.0.8

  # HTTP
  dio: ^5.4.3+1

  # Stockage token
  shared_preferences: ^2.2.3

  # QR Code Scanner
  mobile_scanner: ^5.2.3

  # Gestion d'état simple
  provider: ^6.1.2
```

---

## PARTIE 5 — FLUX DÉMO COMPLET (étape par étape)

```
ÉTAPE 1 — Admin crée le contexte (appweb_skooly)
  a. Créer l'école "Notre-Dame de Quaben"
  b. Créer la classe "3ème B"
  c. Créer les matières : Maths, Français, Histoire
  d. Créer l'enseignant M. Obiang → affecter à Maths (3ème B)
  e. Créer l'élève Junior Nguema → classe 3ème B
     → Code secret auto-généré : SKL-2026-4521
     → QR Code auto-généré
  f. Créer le parent M. Ewosso → lier à Junior (optionnel côté admin)

ÉTAPE 2 — Parent se connecte (app_mobile)
  a. Ouvre app_mobile → sélectionne "Parent"
  b. Login : téléphone +241 06 11 22 33 + mot de passe
  c. Arrive sur "Mes enfants" → liste vide

ÉTAPE 3 — Parent lie son enfant (app_mobile)
  OPTION A — QR Code :
    a. Clique "Scanner QR Code"
    b. Scanne le QR Code imprimé sur la carte de Junior
    c. ✅ "Junior Nguema lié avec succès !"
    d. Junior apparaît dans "Mes enfants"

  OPTION B — Code secret :
    a. Clique "Entrer le code secret"
    b. Saisit : SKL-2026-4521
    c. Sélectionne : Notre-Dame de Quaben
    d. ✅ "Junior Nguema lié avec succès !"
    e. Junior apparaît dans "Mes enfants"

ÉTAPE 4 — Parent voit les infos de son enfant (app_mobile)
  → Nom, photo, classe
  → Matières : Maths (M. Obiang), Français (Mme Nze)...
  → Statut présence du jour
```

---

## RÉCAPITULATIF DES FICHIERS À CRÉER/MODIFIER

### appweb_skooly/
```
CRÉER :
  src/features/featuresAdmin/ElevesPage.jsx
  src/features/featuresAdmin/ParentsPage.jsx
  src/features/featuresAdmin/MatieresPage.jsx

MODIFIER :
  src/features/featuresAdmin/ClassesPage.jsx  → ajouter onglet Matières
  src/components/AdminRoutes.jsx              → 3 nouvelles routes
  src/partials/Sidebar.jsx                   → 3 nouveaux liens
```

### api/
```
CRÉER :
  app/Http/Controllers/EleveController.php
  app/Http/Controllers/ParentController.php
  app/Http/Controllers/MatiereController.php
  app/Http/Controllers/QrCodeController.php
  app/Models/Eleve.php
  app/Models/Parent.php  (ParentModel)
  app/Models/Matiere.php
  app/Models/EleveParent.php
  database/migrations/ (voir carnet.sql)
  routes/api.php (voir 3.2)
```

### app_mobile/
```
MODIFIER :
  lib/features/auth/pages/login.dart         → login email OU téléphone
  lib/features/parent/pages/parent_home_page.dart → état vide + liaison

CRÉER :
  lib/features/auth/pages/qr_scan_page.dart
  lib/features/auth/pages/link_child_page.dart
  lib/shared/config/api_client.dart
  lib/shared/config/api_endpoints.dart

AJOUTER dans pubspec.yaml :
  dio, shared_preferences, mobile_scanner, provider
```

---

> **Version démo — Pas de sécurité complexe**
> **Objectif : montrer le flux complet en démonstration**
