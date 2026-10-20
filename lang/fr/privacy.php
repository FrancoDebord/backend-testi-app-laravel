<?php

// Politique de confidentialité et suppression de compte (docs/fonctionnalites/confidentialite-et-suppression.md).
// Le texte de la politique lui-même est dans resources/legal/privacy.fr.md.
return [
    'link'          => 'Politique de confidentialité',
    'updated'       => 'Dernière mise à jour : :date',
    'other_language' => 'Read in English',
    'breadcrumb'    => 'Confidentialité',
    'delete_link'   => 'Supprimer mon compte',

    'consent' => [
        'label'    => 'J’accepte la :link',
        'link'     => 'politique de confidentialité',
        'required' => 'Vous devez accepter la politique de confidentialité pour créer un compte.',
        'google'   => 'En continuant avec Google, vous acceptez la :link.',
    ],

    'deletion' => [
        'title'          => 'Supprimer votre compte My Testimony',
        'breadcrumb'     => 'Suppression du compte',
        'other_language' => 'Read in English',
        'intro'          => 'Vous pouvez supprimer votre compte My Testimony et les données associées à tout moment, depuis l’application (Paramètres → Compte → Supprimer mon compte) ou depuis cette page.',
        'deleted_title'  => 'Ce qui est supprimé',
        'deleted' => [
            'Votre profil : nom, e-mail, téléphone, photo de profil et de couverture, informations de vérification.',
            'Vos témoignages, y compris les vidéos, audios, images et preuves envoyés.',
            'Vos sujets de prière, vos sessions de prière et vos paroles prophétiques enregistrées.',
            'Vos préférences (verset du jour, affichage, notifications) et vos brouillons.',
            'Vos réactions, commentaires et abonnements.',
            'Les identifiants de notification de vos appareils et vos connexions ouvertes.',
            'Les événements publiés au nom de votre compte.',
        ],
        'delays_title'   => 'Délais',
        'delays' => [
            'Depuis l’application ou cette page en étant connecté : suppression immédiate et définitive.',
            'Par le formulaire ci-dessous : votre demande est traitée sous 30 jours au plus.',
            'Les copies de sauvegarde sont effacées sous 90 jours.',
            'Les journaux techniques du serveur (sans contenu) sont conservés 12 mois au maximum, sauf obligation légale.',
        ],
        'irreversible'   => 'La suppression est définitive : vos contenus ne pourront pas être récupérés.',

        'connected_title' => 'Supprimer mon compte maintenant',
        'connected_as'   => 'Vous êtes connecté en tant que :name.',
        'password'       => 'Mot de passe',
        'password_hint'  => 'Saisissez votre mot de passe pour confirmer.',
        'submit'         => 'Supprimer définitivement mon compte',
        'confirm_title'  => 'Supprimer votre compte ?',
        'confirm_text'   => 'Votre profil, vos témoignages, vos sujets de prière et toutes vos données seront supprimés définitivement. Cette action est irréversible.',
        'confirm_label'  => 'Supprimer',
        'wrong_password' => 'Mot de passe incorrect.',
        'done'           => 'Votre compte et vos données ont été supprimés.',
        'super_admin_forbidden' => 'Un compte super-administrateur ne peut pas être supprimé de cette façon. Retirez d’abord ce statut (php artisan users:super-admin).',

        // Supprimer certaines données sans supprimer le compte
        'partial_title'  => 'Supprimer certaines données sans supprimer votre compte',
        'partial_items'  => [
            'Dans l’application, chaque témoignage, sujet de prière ou parole prophétique enregistrée peut être supprimé depuis son menu « ⋮ ».',
            'Les notifications se désactivent dans Paramètres.',
        ],
        'partial_other'  => 'Pour toute autre donnée (photo de profil, historique de prière, données de vérification), écrivez à :email ou utilisez le formulaire de cette page en choisissant « Supprimer certaines données » (suppression partielle).',
        // Adresse de contact : à compléter, comme dans resources/legal/privacy.fr.md
        'contact_email'  => '[adresse e-mail de contact]',

        'request_title'  => 'Faire une demande',
        'request_intro'  => 'Indiquez l’adresse e-mail ou le numéro de téléphone de votre compte. Nous vérifierons la demande avant de la traiter.',
        'type_legend'    => 'Votre demande',
        'type_account'   => 'Supprimer mon compte',
        'type_account_hint' => 'Le compte et toutes ses données.',
        'type_partial'   => 'Supprimer certaines données',
        'type_partial_hint' => 'Suppression partielle : le compte est conservé.',
        'contact'        => 'E-mail ou téléphone du compte',
        'contact_placeholder' => 'vous@exemple.com ou +229 01 90 00 00 00',
        'contact_invalid' => 'Saisissez une adresse e-mail ou un numéro de téléphone valide.',
        'message'        => 'Précisions',
        'message_hint'   => 'Obligatoire pour une suppression partielle : indiquez les données à supprimer.',
        'message_required' => 'Précisez les données à supprimer.',
        'request_submit' => 'Envoyer la demande',
        'request_sent'   => 'Votre demande a été enregistrée. Elle sera traitée sous 30 jours au plus ; nous pourrons vous contacter pour vérifier qu’il s’agit bien de votre compte.',
        'partial_sent'   => 'Votre demande de suppression partielle a été enregistrée. Elle sera traitée sous 30 jours au plus ; nous pourrons vous contacter pour vérifier qu’il s’agit bien de votre compte.',
        'or_login'       => 'Vous avez encore accès à votre compte ?',
        'login'          => 'Connectez-vous',
        'login_suffix'   => 'pour le supprimer immédiatement.',

        'notify_admin_known'   => 'Demande de suppression du compte lié à :contact.',
        'notify_admin_unknown' => 'Demande de suppression pour :contact (aucun compte reconnu).',
        'notify_admin_partial_known'   => 'Demande de suppression partielle de données du compte lié à :contact.',
        'notify_admin_partial_unknown' => 'Demande de suppression partielle de données pour :contact (aucun compte reconnu).',

        'api' => [
            'deleted' => 'Compte et données supprimés.',
        ],
    ],

    'admin' => [
        'title'       => 'Demandes de suppression',
        'subheader'   => 'Demandes faites sans connexion sur la page publique de suppression de compte.',
        'empty'       => 'Aucune demande.',
        'contact'     => 'Contact',
        'account'     => 'Compte reconnu',
        'no_account'  => 'Aucun compte',
        'received'    => 'Reçue',
        'status'      => 'État',
        'statuses'    => ['pending' => 'En attente', 'done' => 'Traitée', 'rejected' => 'Refusée'],
        'delete'      => 'Supprimer le compte',
        'reject'      => 'Classer sans suite',
        'mark_done'   => 'Marquer comme traitée',
        'marked_done' => 'Demande marquée comme traitée.',
        'types'       => ['account' => 'Compte entier', 'partial' => 'Suppression partielle'],
        'confirm_delete' => 'Le compte « :name » et toutes ses données seront supprimés définitivement.',
        'deleted'     => 'Compte supprimé.',
        'rejected'    => 'Demande classée sans suite.',
        'super_admin_only' => 'Seul un super-administrateur peut supprimer un compte.',
    ],
];
