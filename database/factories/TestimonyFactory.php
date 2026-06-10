<?php

namespace Database\Factories;

use App\Enums\TestimonyStatus;
use App\Enums\TestimonyType;
use App\Enums\TestimonyVisibility;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TestimonyFactory extends Factory
{
    private static array $titles = [
        "Comment Dieu m'a guéri d'une maladie incurable",
        "Ma délivrance miraculeuse d'une addiction de 10 ans",
        "Dieu a pourvu à mes besoins au moment où j'avais tout perdu",
        "Protection divine lors d'un accident de voiture",
        "Mon mariage restauré après 3 ans de séparation",
        "Comment j'ai trouvé un emploi en pleine crise économique",
        "La guérison de mon enfant diagnostiqué d'une leucémie",
        "Délivrance d'une dépression profonde par la prière",
        "Dieu m'a accordé une bourse d'études miraculeuse",
        "Ma conversion au bord du précipice",
        "La résurrection de ma foi après des années de sécheresse spirituelle",
        "Comment Dieu a restauré mes finances ruinées",
        "Guérison d'une paralysie partielle après des années de souffrance",
        "Ma famille réconciliée après des années de conflit",
        "Dieu m'a donné un enfant après 8 ans d'attente",
        "Protection lors d'une tentative de cambriolage armé",
        "Ma délivrance de la sorcellerie héréditaire",
        "Dieu a comblé ma solitude et m'a donné un conjoint selon son cœur",
        "Comment Dieu m'a sauvé d'une banqueroute certaine",
        "Ma guérison d'un cancer du sein au stade 4",
        "Dieu a ouvert une porte là où toutes les portes étaient fermées",
        "Mon témoignage de guérison de l'hypertension chronique",
        "La délivrance de mon fils de la drogue",
        "Comment Dieu a sauvé mon entreprise de la faillite",
        "Vision et appel au ministère qui a transformé ma vie",
        "Guérison instantanée lors d'une réunion de prière",
        "Dieu a pourvu pour mon loyer au dernier moment",
        "Ma victoire sur la dépendance aux réseaux sociaux grâce à Dieu",
        "Restauration de mon foyer par la grâce de Dieu",
        "Dieu m'a conduit à ma vocation après des années d'errance",
    ];

    private static array $bodies = [
        "Je veux partager avec vous ce que Dieu a fait dans ma vie. Il y a deux ans, les médecins m'avaient condamné. Ils disaient qu'il n'y avait plus rien à faire. Mais j'ai décidé de croire à la Parole de Dieu. J'ai jeûné et prié pendant 21 jours avec mon église. Et un matin, je me suis réveillé totalement guéri. Les médecins eux-mêmes ont été stupéfaits par les résultats de mes examens. Gloire à Dieu !",

        "Pendant dix longues années, j'étais esclave de l'alcool. Ma famille m'avait abandonné. J'avais perdu mon emploi. Je vivais dans la rue. Un ami m'a emmené à une réunion de prière et là, quelque chose s'est brisé en moi. J'ai pleuré comme un enfant et j'ai demandé à Dieu de me libérer. Ce soir-là, le désir de boire a disparu instantanément. Cela fait maintenant 3 ans que je suis sobre.",

        "Suite à un licenciement brutal, je me suis retrouvé sans revenus, avec un loyer à payer et trois enfants à nourrir. J'ai crié à Dieu dans ma détresse. Le lendemain, un cousin que je n'avais pas vu depuis 15 ans m'appelle et me propose de travailler avec lui. Deux semaines plus tard, j'avais un salaire qui dépassait mon ancien poste. Dieu est fidèle !",

        "Je revenais du travail un soir quand un camion a grillé un feu rouge et m'a percuté de plein fouet. Les témoins disaient qu'il était impossible que je survive. Mais j'en suis sorti avec juste quelques égratignures. Le médecin urgentiste a dit qu'il ne comprenait pas. Moi je sais : c'était la main protectrice de Dieu sur ma vie.",

        "Mon mari et moi nous étions séparés depuis trois ans à cause de problèmes d'infidélité. J'avais presque signé les papiers de divorce quand une pasteure m'a dit de tenir bon dans la prière. J'ai jeûné pendant 40 jours. Mon mari est revenu de lui-même, repentant et transformé. Aujourd'hui nous avons renouvelé nos vœux et notre mariage est plus fort que jamais.",
    ];

    private static array $verses = [
        ["Car je suis l'Éternel, ton médecin.", "Exode 15:26"],
        ["Je puis tout par celui qui me fortifie.", "Philippiens 4:13"],
        ["L'Éternel est mon berger, je ne manquerai de rien.", "Psaume 23:1"],
        ["Mon Dieu pourvoira à tous vos besoins selon sa richesse.", "Philippiens 4:19"],
        ["Ne crains rien, car je suis avec toi.", "Ésaïe 41:10"],
        ["Tout est possible à celui qui croit.", "Marc 9:23"],
        ["Dieu est notre refuge et notre force.", "Psaume 46:1"],
        ["Les voies de l'Éternel sont droites.", "Osée 14:9"],
        ["Il guérit ceux qui ont le cœur brisé.", "Psaume 147:3"],
        ["Ta foi t'a sauvé.", "Luc 17:19"],
    ];

    public function definition(): array
    {
        $titleIndex = array_rand(self::$titles);
        $bodyIndex = array_rand(self::$bodies);
        $verseIndex = array_rand(self::$verses);
        $type = $this->faker->randomElement(['text', 'text', 'text', 'audio', 'video']);

        $categories = Category::pluck('id', 'slug');
        $categorySlug = $this->faker->randomElement(['guerison', 'delivrance', 'provision', 'protection', 'famille', 'salut', 'mariage', 'emploi', 'etudes', 'autre']);
        $categoryId = $categories[$categorySlug] ?? $categories->first();

        return [
            'user_id' => User::inRandomOrder()->first()->id ?? User::factory(),
            'category_id' => $categoryId,
            'category_slug' => $categorySlug,
            'title' => self::$titles[$titleIndex],
            'type' => $type,
            'body_text' => $type === 'text' ? self::$bodies[$bodyIndex] : ($this->faker->boolean(70) ? self::$bodies[$bodyIndex] : null),
            'bible_verse' => self::$verses[$verseIndex][0],
            'bible_ref' => self::$verses[$verseIndex][1],
            'tags' => $this->faker->randomElements(['foi', 'guérison', 'miracle', 'témoignage', 'grâce', 'prière', 'délivrance', 'provision'], rand(2, 4)),
            'visibility' => 'public',
            'status' => $this->faker->randomElement(['approved', 'approved', 'approved', 'pending', 'rejected']),
            'is_featured' => $this->faker->boolean(10),
            'views_count' => $this->faker->numberBetween(10, 5000),
            'like_count' => $this->faker->numberBetween(0, 300),
            'prayer_count' => $this->faker->numberBetween(0, 150),
            'comment_count' => $this->faker->numberBetween(0, 80),
            'share_count' => $this->faker->numberBetween(0, 50),
            'bookmark_count' => $this->faker->numberBetween(0, 30),
            'consent_given' => true,
            'created_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'updated_at' => now(),
        ];
    }

    public function approved(): static
    {
        return $this->state(['status' => 'approved']);
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }

    public function featured(): static
    {
        return $this->state(['status' => 'approved', 'is_featured' => true]);
    }
}
