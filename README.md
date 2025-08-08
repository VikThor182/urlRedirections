# 🔄 Application de Redirection d'URLs

Application simple pour automatiser la mise à jour des liens dans un fichier Excel après une migration de site web.

## 📋 Ce que fait cette application

Vous avez un fichier Excel avec des anciennes URLs qui ne fonctionnent plus après une migration de site ? Cette application va :

1. **Lire votre fichier Excel** avec les anciennes URLs
2. **Tester automatiquement** si ces URLs redirigent déjà vers les bonnes pages
3. **Trouver les nouvelles URLs** pour celles qui sont cassées
4. **Vous donner un fichier Excel mis à jour** avec toutes les nouvelles adresses

## 🖥️ Installation sous Windows (ULTRA SIMPLE)

### Étape 1 : Installer Docker Desktop

1. **Télécharger Docker Desktop** : https://www.docker.com/products/docker-desktop/
2. **Installer Docker Desktop** (suivre l'assistant d'installation)
3. **Redémarrer votre ordinateur** si demandé
4. **Lancer Docker Desktop** (icône Docker dans la barre des tâches)

### Étape 2 : Télécharger l'application

1. **Télécharger l'application** :
   - Soit : Télécharger le ZIP depuis GitHub et l'extraire
   - Soit : Si vous avez Git installé :
   ```
   git clone [URL_DU_DEPOT]
   cd urlRedirection
   ```

### Étape 3 : Lancer l'application (1 seule commande !)

1. **Ouvrir l'Invite de commande** dans le dossier de l'application
2. **Taper cette commande magique** :
   ```
   make install
   ```
3. **Attendre** que tout s'installe automatiquement
4. **C'est tout !** L'application se lance automatiquement

## 🚀 Utilisation quotidienne

### Démarrer l'application
```
make up
```

### Arrêter l'application
```
make down
```

### Voir si l'application fonctionne
```
make status
```

### Redémarrer l'application
```
make restart
```

## 📊 Préparer votre fichier Excel

Votre fichier Excel doit avoir **exactement ces deux colonnes** :

| Adresse | Nouvelle Adresse |
|---------|------------------|
| https://monsite.com/ancienne-page | (sera rempli automatiquement) |
| https://monsite.com/autre-page | (sera rempli automatiquement) |
| https://monsite.com/contact | (sera rempli automatiquement) |

**Important :**
- Colonne A : Nommée "Adresse" (avec vos anciennes URLs)
- Colonne B : Nommée "Nouvelle Adresse" (peut être vide, sera remplie automatiquement)

## 🎯 Utiliser l'application

1. **Démarrer l'application** : `make up`
2. **Ouvrir votre navigateur** : `http://localhost:8000`
3. **Cliquer sur "Choisir un fichier"** et sélectionner votre fichier Excel
4. **Entrer l'URL de votre nouveau site** (exemple : `https://nouveau-site.com`)
5. **Cliquer sur "Traiter le fichier"**
6. **Attendre** que le traitement se termine
7. **Télécharger le fichier mis à jour** quand c'est terminé
8. **Arrêter l'application** : `make down`

## ✅ Ce qui va se passer

L'application va :
- **Tester chaque URL** de votre colonne "Adresse"
- **Si l'URL fonctionne** → Elle garde l'URL finale (même après redirection)
- **Si l'URL ne fonctionne pas** → Elle cherche sur votre nouveau site la page qui correspond le mieux
- **Remplir automatiquement** la colonne "Nouvelle Adresse"

## 🛠️ Commandes disponibles

Tapez `make help` pour voir toutes les commandes disponibles :

- `make install` - Installation complète (première fois)
- `make up` - Démarre l'application
- `make down` - Arrête l'application
- `make restart` - Redémarre l'application
- `make status` - Voir si l'application fonctionne
- `make logs` - Voir les logs en cas de problème
- `make clean` - Nettoyage complet

## ❓ Problèmes courants

### "make n'est pas reconnu"
**Solution** : Utiliser les commandes Docker directement :
```
docker-compose up -d --build
```
Pour arrêter :
```
docker-compose down
```

### "Docker n'est pas en cours d'exécution"
**Solution** : Lancer Docker Desktop depuis le menu Démarrer

### L'application ne se charge pas
**Solution** : Vérifier que l'application fonctionne :
```
make status
```

### Port déjà utilisé
**Solution** : Arrêter l'application qui utilise le port 8000 ou modifier le port dans `docker-compose.yml`

## 📞 Résultats attendus

Après traitement, vous recevrez un rapport comme :
> "Traitement terminé ! 25 URL(s) mises à jour sur 30 lignes. (15 redirections OK, 10 correspondances trouvées)"

Cela signifie :
- **15 URLs** fonctionnaient déjà et ont été mises à jour avec leur adresse finale
- **10 URLs** étaient cassées et l'application a trouvé les nouvelles pages correspondantes
- **5 URLs** n'ont pas pu être traitées (vous devrez les faire manuellement)

## 🎉 Avantages de cette version Docker

- ✅ **Installation ultra-simple** : Juste Docker à installer
- ✅ **Aucune configuration** : Tout fonctionne directement
- ✅ **Compatible tous OS** : Windows, Mac, Linux
- ✅ **Isolé** : N'interfère pas avec votre système
- ✅ **Mise à jour facile** : `git pull` + `make restart`

---

💡 **Astuce** : Gardez toujours une copie de votre fichier Excel original avant traitement !
