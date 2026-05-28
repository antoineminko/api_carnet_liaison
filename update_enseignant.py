import re

file_path = 'app/Http/Controllers/Api/EnseignantController.php'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

content = content.replace("use Illuminate\Support\Facades\DB;", "use Illuminate\Support\Facades\DB;\nuse Illuminate\Support\Facades\Hash;")

# In store
store_replacement = '''
             = DB::table('enseignants')->insertGetId([
                'nom' => ->input('nom', 'N/A'),
                'prenom' => ->input('prenom', 'N/A'),
                'matiere' => ->input('matiere', null),
                'email' => ->input('email', null),
                'telephone' => ->input('telephone', null),
                'password' => ->input('password') ? Hash::make(->input('password')) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
'''
content = re.sub(r"\ = DB::table\('enseignants'\)->insertGetId\(\[[\s\S]*?'updated_at' => now\(\),\s*\]\);", store_replacement.strip(), content)

# In update
update_replacement = '''
             = [
                'nom' => ->input('nom'),
                'prenom' => ->input('prenom'),
                'matiere' => ->input('matiere'),
                'email' => ->input('email'),
                'telephone' => ->input('telephone'),
                'est_prof_principal' => ->input('est_prof_principal', false),
                'classe_principale_id' => ->input('classe_principale_id'),
                'updated_at' => now(),
            ];
            
            if (->filled('password')) {
                ['password'] = Hash::make(->input('password'));
            }

            DB::table('enseignants')->where('id', )->update();
'''
content = re.sub(r"DB::table\('enseignants'\)->where\('id', \\)->update\(\[[\s\S]*?'updated_at' => now\(\),\s*\]\);", update_replacement.strip(), content)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)
