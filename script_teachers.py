import re

file_path = 'app/Http/Controllers/Api/EleveDashboardController.php'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# Replace the teachers fetch logic
target_logic = '''        // 2. Professeurs de l'Ã©lÃ¨ve
        // On rÃ©cupÃ¨re le prof principal ou tous les profs liÃ©s Ã  la classe (simulÃ© ici via l'Ã©cole si pas de lien strict)
         = DB::table('enseignants')
            ->where('ecole_id', ->ecole_id)
            ->select('id', 'prenom', 'nom', 'matiere')
            ->get();
        if (->prof_principal_id) {
             = DB::table('enseignants')->where('id', ->prof_principal_id)->select('id', 'prenom', 'nom', 'matiere')->first();
            if () {
                // S'assurer qu'il est en tÃªte de liste ou marquer comme principal
                ->is_principal = true;
                ->prepend();
            }
        }'''

new_logic = '''        // 2. Professeurs de l'élève
        // On récupère strictement les professeurs de la classe (prof_principal + ceux ayant assigné des devoirs)
         = collect([]);
        if (->prof_principal_id) {
             = DB::table('enseignants')->where('id', ->prof_principal_id)->select('id', 'prenom', 'nom', 'matiere')->first();
            if () {
                ->is_principal = true;
                ->push();
            }
        }
        
         = DB::table('devoirs')
            ->where('classe_id', ->classe_id)
            ->whereNotNull('enseignant_id')
            ->pluck('enseignant_id')
            ->unique();
            
        foreach( as ) {
            if ( != ->prof_principal_id) {
                 = DB::table('enseignants')->where('id', )->select('id', 'prenom', 'nom', 'matiere')->first();
                if () {
                    ->is_principal = false;
                    ->push();
                }
            }
        }'''

content = content.replace(target_logic, new_logic)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)
