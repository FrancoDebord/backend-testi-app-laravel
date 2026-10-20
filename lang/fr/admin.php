<?php

// Administration : contrôleurs Admin, journal d'audit, vidéos Bunny, vues resources/views/admin/*.
return [
    'flash' => [
        'organization_only_verify' => 'Seul un compte organisation peut être vérifié.',
        'organization_verified'    => 'Organisation vérifiée. Le compte a été prévenu.',
        'organization_only_review' => 'Seul un compte organisation peut faire l’objet d’une vérification.',
        'organization_rejected'    => 'Vérification refusée. Le compte a été prévenu.',
        'own_role'                 => 'Vous ne pouvez pas modifier votre propre rôle.',
        'user_updated'             => 'Utilisateur mis à jour.',
        'category_created'         => 'Catégorie créée.',
        'category_updated'         => 'Catégorie mise à jour.',
        'category_deleted'         => 'Catégorie supprimée.',
        'settings_updated'         => 'Paramètres mis à jour.',
    ],

    'api' => [
        'organization_only_verify' => 'Seul un compte organisation peut être vérifié',
        'organization_verified'    => 'Organisation vérifiée',
        'organization_only_review' => 'Seul un compte organisation peut faire l’objet d’une vérification',
        'organization_rejected'    => 'Vérification refusée',
        'cannot_ban_admin'         => 'Impossible de bannir un administrateur',
        'user_banned'              => 'Utilisateur banni',
        'user_suspended'           => 'Utilisateur suspendu',
        'user_activated'           => 'Utilisateur activé',
        'own_role'                 => 'Vous ne pouvez pas modifier votre propre rôle',
        'role_updated'             => 'Rôle mis à jour',
        'slug_taken'               => 'Le slug \':slug\' est déjà utilisé. Fournissez un slug personnalisé.',
        'category_created'         => 'Catégorie créée',
        'category_updated'         => 'Catégorie mise à jour',
        'category_in_use'          => 'Impossible de supprimer : :count témoignage(s) sont liés à cette catégorie. Désactivez-la plutôt.',
        'category_deleted'         => 'Catégorie supprimée',
        'settings_updated'         => 'Paramètres mis à jour',
    ],

    // Journal d'audit (AdminAudit::label).
    'audit' => [
        'testimony' => [
            'approved'      => 'Témoignage approuvé (publié)',
            'rejected'      => 'Témoignage refusé',
            'resubmitted'   => 'Témoignage modifié par l\'auteur, renvoyé en modération',
            'verified'      => 'Label « Témoignage approuvé » attribué',
            'unverified'    => 'Label « Témoignage approuvé » retiré',
            'featured'      => 'Mis à la une',
            'unfeatured'    => 'Retiré de la une',
            'deleted'       => 'Mis à la corbeille',
            'restored'      => 'Restauré depuis la corbeille',
            'force_deleted' => 'Supprimé définitivement',
        ],
        'user' => [
            'badge_granted'       => 'Badge « Approuvé » attribué',
            'badge_revoked'       => 'Badge « Approuvé » retiré',
            'super_admin_granted' => 'Statut super-administrateur attribué',
            'super_admin_revoked' => 'Statut super-administrateur retiré',
            'testimony_types'     => 'Types de témoignages du profil modifiés',
        ],
    ],

    // Vidéos Bunny Stream (BunnyVideoSync).
    'bunny' => [
        'notify_stuck'      => 'La vidéo « :title » est en traitement chez Bunny depuis plus de :minutes minutes (:guid).',
        'push_ready_title'  => 'Vidéo prête',
        'push_ready_body'   => 'Votre vidéo « :title » est prête.',
        'push_failed_title' => 'Vidéo non traitée',
        'push_failed_body'  => 'Votre vidéo « :title » n\'a pas pu être traitée. Merci de l\'envoyer de nouveau.',
    ],

    // Pagination (resources/views/vendor/pagination/tailwind.blade.php).
    'pagination' => [
        'label'    => 'Pagination',
        'range'    => ':first–:last sur :total',
        'items'    => ':count élément(s)',
        'previous' => 'Page précédente',
        'next'     => 'Page suivante',
        'page'     => 'Page :page',
        'page_of'  => 'Page :page / :last',
    ],

    // Documentation en ligne (Web\DocumentationController).
    'documentation' => [
        'updated_format' => 'd/m/Y \à H:i',
        'groups' => [
            'general'  => 'Général',
            'features' => 'Fonctionnalités',
            'tracking' => 'Suivi',
        ],
    ],

    // Types de témoignages (resources/views/admin/categories/index.blade.php)
    'categories' => [
        'name_en'      => 'Nom anglais (facultatif)',
        'name_en_hint' => 'Affiché quand le site ou l’application est en anglais ; sinon le nom français est utilisé.',
        'english'      => 'Anglais',
    ],
];
