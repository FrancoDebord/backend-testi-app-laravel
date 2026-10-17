<?php

// Vérification des comptes et badge « Approuvé » : services, contrôleurs et vues admin/verifications.
return [
    'date_time_format'           => 'j F Y \à H:i',
    'account_deleted'            => 'Ce compte a été supprimé.',
    'already_processed'          => 'Cette demande a déjà été traitée.',
    'use_organization_review'    => 'Utilisez la vérification des organisations pour ce compte.',
    'not_verified'               => 'Ce compte n’est pas vérifié.',
    'document_n'                 => 'Pièce :n',

    'approval' => [
        'grant_reason_required'  => 'Indiquez le motif de l’attribution.',
        'already_approved'       => 'Ce compte a déjà le badge « Approuvé ».',
        'revoke_reason_required' => 'Indiquez le motif du retrait.',
        'not_approved'           => 'Ce compte n’a pas le badge « Approuvé ».',
    ],

    'rules' => [
        'full_name_required'     => 'Indiquez votre nom complet.',
        'full_name_min'          => 'Indiquez votre nom complet.',
        'role_required'          => 'Choisissez votre fonction.',
        'role_other_required_if' => 'Précisez votre fonction.',
        'church_required'        => 'Indiquez votre église ou votre ministère.',
        'links_max'              => 'Trois liens au plus.',
        'links_url'              => 'Chaque lien doit être une adresse web (https://…).',
        'documents_required'     => 'Ajoutez au moins une pièce justificative.',
        'documents_min'          => 'Ajoutez au moins une pièce justificative.',
        'documents_max'          => 'Trois pièces justificatives au plus.',
        'documents_mimes'        => 'Pièce justificative : photo (JPG, PNG, WebP) ou PDF.',
        'documents_file_max'     => 'Chaque pièce justificative doit faire 8 Mo au plus.',
        'documents_file'         => 'Pièce justificative invalide.',
    ],

    'block' => [
        'organization'     => 'Les organisations sont vérifiées à partir des informations de leur profil.',
        'already_verified' => 'Votre compte est déjà vérifié.',
        'pending'          => 'Votre demande est en cours d’examen.',
        'retry_at'         => 'Vous pourrez envoyer une nouvelle demande à partir du :date.',
    ],

    'notify_organization_approved' => 'Votre organisation a reçu le badge « Approuvé ». Il apparaît désormais sur votre profil et vos publications.',
    'notify_account_approved'      => 'Votre compte a reçu le badge « Approuvé ». Il apparaît désormais sur votre profil et vos publications.',
    'notify_approval_revoked'      => 'Le badge « Approuvé » a été retiré de votre compte : :reason.',
    'notify_verified'              => 'Votre compte est vérifié. Le badge « Compte vérifié » apparaît désormais sur votre profil et vos publications.',
    'notify_rejected'              => 'Votre demande de vérification a été refusée : :reason. Vous pourrez envoyer une nouvelle demande dans :hours heures.',
    'notify_revoked'               => 'La vérification de votre compte a été retirée : :reason.',

    'reason_required'        => 'Indiquez le motif.',
    'reject_reason_required' => 'Indiquez le motif du refus.',
    'flash' => [
        'badge_granted' => 'Badge « Approuvé » attribué.',
        'badge_revoked' => 'Badge « Approuvé » retiré.',
        'submitted'     => 'Demande envoyée. Notre équipe l’examinera et vous serez prévenu dans vos notifications.',
        'approved'      => 'Compte vérifié. La personne a été prévenue.',
        'rejected'      => 'Demande refusée. La personne a été prévenue du motif.',
        'revoked'       => 'Vérification retirée. La personne a été prévenue.',
    ],
    'api' => [
        'submitted' => 'Demande envoyée. Notre équipe l’examinera et vous serez prévenu dans l’application.',
        'approved'  => 'Compte vérifié',
        'rejected'  => 'Demande refusée',
        'revoked'   => 'Vérification retirée',
    ],
];
