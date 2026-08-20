# Storage 360 - Plugin Moodle

Plugin Moodle de type `local` offrant une vision complète du stockage de la plateforme avec des capacités d'analyse, de visualisation et de gestion des données.

## Fonctionnalités

### Dashboard
- KPIs : stockage total, nombre de fichiers, taille moyenne, taux de croissance mensuel
- Gauge d'utilisation disque (vert/orange/rouge) avec 3 modes de calcul
- Pie chart de répartition par composant
- Top 5 des cours consommateurs

### Analyse par cours
- Tableau trié par taille, nombre de fichiers, sauvegardes, devoirs
- Filtres : catégorie, taille minimale, visibilité
- Calcul du stockage par étudiant
- Export CSV

### Analyse par utilisateur
- Détail : fichiers privés, brouillons, devoirs
- Recherche par nom/email
- Filtre par taille minimale
- Export CSV

### Analyse par composant
- Bar chart horizontal des 15 plus gros composants
- Tableau avec nombre de fichiers, taille totale, moyenne, max, dates

### Évolution temporelle
- Graphique historique (données collectées quotidiennement par la tâche planifiée)
- Analyse rétrospective : ajouts mensuels + courbe cumulative

### Nettoyage
- Suppression de sauvegardes (filtres par âge et taille)
- Nettoyage de brouillons utilisateurs anciens
- Confirmation en deux étapes
- Logging de toutes les suppressions via l'Events API Moodle

## Installation

1. Copier le dossier `local/storage360/` dans le répertoire `local/` de votre Moodle
2. Aller dans **Administration du site > Notifications** pour lancer l'installation
3. Configurer dans **Administration du site > Serveur > Storage 360**

## Configuration

| Paramètre | Description | Défaut |
|-----------|-------------|--------|
| Méthode espace disque | Auto (système de fichiers), Manuel, ou BD uniquement | Auto |
| Taille disque manuelle | Taille en Go (si mode Manuel) | 100 |
| Durée du cache | TTL en secondes | 3600 |
| Seuil brouillons | Jours avant éligibilité au nettoyage | 30 |
| Seuil sauvegardes | Jours avant éligibilité au nettoyage | 365 |
| Nettoyage auto | Active le nettoyage automatique par la tâche planifiée | Non |

## Capabilities

| Capability | Type | Description |
|-----------|------|-------------|
| `local/storage360:view` | read | Voir le dashboard |
| `local/storage360:viewdetails` | read | Voir les vues détaillées (cours, utilisateurs, composants) |
| `local/storage360:deletefiles` | write | Supprimer des fichiers via l'interface de nettoyage |
| `local/storage360:managesettings` | write | Modifier les paramètres du plugin |

Toutes les capabilities sont attribuées par défaut au rôle `manager`.

## Tâche planifiée

La tâche `collect_storage_stats` s'exécute quotidiennement à 3h du matin et :
- Enregistre un snapshot global (fichiers, taille, espace disque)
- Enregistre un snapshot par cours (taille, fichiers, sauvegardes, devoirs)
- Alimente les graphiques d'évolution temporelle

Modifiable dans **Administration > Serveur > Tâches planifiées**.

## Prérequis

- Moodle 4.5 à 5.2
- PHP 8.1+ pour Moodle 4.5 ; PHP 8.3 à 8.4 pour Moodle 5.2 (PHP 64 bits requis)
- Bases de données minimales pour Moodle 5.2 : PostgreSQL 16, MySQL 8.4, MariaDB 10.11, SQL Server 2019

> **Note** : depuis Moodle 5.1 (restructuration du webroot), le plugin s'installe sous
> `public/local/storage360` au lieu de `local/storage360`.

## Tests

```bash
# Depuis la racine Moodle
vendor/bin/phpunit --testsuite local_storage360_testsuite
# Ou fichier par fichier
vendor/bin/phpunit local/storage360/tests/storage_calculator_test.php
vendor/bin/phpunit local/storage360/tests/lib_test.php
vendor/bin/phpunit local/storage360/tests/events_test.php
```

## Structure

```
local/storage360/
├── version.php                     # Métadonnées plugin
├── settings.php                    # Paramètres admin
├── lib.php                         # Navigation + helpers
├── index.php                       # Redirect dashboard
├── styles.css                      # CSS personnalisé
├── classes/analytics/              # Moteur d'analyse
├── classes/task/                   # Tâche planifiée
├── classes/event/                  # Événements (backup_deleted, file_deleted)
├── classes/privacy/                # RGPD (nullprovider)
├── db/                             # Schéma BD, capabilities, cache, tâches
├── lang/{en,fr}/                   # Traductions
├── pages/                          # 6 pages d'interface
└── tests/                          # Tests PHPUnit
```

## Licence

GNU GPL v3 or later - http://www.gnu.org/copyleft/gpl.html
