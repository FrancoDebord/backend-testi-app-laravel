<?php

namespace Database\Seeders;

use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestimonySeeder extends Seeder
{
    public function run(): void
    {
        // Créer des utilisateurs fictifs
        $users = [];
        $names = [
            ['Marie-Claire Adjovi', 'Bénin'],
            ['Pastor Emmanuel Kokou', 'Togo'],
            ['Sœur Esther Ahouanvoèbla', 'Bénin'],
            ['Frère Daniel Gbaguidi', 'Bénin'],
            ['Grâce Akakpo', 'Togo'],
            ['Jérémie Dossou', 'Bénin'],
            ['Ruth Kossivi', 'Côte d\'Ivoire'],
            ['Samuel Agossou', 'Bénin'],
            ['Abigail Mensah', 'Ghana'],
            ['Pasteur Pierre Zinsou', 'Bénin'],
            ['Judith Amoussou', 'Bénin'],
            ['Benjamin Tokpli', 'Togo'],
        ];

        foreach ($names as [$name, $country]) {
            $slug = Str::slug($name);
            $user = User::firstOrCreate(
                ['email' => $slug . '@testiapp.com'],
                [
                    'id' => (string) Str::uuid(),
                    'display_name' => $name,
                    'password' => Hash::make('password'),
                    'role' => UserRole::Utilisateur,
                    'status' => UserAccountStatus::Active,
                    'is_active' => true,
                    'country' => $country,
                    'testimony_count' => 0,
                ]
            );
            $user->settings()->firstOrCreate(['user_id' => $user->id]);
            $users[] = $user;
        }

        // Créer des témoignages fictifs
        $categories = Category::pluck('id', 'slug');

        $testimonies = [
            [
                'title' => "Comment Dieu m'a guéri d'une maladie incurable",
                'body' => "Il y a deux ans, j'ai reçu un diagnostic dévastateur : une maladie auto-immune en phase terminale. Les médecins m'avaient donné six mois à vivre. Ma famille était effondrée. Mais au fond de moi, j'entendais une voix qui disait : 'Je suis l'Éternel ton médecin.' J'ai décidé de croire.\n\nJ'ai jeûné pendant 21 jours avec ma communauté. Chaque nuit, des membres de mon église priaient pour moi. Et un matin, lors d'un contrôle de routine, les médecins ont découvert que tous les marqueurs de la maladie avaient disparu. Ils ne savaient pas comment l'expliquer.\n\nAujourd'hui, deux ans après, je suis en parfaite santé. Je cours, je travaille, je vis. La gloire revient uniquement à Dieu.",
                'category' => 'guerison',
                'bible_verse' => "Je suis l'Éternel, ton médecin.",
                'bible_ref' => 'Exode 15:26',
                'is_featured' => true,
                'views' => 4320,
                'likes' => 287,
            ],
            [
                'title' => "Ma délivrance miraculeuse d'une addiction de 10 ans",
                'body' => "Pendant dix longues années, l'alcool était mon maître. J'avais tout perdu : ma famille, mon emploi, ma dignité. Je dormais dans la rue. Les gens me fuyaient.\n\nUn ami de l'école primaire m'a trouvé un soir dans un état pitoyable. Au lieu de me juger, il m'a emmené à une réunion de prière. Là, quelque chose s'est brisé en moi. J'ai pleuré comme un enfant pour la première fois depuis des années.\n\nLa délivrance n'a pas été instantanée, mais progressive. Chaque jour, la grâce de Dieu me suffisait. Cela fait maintenant 3 ans que je suis sobre. J'ai retrouvé mon fils. Je travaille à nouveau. Ma vie a été restaurée.",
                'category' => 'delivrance',
                'bible_verse' => "C'est pour la liberté que Christ nous a affranchis.",
                'bible_ref' => 'Galates 5:1',
                'is_featured' => true,
                'views' => 3150,
                'likes' => 221,
            ],
            [
                'title' => "Dieu a pourvu à mon loyer au dernier moment",
                'body' => "Je me souviens encore de ce jour de fin de mois. Mon propriétaire m'avait donné jusqu'au soir pour payer ou quitter l'appartement. J'avais cherché partout, demandé à tout le monde. Rien.\n\nÀ 17h, j'ai posé la question à Dieu dans la prière : 'Seigneur, je ne sais plus quoi faire.' À 17h30, mon téléphone a sonné. C'était un client que j'avais oublié, qui m'appelait pour me dire qu'il venait de transférer le paiement d'un travail fait six mois plus tôt. Le montant exact de mon loyer.\n\nJe pleurais en raccrochant. Dieu connaît exactement ce dont nous avons besoin et il pourvoit au moment exact.",
                'category' => 'provision',
                'bible_verse' => "Mon Dieu pourvoira à tous vos besoins.",
                'bible_ref' => 'Philippiens 4:19',
                'is_featured' => false,
                'views' => 2100,
                'likes' => 165,
            ],
            [
                'title' => "Protection divine lors d'un accident terrible",
                'body' => "Je revenais du culte un dimanche soir quand un chauffard ivre a grillé le feu rouge. Il m'a percuté à pleine vitesse. Ma voiture a fait trois tonneaux avant de s'immobiliser dans le fossé.\n\nLes témoins ont dit qu'il était impossible que quelqu'un survive à un tel choc. Les pompiers ont mis vingt minutes à me désincarcérer, certains que je serais mort.\n\nJ'en suis sorti avec quelques égratignures et une légère commotion. Le médecin urgentiste, qui n'était pas croyant, m'a dit : 'Quelqu'un vous a protégé ce soir.' Oui, et je sais qui c'est.",
                'category' => 'protection',
                'bible_verse' => "L'ange de l'Éternel campe autour de ceux qui le craignent, et il les protège.",
                'bible_ref' => 'Psaume 34:7',
                'is_featured' => true,
                'views' => 1890,
                'likes' => 142,
            ],
            [
                'title' => "Mon mariage restauré après 3 ans de séparation",
                'body' => "Nous étions séparés depuis presque trois ans. Les papiers de divorce étaient déjà préparés. Mon mari avait une autre relation. J'avais moi-même commencé à tourner la page.\n\nMais une servante de Dieu m'a dit de tenir bon dans la prière. J'ai décidé de croire même quand tout semblait perdu. J'ai jeûné pendant 40 jours en demandant à Dieu de restaurer notre foyer.\n\nSix mois plus tard, mon mari m'a appelée. Il pleurait. Il disait qu'il n'avait plus la paix depuis qu'il nous avait quittés. Il est revenu, repentant. Nous avons renouvelé nos vœux en présence de nos enfants. Notre mariage est aujourd'hui plus fort qu'il ne l'a jamais été.",
                'category' => 'mariage',
                'bible_verse' => "Ce que Dieu a uni, que l'homme ne le sépare pas.",
                'bible_ref' => 'Matthieu 19:6',
                'is_featured' => false,
                'views' => 2540,
                'likes' => 198,
            ],
            [
                'title' => "Dieu m'a donné un enfant après 8 ans d'attente",
                'body' => "Huit ans. Huit ans de larmes, de tentatives médicales, de faux espoirs. Les médecins avaient renoncé. Ils disaient que c'était impossible pour moi d'avoir un enfant naturellement.\n\nJ'ai continué à prier, même dans les moments où la prière me semblait vide. Mon mari était mon soutien de fer. Notre église priait avec nous fidèlement.\n\nEt puis, un matin ordinaire, un test de grossesse est revenu positif. Nous n'y croyions presque pas. Neuf mois plus tard, notre fils Ezéchiel est né en parfaite santé. Son nom signifie 'Dieu fortifie' - et c'est exactement ce qu'Il a fait pour nous.",
                'category' => 'famille',
                'bible_verse' => "Il fait habiter dans sa maison la femme stérile, en la rendant joyeuse mère de famille.",
                'bible_ref' => 'Psaume 113:9',
                'is_featured' => true,
                'views' => 3680,
                'likes' => 312,
            ],
            [
                'title' => "Comment j'ai trouvé un emploi en pleine crise",
                'body' => "Licencié après 7 ans de bons et loyaux services, j'ai cherché un emploi pendant 18 mois. 47 candidatures. 12 entretiens. 0 offre. Ma confiance s'effritait chaque semaine.\n\nMais j'ai continué à prier et à me former. J'ai appris de nouvelles compétences en ligne. Et surtout, j'ai décidé de faire confiance à Dieu pour le timing.\n\nUn matin, j'ai reçu un appel d'une entreprise que je n'avais pas sollicitée. Ils avaient trouvé mon profil et cherchaient exactement quelqu'un avec mon parcours. Le salaire proposé était 40% supérieur à mon ancien poste. Dieu ne retarde pas, Il prépare.",
                'category' => 'emploi',
                'bible_verse' => "Commets ton sort à l'Éternel, mets en lui ta confiance, et il agira.",
                'bible_ref' => 'Psaume 37:5',
                'is_featured' => false,
                'views' => 1560,
                'likes' => 124,
            ],
            [
                'title' => "Ma conversion au bord du précipice",
                'body' => "Je n'avais jamais cru en Dieu. Pour moi, c'était une faiblesse des esprits simples. Je vivais dans l'excès : alcool, fêtes, relations sans lendemain.\n\nUne nuit, après une soirée particulièrement sombre, je me suis retrouvé sur le bord d'un pont. Je voulais en finir. Et là, dans ce silence absolu, j'ai entendu quelque chose que je ne saurais décrire autrement que : une voix intérieure qui disait 'Je t'aime.'\n\nJ'ai reculé. J'ai cherché. J'ai trouvé une église. Ma vie a été complètement transformée. Aujourd'hui je témoigne dans les prisons et les rues pour que personne d'autre ne se retrouve seul au bord d'un pont.",
                'category' => 'salut',
                'bible_verse' => "Dieu a tant aimé le monde qu'il a donné son Fils unique.",
                'bible_ref' => 'Jean 3:16',
                'is_featured' => true,
                'views' => 5120,
                'likes' => 421,
            ],
            [
                'title' => "Bourse d'études obtenue miraculeusement",
                'body' => "Je voulais poursuivre mes études à l'étranger mais notre famille n'en avait absolument pas les moyens. J'avais fait plusieurs demandes de bourses, toutes refusées. J'avais accepté que ce rêve ne se réaliserait probablement pas.\n\nMon pasteur m'a dit : 'Prie avec foi, Dieu peut ouvrir des portes que tu n'as même pas encore frappées.' J'ai prié et j'ai quand même posé une dernière candidature pour une bourse dont je ne remplissais pas tous les critères.\n\nTrois mois plus tard, j'ai reçu une lettre m'annonçant que j'avais été sélectionné sur dossier. La bourse couvre les frais de scolarité, le logement et une allocation mensuelle. Dieu avait préparé cela avant même ma prière.",
                'category' => 'etudes',
                'bible_verse' => "Cherchez d'abord le royaume de Dieu et sa justice, et toutes ces choses vous seront données par-dessus.",
                'bible_ref' => 'Matthieu 6:33',
                'is_featured' => false,
                'views' => 1230,
                'likes' => 98,
            ],
            [
                'title' => "Guérison d'un cancer au stade 4",
                'body' => "Le diagnostic est tombé comme un couperet : cancer du sein, stade 4, avec métastases. Le médecin m'avait donné 3 à 6 mois. J'avais 34 ans et deux enfants en bas âge.\n\nMon mari et moi avons décidé de faire confiance à Dieu tout en acceptant les traitements médicaux. Chaque semaine, notre cellule de prière jeûnait pour moi. Des frères et sœurs du monde entier intercédaient.\n\nAprès un an de traitement, les scanners ne montrent plus aucune trace du cancer. Les oncologues qualifient mon cas de 'rémission complète inexplicable'. Je qualifie ça de miracle de Dieu. J'ai maintenant 38 ans et je cours avec mes enfants chaque matin.",
                'category' => 'guerison',
                'bible_verse' => "Il a été blessé pour nos péchés, brisé pour nos iniquités. Le châtiment qui nous donne la paix est tombé sur lui, et c'est par ses meurtrissures que nous sommes guéris.",
                'bible_ref' => 'Ésaïe 53:5',
                'is_featured' => true,
                'views' => 6240,
                'likes' => 503,
            ],
        ];

        foreach ($testimonies as $i => $t) {
            $user = $users[$i % count($users)];
            $catId = $categories[$t['category']] ?? $categories->first();

            Testimony::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'category_id' => $catId,
                'category_slug' => $t['category'],
                'title' => $t['title'],
                'type' => 'text',
                'body_text' => $t['body'],
                'bible_verse' => $t['bible_verse'],
                'bible_ref' => $t['bible_ref'],
                'tags' => ['foi', 'miracle', 'témoignage'],
                'visibility' => 'public',
                'status' => 'approved',
                'is_featured' => $t['is_featured'],
                'views_count' => $t['views'],
                'like_count' => $t['likes'],
                'prayer_count' => rand(20, 150),
                'comment_count' => rand(5, 60),
                'share_count' => rand(5, 40),
                'bookmark_count' => rand(3, 30),
                'approved_by' => User::where('role', 'administrateur')->first()?->id,
                'approved_at' => now()->subDays(rand(1, 30)),
                'created_at' => now()->subDays(rand(1, 180)),
                'updated_at' => now(),
            ]);

            // Update user testimony count
            $user->increment('testimony_count');
        }

        // Add some pending testimonies for moderation
        $pendingTitles = [
            "Ma prière exaucée après 2 ans d'attente",
            "Délivrance d'une sorcellerie familiale",
            "Dieu m'a parlé dans un rêve",
            "Guérison de mon mari après un AVC",
            "Comment j'ai retrouvé mon fils disparu",
        ];

        $offset = count($testimonies);
        foreach ($pendingTitles as $j => $title) {
            $user = $users[($offset + $j + 1) % count($users)];
            $slugs = array_keys($categories->toArray());
            $slug = $slugs[$j % count($slugs)];

            Testimony::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'category_id' => $categories[$slug],
                'category_slug' => $slug,
                'title' => $title,
                'type' => 'text',
                'body_text' => "Ce témoignage est en attente de modération. " . fake()->paragraphs(3, true),
                'visibility' => 'public',
                'status' => 'pending',
                'is_featured' => false,
                'views_count' => 0,
                'like_count' => 0,
                'prayer_count' => 0,
                'comment_count' => 0,
                'created_at' => now()->subHours(rand(1, 48)),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('✅ ' . (count($testimonies) + count($pendingTitles)) . ' témoignages créés.');
    }
}
