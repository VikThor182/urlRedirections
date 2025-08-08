# 🧪 Fichier de test pour l'application

Ce fichier Excel d'exemple peut être utilisé pour tester l'application de redirection d'URLs.

## Contenu du fichier test.xlsx

| Adresse | Nouvelle Adresse |
|---------|------------------|
| https://example.com/contact | |
| https://example.com/about | |
| https://example.com/services | |
| https://example.com/blog | |
| https://httpstat.us/404 | |

## Instructions de test

1. Démarrer l'application avec `make up`
2. Aller sur http://localhost:8000
3. Uploader le fichier `test.xlsx`
4. Entrer comme URL de site : `https://example.com`
5. Lancer le traitement

## Résultats attendus

- Les URLs `example.com/*` devraient être testées
- L'URL `httpstat.us/404` devrait retourner une 404
- L'application devrait chercher des correspondances sur example.com
