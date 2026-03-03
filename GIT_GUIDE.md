# 📚 GUIDE GIT - EMB MISSION

## ✅ Git est configuré !

**État :** 117 fichiers suivis | 1 commit initial | Branche master

---

## 🚨 ROLLBACK D'URGENCE (SI JE CASSE LE CODE)

### Annuler TOUS les changements :
```bash
cd /var/www/emb-mission
sudo git reset --hard HEAD
sudo supervisorctl restart laravel-worker:*
```

### Annuler UN fichier :
```bash
sudo git checkout fichier.php
```

### Revenir au commit précédent :
```bash
sudo git log --oneline
sudo git reset --hard COMMIT_ID
```

---

## 📋 COMMANDES QUOTIDIENNES

**Voir l'état :**
```bash
sudo git status
```

**Voir l'historique :**
```bash
sudo git log --oneline
```

**Faire un commit :**
```bash
sudo git add .
sudo git commit -m "Description"
```

---

## 🎯 WORKFLOW POUR CHAQUE MODIFICATION

1. **AVANT** : `sudo git commit -am "Avant modification"`
2. **MODIFIER** le code
3. **TESTER** : `php -l fichier.php`
4. **Si OK** : `sudo git commit -am "Modification faite"`
5. **Si KO** : `sudo git reset --hard HEAD`
