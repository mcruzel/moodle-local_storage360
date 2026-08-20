<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * French language strings for local_storage360.
 *
 * @package    local_storage360
 * @copyright  2025 Storage 360
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Stockage 360°';

// Navigation.
$string['nav:dashboard'] = 'Tableau de bord';
$string['nav:courses'] = 'Par cours';
$string['nav:users'] = 'Par utilisateur';
$string['nav:components'] = 'Par composant';
$string['nav:timeline'] = 'Évolution temporelle';
$string['nav:cleanup'] = 'Nettoyage';
$string['nav:backups'] = 'Sauvegardes';
$string['nav:deletelog'] = 'Historique des suppressions';

// Dashboard.
$string['dashboard:title'] = 'Stockage 360° - Tableau de bord';
$string['dashboard:totalstorage'] = 'Stockage total utilisé';
$string['dashboard:totalfiles'] = 'Nombre total de fichiers';
$string['dashboard:avgfilesize'] = 'Taille moyenne par fichier';
$string['dashboard:diskusage'] = 'Utilisation du disque';
$string['dashboard:diskfree'] = 'Espace libre';
$string['dashboard:disktotal'] = 'Espace disque total';
$string['dashboard:diskpercent'] = 'Pourcentage d\'utilisation';
$string['dashboard:growthrate'] = 'Taux de croissance mensuel';
$string['dashboard:growthrate_pending'] = 'En attente de données';
$string['dashboard:growthrate_available'] = 'Disponible à partir du {$a}';
$string['dashboard:nextupdate'] = 'Prochaine màj : {$a}';
$string['dashboard:top5courses'] = 'Top 5 des cours par stockage';
$string['dashboard:bycomponent'] = 'Stockage par composant';
$string['dashboard:nodiskinfo'] = 'Les informations sur l\'espace disque ne sont pas disponibles. Configurez la taille du disque manuellement dans les paramètres.';

// Courses view.
$string['courses:title'] = 'Stockage 360° - Stockage par cours';
$string['courses:coursename'] = 'Cours';
$string['courses:totalsize'] = 'Taille totale';
$string['courses:filecount'] = 'Fichiers';
$string['courses:backupsize'] = 'Sauvegardes';
$string['courses:assignmentsize'] = 'Devoirs';
$string['courses:studentcount'] = 'Étudiants';
$string['courses:perpupil'] = 'Par étudiant';
$string['courses:category'] = 'Catégorie';
$string['courses:visible'] = 'Visible';
$string['courses:hidden'] = 'Masqué';
$string['courses:minsize'] = 'Taille min.';
$string['courses:status'] = 'Statut';
$string['courses:search'] = 'Rechercher un cours';
$string['courses:searchplaceholder'] = 'ID ou nom...';

// Users view.
$string['users:title'] = 'Stockage 360° - Stockage par utilisateur';
$string['users:username'] = 'Utilisateur';
$string['users:email'] = 'Email';
$string['users:privatefiles'] = 'Fichiers privés';
$string['users:draftfiles'] = 'Brouillons';
$string['users:assignmentfiles'] = 'Devoirs';
$string['users:totalsize'] = 'Taille totale';
$string['users:lastupload'] = 'Dernier dépôt';
$string['users:search'] = 'Rechercher un utilisateur';
$string['users:searchplaceholder'] = 'ID, login, nom ou email...';

// Components view.
$string['components:title'] = 'Stockage 360° - Stockage par composant';
$string['components:component'] = 'Composant';
$string['components:filearea'] = 'Zone de fichiers';
$string['components:filecount'] = 'Fichiers';
$string['components:totalsize'] = 'Taille totale';
$string['components:avgsize'] = 'Taille moyenne';
$string['components:maxsize'] = 'Taille max';
$string['components:oldest'] = 'Plus ancien';
$string['components:newest'] = 'Plus récent';

// Timeline view.
$string['timeline:title'] = 'Stockage 360° - Évolution temporelle';
$string['timeline:totalovertime'] = 'Évolution du stockage total';
$string['timeline:monthlyadditions'] = 'Ajouts mensuels';
$string['timeline:nohistory'] = 'Aucune donnée historique disponible. La tâche planifiée collectera les données quotidiennement.';
$string['timeline:retroanalysis'] = 'Analyse rétrospective (basée sur les dates de création des fichiers)';
$string['timeline:bycomponent'] = 'Évolution par type de contenu';
$string['timeline:resources'] = 'Ressources';
$string['timeline:folders'] = 'Dossiers';
$string['timeline:coursefiles'] = 'Fichiers de cours';
$string['timeline:otherfiles'] = 'Autres';

// Files view.
$string['nav:files'] = 'Fichiers';
$string['files:title'] = 'Stockage 360° - Explorateur de fichiers';
$string['files:filename'] = 'Nom du fichier';
$string['files:size'] = 'Taille';
$string['files:mimetype'] = 'Type MIME';
$string['files:course'] = 'Cours';
$string['files:user'] = 'Utilisateur';
$string['files:created'] = 'Création';
$string['files:search'] = 'Rechercher un fichier';
$string['files:searchplaceholder'] = 'ex. rapport.pdf';
$string['files:totalresults'] = '{$a} fichier(s) trouvé(s)';
$string['files:filtercourse'] = 'Cours : {$a}';
$string['files:filteruser'] = 'Utilisateur : {$a}';
$string['files:filtercomponent'] = 'Composant : {$a}';
$string['files:images'] = 'Images';
$string['files:videos'] = 'Vidéos';
$string['files:audio'] = 'Audio';
$string['files:viewfiles'] = 'Voir les fichiers';

// Cleanup.
$string['cleanup:title'] = 'Stockage 360° - Nettoyage';
$string['cleanup:backups'] = 'Sauvegardes';
$string['cleanup:drafts'] = 'Brouillons utilisateurs';
$string['cleanup:selectall'] = 'Tout sélectionner';
$string['cleanup:deleteselected'] = 'Supprimer la sélection';
$string['cleanup:confirmdelete'] = 'Êtes-vous sûr de vouloir supprimer les fichiers sélectionnés ? Cette action est irréversible.';
$string['cleanup:confirmmassdelete'] = 'Vous êtes sur le point de supprimer {$a->count} fichier(s) totalisant {$a->size}. Cette action est irréversible. Continuer ?';
$string['cleanup:deleted'] = '{$a} fichier(s) supprimé(s) avec succès.';
$string['cleanup:nofiles'] = 'Aucun fichier ne correspond aux critères sélectionnés.';
$string['cleanup:olderthan'] = 'Plus ancien que (jours)';
$string['cleanup:largerthan'] = 'Plus grand que (Mo)';
$string['cleanup:preview'] = 'Aperçu';
$string['cleanup:managecourse'] = 'Gérer le cours';
$string['cleanup:viewassignment'] = 'Voir le devoir';
$string['cleanup:deleteone'] = 'Supprimer';
$string['cleanup:confirmdeletetitle'] = 'Confirmer la suppression';
$string['cleanup:confirmdeletebody'] = 'Êtes-vous sûr de vouloir supprimer ce fichier ? Cette action est irréversible.';

// Gestion des sauvegardes.
$string['backups:title'] = 'Stockage 360° - Gestion des sauvegardes';
$string['backups:warning'] = 'Les sauvegardes automatiques sont recréées par le cron Moodle. Pour libérer de l\'espace durablement, désactivez d\'abord la prochaine sauvegarde avant de supprimer les fichiers.';
$string['backups:course'] = 'Cours';
$string['backups:count'] = 'Sauvegardes';
$string['backups:totalsize'] = 'Taille totale';
$string['backups:lastbackup'] = 'Dernier backup';
$string['backups:status'] = 'Statut';
$string['backups:nextbackup'] = 'Prochain backup';
$string['backups:disabled'] = 'Désactivé';
$string['backups:status_ok'] = 'OK';
$string['backups:status_error'] = 'Erreur';
$string['backups:status_unfinished'] = 'En cours';
$string['backups:status_skipped'] = 'Ignoré';
$string['backups:status_never'] = 'Jamais';
$string['backups:disable'] = 'Désactiver';
$string['backups:enable'] = 'Réactiver';
$string['backups:setdate'] = 'Modifier la date';
$string['backups:viewfiles'] = 'Voir les fichiers';
$string['backups:modalfiles'] = 'Fichiers de sauvegarde du cours {$a}';
$string['backups:filearea_automated'] = 'Automatique';
$string['backups:filearea_course'] = 'Manuelle';
$string['backups:filearea_activity'] = 'Activité';
$string['backups:disabled_success'] = 'Sauvegarde automatique désactivée pour ce cours.';
$string['backups:enabled_success'] = 'Sauvegarde automatique réactivée pour ce cours.';
$string['backups:datechanged'] = 'Date de prochaine sauvegarde modifiée.';
$string['backups:nobackups'] = 'Aucun cours avec des fichiers de sauvegarde.';
$string['backups:search'] = 'Rechercher un cours';
$string['backups:searchplaceholder'] = 'ID ou nom du cours...';
$string['backups:confirmdisable'] = 'Êtes-vous sûr de vouloir désactiver la prochaine sauvegarde automatique pour ce cours ?';

// Historique des suppressions.
$string['deletelog:title'] = 'Stockage 360° - Historique des suppressions';
$string['deletelog:date'] = 'Date';
$string['deletelog:owner'] = 'Propriétaire du fichier';
$string['deletelog:deletedby'] = 'Supprimé par';
$string['deletelog:source'] = 'Source';
$string['deletelog:source_backups'] = 'Nettoyage (sauvegardes)';
$string['deletelog:source_drafts'] = 'Nettoyage (brouillons)';
$string['deletelog:source_files'] = 'Explorateur de fichiers';
$string['deletelog:search'] = 'Rechercher';
$string['deletelog:searchplaceholder'] = 'Nom de fichier, cours, propriétaire ou ID...';
$string['deletelog:summary'] = '{$a->count} fichier(s) supprimé(s) totalisant {$a->size}';
$string['deletelog:verifydisk'] = 'Vérifier le disque';
$string['deletelog:diskstatus'] = 'Statut disque';
$string['deletelog:status_removed'] = 'Supprimé du disque';
$string['deletelog:status_referenced'] = 'Contenu encore référencé ({$a} copie(s))';
$string['deletelog:status_trash'] = 'En corbeille (suppression par le cron en attente)';
$string['deletelog:status_orphan'] = 'Orphelin sur disque';
$string['deletelog:emptytrash'] = 'Purger la corbeille';
$string['deletelog:emptytrashconfirm'] = 'Ceci supprimera définitivement tous les fichiers non référencés de la corbeille Moodle. Cette action est irréversible. Continuer ?';
$string['deletelog:trashcleaned'] = 'La corbeille a été purgée avec succès.';
$string['deletelog:cleanresult_title'] = 'Résultats de la purge';
$string['deletelog:cleanresult_summary'] = '{$a->cleaned} fichier(s) définitivement supprimé(s) ({$a->freed} libérés). {$a->skipped} encore référencé(s), {$a->already} déjà supprimé(s), {$a->failed} en échec.';
$string['deletelog:cleanresult_cleaned'] = '{$a} supprimé(s) du disque';
$string['deletelog:cleanresult_skipped'] = '{$a} encore référencé(s)';
$string['deletelog:cleanresult_already'] = '{$a} déjà supprimé(s)';
$string['deletelog:cleanresult_failed'] = '{$a} en échec';

// Settings.
$string['settings:diskspacemethod'] = 'Méthode de calcul de l\'espace disque';
$string['settings:diskspacemethod_desc'] = 'Comment déterminer l\'espace disque total. « Auto » lit le système de fichiers, « Manuel » utilise une valeur configurée, « BD uniquement » n\'affiche que le stockage en base de données.';
$string['settings:diskspacemethod_auto'] = 'Automatique (système de fichiers)';
$string['settings:diskspacemethod_manual'] = 'Manuel';
$string['settings:diskspacemethod_dbonly'] = 'Base de données uniquement';
$string['settings:manualdisksize'] = 'Taille du disque manuelle (Go)';
$string['settings:manualdisksize_desc'] = 'Taille totale de la partition en Go, utilisée lorsque la méthode est « Manuel ».';
$string['settings:cachettl'] = 'Durée du cache (secondes)';
$string['settings:cachettl_desc'] = 'Durée de mise en cache des résultats d\'analyse avant recalcul.';
$string['settings:draftcleanupdays'] = 'Seuil de nettoyage des brouillons (jours)';
$string['settings:draftcleanupdays_desc'] = 'Les brouillons plus anciens que ce nombre de jours sont candidats au nettoyage.';
$string['settings:backupcleanupdays'] = 'Seuil de nettoyage des sauvegardes (jours)';
$string['settings:backupcleanupdays_desc'] = 'Les sauvegardes plus anciennes que ce nombre de jours sont candidates au nettoyage.';
$string['settings:enableautocleanup'] = 'Activer le nettoyage automatique';
$string['settings:enableautocleanup_desc'] = 'Si activé, la tâche planifiée nettoiera automatiquement les anciens brouillons et sauvegardes selon les seuils configurés.';
$string['settings:enablestoragealert'] = 'Activer l\'alerte de seuil de stockage';
$string['settings:enablestoragealert_desc'] = 'Envoie une notification aux administrateurs du site lorsque l\'utilisation du disque dépasse le seuil configuré. Nécessite que la méthode de calcul soit « Automatique » ou « Manuel ».';
$string['settings:storagethreshold'] = 'Seuil d\'alerte de stockage (%)';
$string['settings:storagethreshold_desc'] = 'Lorsque l\'utilisation du disque atteint ce pourcentage, une notification est envoyée à tous les administrateurs du site. Plage valide : 1-100.';

// Fournisseur de messages.
$string['messageprovider:storagealert'] = 'Alerte de seuil de stockage';

// Contenu de la notification d\'alerte.
$string['alert:subject'] = 'Stockage 360° : L\'utilisation du disque a atteint {$a} %';
$string['alert:body'] = 'Attention : L\'utilisation du disque de votre instance Moodle a atteint {$a->percent} % ({$a->used} utilisés sur {$a->total}).

Vous pouvez consulter les informations détaillées sur le stockage ici : {$a->dashboardurl}

Cette notification a été envoyée automatiquement par le plugin Stockage 360°. Vous pouvez ajuster le seuil ou désactiver les alertes dans les paramètres du plugin.';
$string['alert:bodyhtml'] = '<p><strong>Attention :</strong> L\'utilisation du disque de votre instance Moodle a atteint <strong>{$a->percent} %</strong> ({$a->used} utilisés sur {$a->total}).</p><p>Vous pouvez consulter les informations détaillées sur le <a href="{$a->dashboardurl}">tableau de bord Stockage 360°</a>.</p><p><small>Cette notification a été envoyée automatiquement par le plugin Stockage 360°. Vous pouvez ajuster le seuil ou désactiver les alertes dans les <a href="{$a->settingsurl}">paramètres du plugin</a>.</small></p>';

// Task.
$string['task:collectstoragestats'] = 'Collecter les statistiques de stockage';

// Events.
$string['event:backupdeleted'] = 'Fichier de sauvegarde supprimé';
$string['event:filedeleted'] = 'Fichier supprimé via Stockage 360°';

// Privacy.
$string['privacy:metadata:history'] = 'L\'historique des statistiques de stockage ne contient pas de données personnelles.';
$string['privacy:metadata:history:timecreated'] = 'Horodatage du snapshot.';
$string['privacy:metadata:history:total_size'] = 'Taille de stockage agrégée à ce moment.';
$string['privacy:metadata:deletelog'] = 'Journal d\'audit des fichiers supprimés via Stockage 360°. Contient des références au propriétaire du fichier et à l\'utilisateur ayant effectué la suppression.';
$string['privacy:metadata:deletelog:owneruserid'] = 'Identifiant de l\'utilisateur propriétaire du fichier.';
$string['privacy:metadata:deletelog:ownerfullname'] = 'Nom complet du propriétaire du fichier au moment de la suppression.';
$string['privacy:metadata:deletelog:deletedby'] = 'Identifiant de l\'utilisateur ayant supprimé le fichier.';
$string['privacy:metadata:deletelog:filename'] = 'Nom du fichier supprimé.';
$string['privacy:metadata:deletelog:filesize'] = 'Taille du fichier supprimé en octets.';
$string['privacy:metadata:deletelog:timedeleted'] = 'Horodatage de la suppression du fichier.';
$string['privacy:deletelog:asowner'] = 'Fichiers vous appartenant qui ont été supprimés';
$string['privacy:deletelog:asdeleter'] = 'Fichiers que vous avez supprimés';

// Common.
$string['bytes'] = 'o';
$string['kilobytes'] = 'Ko';
$string['megabytes'] = 'Mo';
$string['gigabytes'] = 'Go';
$string['terabytes'] = 'To';
$string['nodata'] = 'Aucune donnée disponible.';
$string['filter'] = 'Filtrer';
$string['reset'] = 'Réinitialiser';
$string['exportcsv'] = 'Exporter en CSV';
$string['all'] = 'Tous';
$string['period:7days'] = '7 derniers jours';
$string['period:30days'] = '30 derniers jours';
$string['period:90days'] = '90 derniers jours';
$string['period:1year'] = 'Dernière année';
$string['period:all'] = 'Tout';
$string['minsize:10mb'] = '> 10 Mo';
$string['minsize:100mb'] = '> 100 Mo';
$string['minsize:1gb'] = '> 1 Go';
$string['snapshot:lastupdated'] = 'Données au : {$a}';
$string['nosnapshots'] = 'Aucune donnée d\'analyse n\'est encore disponible. Veuillez lancer une collecte initiale pour alimenter le tableau de bord. Cela peut prendre quelques minutes selon le nombre de fichiers.';
$string['collectnow'] = 'Collecter les données maintenant';
$string['collect:title'] = 'Stockage 360° - Collecte des données';
$string['collect:running'] = 'La collecte des données est en cours, veuillez patienter...';
$string['collect:done'] = 'La collecte des données s\'est terminée avec succès.';

// Scan d'intégrité.
$string['task:integrityscan'] = 'Scan d\'intégrité des fichiers';
$string['settings:scanbatchsize'] = 'Taille du lot de scan d\'intégrité (par direction)';
$string['settings:scanbatchsize_desc'] = 'Nombre de contenthashes à scanner depuis chaque extrémité (récents + anciens) par exécution du cron. Total scanné par exécution = 2 x cette valeur. Par défaut : 10.';
$string['scan:progress_title'] = 'Scan d\'intégrité des fichiers';
$string['scan:progress_detail'] = '{$a->scanned} / {$a->total} fichiers uniques scannés ({$a->percent} %)';
$string['scan:anomalies_found'] = '{$a} anomalie(s) détectée(s)';
$string['scan:all_ok'] = 'Tous les fichiers vérifiés — aucune anomalie';
$string['scan:missing_count'] = '{$a} manquant(s)';
$string['scan:mismatch_count'] = '{$a} taille incohérente';
$string['scan:intrash_count'] = '{$a} en corbeille';
$string['scan:disk_column'] = 'Disque';
$string['scan:status_ok'] = 'Fichier présent sur le disque, taille correcte';
$string['scan:status_missing'] = 'Fichier absent du disque';
$string['scan:status_mismatch'] = 'La taille du fichier ne correspond pas à la base de données';
$string['scan:status_intrash'] = 'Le fichier est dans le répertoire de corbeille';
$string['scan:status_pending'] = 'Pas encore scanné';
$string['scan:reset'] = 'Réinitialiser le scan';
$string['scan:reset_confirm'] = 'Ceci supprimera tous les résultats du scan et démarrera un nouveau cycle. Continuer ?';
$string['scan:reset_done'] = 'Le scan d\'intégrité a été réinitialisé. Un nouveau cycle démarrera lors de la prochaine exécution du cron.';

// Fichiers orphelins.
$string['nav:orphans'] = 'Fichiers orphelins';
$string['orphans:title'] = 'Fichiers orphelins sur le disque';
$string['orphans:noorphans'] = 'Aucun fichier orphelin détecté pour le moment.';
$string['orphans:contenthash'] = 'Hash du contenu';
$string['orphans:disksize'] = 'Taille sur disque';
$string['orphans:scanned'] = 'Détecté le';
$string['orphans:filetype'] = 'Type';
$string['orphans:unknown_type'] = 'Inconnu';
$string['orphans:download'] = 'Télécharger';
$string['orphans:download_failed'] = 'Impossible de télécharger le fichier : fichier introuvable sur le disque.';
$string['orphans:delete'] = 'Supprimer';
$string['orphans:delete_confirm'] = 'Supprimer ce fichier orphelin du disque ? Cette action est irréversible.';
$string['orphans:deleted'] = 'Le fichier orphelin a été supprimé avec succès.';
$string['orphans:delete_failed'] = 'Impossible de supprimer le fichier orphelin.';
$string['orphans:still_referenced'] = 'Ce fichier est encore référencé dans la base de données et ne peut pas être supprimé.';
$string['orphans:whatare_title'] = 'Que sont les fichiers orphelins ?';
$string['orphans:whatare_body'] = 'Ce sont des fichiers présents physiquement sur le disque de Moodle mais sans aucune référence en base de données. Ils peuvent résulter d\'interruptions lors d\'uploads, de migrations partielles ou de bugs applicatifs. Ils occupent de l\'espace inutilement et peuvent être supprimés en toute sécurité après vérification. Le scan disque les détecte en parcourant le répertoire filedir de Moodle.';
$string['orphans:total_count'] = '{$a} fichier(s) orphelin(s)';
$string['orphans:total_size'] = 'Taille totale des orphelins : {$a}';
$string['orphans:disk_scan_progress'] = 'Scan disque : répertoire {$a}';
$string['orphans:disk_scan_complete'] = 'Scan disque : terminé';
$string['orphans:disk_scan_notstarted'] = 'Scan disque : pas encore démarré';
$string['scan:orphan_count'] = '{$a} orphelin(s)';
$string['scan:status_orphan'] = 'Fichier sur le disque sans référence en base de données';
